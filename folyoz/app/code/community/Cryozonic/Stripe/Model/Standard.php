<?php
/**
 * Cryozonic
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Single Domain License
 * that is available through the world-wide-web at this URL:
 * http://cryozonic.com/licenses/stripe.html
 * If you are unable to obtain it through the world-wide-web,
 * please send an email to info@cryozonic.com so we can send
 * you a copy immediately.
 *
 * @category   Cryozonic
 * @package    Cryozonic_Stripe
 * @copyright  Copyright (c) Cryozonic Ltd (http://cryozonic.com)
 */

require_once 'Cryozonic/Stripe/init.php';

class Cryozonic_Stripe_Model_Standard extends Mage_Payment_Model_Method_Abstract {
    protected $_code = 'cryozonic_stripe';

    protected $_isInitializeNeeded      = false;
    protected $_canUseForMultishipping  = true;
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canCancelInvoice        = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canSaveCc               = false;
    protected $_formBlockType           = 'cryozonic_stripe/form_standard';

    public $_hasRecurringProducts       = false; // Can be changed by Stripe Subscriptions
    public $saveCards                   = false; // Can be changed by Stripe Subscriptions
    public $sources                     = array();
    public $securityMethod              = null;

    public $allowedPaymentMethods       = array("cryozonic_stripe");

    protected static $_creatingStripeCustomer = false;

    // Docs: http://docs.magentocommerce.com/Mage_Payment/Mage_Payment_Model_Method_Abstract.html
    // mixed $_canCreateBillingAgreement
    // mixed $_canFetchTransactionInfo
    // mixed $_canManageRecurringProfiles
    // mixed $_canOrder
    // mixed $_canReviewPayment
    // array $_debugReplacePrivateDataKeys
    // mixed $_infoBlockType

    // Stripe Modes
    const TEST = 'test';
    const LIVE = 'live';

    // Module Details
    const MODULE_NAME = "Stripe Payments";
    const MODULE_VERSION = "3.2.2";
    const MODULE_URL = "https://store.cryozonic.com/magento-extensions/stripe-payments.html";

    public function __construct()
    {
        $this->helper = Mage::helper('cryozonic_stripe');
        $this->store = $store = $this->getStore();
        $mode = $store->getConfig('payment/cryozonic_stripe/stripe_mode');
        $this->saveCards = $store->getConfig('payment/cryozonic_stripe/ccsave');
        $path = "payment/cryozonic_stripe/stripe_{$mode}_sk";
        $apiKey = trim($store->getConfig($path));
        \Stripe\Stripe::setApiKey($apiKey);
        \Stripe\Stripe::setApiVersion('2017-02-14');
        \Stripe\Stripe::setAppInfo($this::MODULE_NAME, $this::MODULE_VERSION, $this::MODULE_URL);

        $this->ensureStripeCustomer();
    }

    public function addOn($name, $version, $url = null)
    {
        $info = \Stripe\Stripe::getAppInfo();

        if ($name && $version)
            $info['version'] .= ' ' . $name . '/' . $version;

        if ($url)
            $info['url'] .= ', ' . $url;

        \Stripe\Stripe::setAppInfo($info['name'], $info['version'], $info['url']);
    }

    public function getAdminOrderGuestEmail()
    {
        if (Mage::app()->getStore()->isAdmin())
        {
            if (Mage::app()->getRequest()->getParam('order_id'))
            {
                $orderId = Mage::app()->getRequest()->getParam('order_id');
                $order = Mage::getModel('sales/order')->load($orderId);

                if ($order)
                    return $order->getCustomerEmail();
            }
        }

        return null;
    }

    public function getStore()
    {
        // Admins may be viewing an order placed on a specific store
        if (Mage::app()->getStore()->isAdmin())
        {
            try
            {
                if (Mage::app()->getRequest()->getParam('order_id'))
                {
                    $orderId = Mage::app()->getRequest()->getParam('order_id');
                    $order = Mage::getModel('sales/order')->load($orderId);
                    $store = $order->getStore();
                }
                elseif (Mage::app()->getRequest()->getParam('invoice_id'))
                {
                    $invoiceId = Mage::app()->getRequest()->getParam('invoice_id');
                    $invoice = Mage::getModel('sales/order_invoice')->load($invoiceId);
                    $store = $invoice->getStore();
                }
                elseif (Mage::app()->getRequest()->getParam('creditmemo_id'))
                {
                    $creditmemoId = Mage::app()->getRequest()->getParam('creditmemo_id');
                    $creditmemo = Mage::getModel('sales/order_creditmemo')->load($creditmemoId);
                    $store = $creditmemo->getStore();
                }
                else
                {
                    // We are creating a new order
                    $store = $this->getSessionQuote()->getStore();
                }

                if (!empty($store) && $store->getId())
                    return $store;
            }
            catch (\Exception $e) {}
        }

        // Users get the store they are on
        return Mage::app()->getStore();
    }

    public function ensureStripeCustomer($isAtCheckout = true)
    {
        // We only want to do this if saved cards are enabled
        if (!$this->saveCards && !$this->is3DSecureEnabled()) return;

        // Idev OSC can get into an infinite loop here
        if (self::$_creatingStripeCustomer) return;
        self::$_creatingStripeCustomer = true;

        if ($isAtCheckout)
        {
            // If the payment method has not yet been selected, skip this step
            $quote = $this->getSessionQuote();
            $paymentMethod = $quote->getPayment()->getMethod();
            if (empty($paymentMethod) || !in_array($paymentMethod, $this->allowedPaymentMethods))
            {
                self::$_creatingStripeCustomer = false;
                return;
            }
        }

        $customerStripeId = $this->getCustomerStripeId();
        $retrievedSecondsAgo = (time() - $this->customerLastRetrieved);

        if (!$customerStripeId)
        {
            $customer = $this->createStripeCustomer();
        }
        // if the customer was retrieved from Stripe in the last 10 minutes, we're good to go
        // otherwise retrieve them now to make sure they were not deleted from Stripe somehow
        else if ((time() - $this->customerLastRetrieved) > (60 * 10))
        {
            if (!$this->getStripeCustomer($customerStripeId))
            {
                $this->reCreateStripeCustomer($customerStripeId);
            }
        }

        self::$_creatingStripeCustomer = false;
    }

    protected function reCreateStripeCustomer($customerStripeId)
    {
        $this->deleteStripeCustomerId($customerStripeId);
        return $this->createStripeCustomer();
    }

    protected function get3DSecureEmail()
    {
        try
        {
            $info = $this->getInfoInstance();
        }
        catch (Exception $e)
        {
            // Happens in the saved cards customer account section
            return null;
        }

        if ($info->getAdditionalInformation('three_d_secure_pending'))
            return $info->getAdditionalInformation('customer_email');

        return null;
    }

    protected function getCustomerEmail()
    {
        if ($this->customerEmail)
            return $this->customerEmail;

        $quote = $this->getSessionQuote();

        if ($quote)
            $email = trim(strtolower($quote->getCustomerEmail()));

        // This happens with guest checkouts
        if (empty($email))
            $email = trim(strtolower($quote->getBillingAddress()->getEmail()));

        // We might be viewing a guest order from admin
        if (empty($email))
            $email = trim(strtolower($this->getAdminOrderGuestEmail()));

        // Or we may be trying to charge a 3D Secure order through a webhook
        if (empty($email))
            $email = trim(strtolower($this->get3DSecureEmail()));

        return $this->customerEmail = $email;
    }

    public function getCustomerId()
    {
        // If we are in the back office
        if (Mage::app()->getStore()->isAdmin())
        {
            return Mage::getSingleton('adminhtml/sales_order_create')->getQuote()->getCustomerId();
        }
        // If we are on the checkout page
        else if (Mage::getSingleton('customer/session')->isLoggedIn())
        {
            return Mage::getSingleton('customer/session')->getCustomer()->getId();
        }

        return null;
    }

    protected function getSessionQuote()
    {
        // If we are in the back office
        if (Mage::app()->getStore()->isAdmin())
        {
            return Mage::getSingleton('adminhtml/sales_order_create')->getQuote();
        }
        // If we are a user
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    protected function getAvsFields($card)
    {
        if (!is_array($card)) return $card; // Card is a token so AVS should have already been taken care of

        $billingInfo = $this->helper->getSanitizedBillingInfo($this);

        if (empty($billingInfo))
            throw new \Stripe\Error\Card("You must first enter your billing address.");
        else
        {
            $card['address_line1'] = $billingInfo['line1'];
            $card['address_zip'] = $billingInfo['postcode'];
        }

        return $card;
    }

    protected function performAVSChecks($charge)
    {
        // When using Stripe.js, a hacker may try to delete the address details from the Stripe.js API request
        // which would result in a similar response from Stripe as if we did not try to check the address at all
        // DISABLED because Apple Pay does not yet support AVS checks, allow Apple Pay users to complete the order.
        // if (empty($charge->source->address_zip_check) && empty($charge->source->address_line1_check))
        // {
        //     $charge->refund();
        //     Mage::throwException('The purchase could not be completed because no address details were provided');
        // }
    }

    protected function createToken($params)
    {
        // If the card is already a token, such as from Stripe.js or 3D Secure, then don't create a new token
        if (is_string($params['card']) &&
            (
                strpos($params['card'], 'tok_') === 0 ||
                strpos($params['card'], 'tdsrc_') === 0 ||
                strpos($params['card'], 'src_') === 0
            ))
            return $params['card'];

        try
        {
            $params['card'] = $this->getAvsFields($params['card']);

            $this->validateParams($params);

            $token = \Stripe\Token::create($params);

            if (empty($token['id']))
                Mage::throwException($this->t('Sorry, this payment method can not be used at the moment. Try again later.'));

            $this->setCustomerCard($token['card']);

            return $token['id'];
        }
        catch (\Stripe\Error\InvalidRequest $e)
        {
            $this->log($e->getMessage());
            Mage::throwException($this->t($e->getMessage()));
        }
        catch (\Stripe\Error\Card $e)
        {
            $this->log($e->getMessage());
            Mage::throwException($this->t($e->getMessage()));
        }
        catch (\Stripe\Error $e)
        {
            $this->log($e->getMessage());
            Mage::throwException($this->t($e->getMessage()));
        }
        catch (\Exception $e)
        {
            $this->log($e->getMessage());
            Mage::throwException($this->t($e->getMessage()));
        }
    }

    protected function getInfoInstanceCard()
    {
        $info = $this->getInfoInstance();
        return array(
            "card" => array(
                "name" => $info->getCcOwner(),
                "number" => $info->getCcNumber(),
                "cvc" => $info->getCcCid(),
                "exp_month" => $info->getCcExpMonth(),
                "exp_year" => $info->getCcExpYear()
            )
        );
    }

    protected function getToken()
    {
        $info = $this->getInfoInstance();
        $token = $info->getAdditionalInformation('token');

        // Is this a saved card?
        if (strpos($token,'card_') === 0)
            return $token;

        // Are we coming from the back office?
        if (strstr($token,'tok_') === false &&
            strstr($token,'tdsrc_') === false &&
            strstr($token,'src_') === false
            )
        {
            $params = $this->getInfoInstanceCard();
            $token = $this->createToken($params);
        }

        return $token;
    }

    // Use 3D Secure when
    // - Stripe Elements is enabled
    // - 3DS is enabled
    // - We've been passed a 3DS source from Stripe Elements
    // - Of type card, and which supports 3DS
    public function shouldUse3DSecure($source, $cardBrand = null)
    {
        if (!$this->is3DSecureEnabled())
            return false;

        // AmEx do not support 3DS, and we may select a saved card
        if ($cardBrand == 'American Express')
            return false;

        if (strpos($source, 'src_') === 0)
        {
            try
            {
                $source = $this->retrieveSource($source);
                $config = $this->getStore()->getConfig('payment/cryozonic_stripe/three_d_secure');

                $states = array("required");

                if ($config >= 2)
                    $states[] = "optional";

                if ($source->type == 'card' && in_array($source->card->three_d_secure, $states))
                    return true;
            }
            catch (Exception $e)
            {
                Mage::logException($e);
                return false;
            }
        }
        else if (strpos($source, 'card_') === 0)
        {
            return false; // For the time being we have no way of knowing whether the card requires 3DS or not
            $card = $this->retrieveCard($source);
        }

        // In other cases when we receive a regular token, don't trigger 3DS
        return false;
    }

    protected function resetPaymentData()
    {
        $info = $this->getInfoInstance();
        $session = Mage::getSingleton('core/session');

        // Reset a previously initialized 3D Secure session
        $session->setRedirectUrl(null);
        $info->setAdditionalInformation('three_d_secure_pending', false)
             ->setAdditionalInformation('stripejs_token', null)
             ->setCcType(null)
             ->setCcLast4(null)
             ->setAdditionalInformation('save_card', false);
    }

    public function create3DSecureSource($data, $card)
    {
        $info = $this->getInfoInstance();
        $session = Mage::getSingleton('core/session');
        $params3DS = $this->get3DSecureParams(false);

        $quote = $this->getSessionQuote();
        $quote->reserveOrderId();

        $source = \Stripe\Source::create(array(
            "amount" => $params3DS['amount'],
            "currency" => $params3DS['currency'],
            "type" => "three_d_secure",
            "three_d_secure" => array(
                "card" => $card[0],
            ),
            "metadata" => array(
                "Order #" => $quote->getReservedOrderId()
            ),
            "redirect" => array(
                "return_url" => Mage::getUrl('cryozonic_stripe/return')
            ),
        ));

        if (empty($source) || !isset($source->id))
            throw new Exception("Sorry, we could not initiate a card authentication with your bank.");

        // We only want to redirect the customer if 3D Secure is necessary
        if ($source->status == 'pending')
        {
            $session->setRedirectUrl($source->redirect->url);
            $session->setClientSecret($source->client_secret);
            $session->setQuoteId($quote->getId());

            $info->setAdditionalInformation('three_d_secure_pending', true)
                ->setAdditionalInformation('source_id', $source->id)
                ->setAdditionalInformation('customer_stripe_id', $this->getCustomerStripeId())
                ->setAdditionalInformation('customer_email', $this->getCustomerEmail())
                ->setAdditionalInformation('save_card', $this->saveCards && $data['cc_save']);
        }

        return $source;
    }

    public function assignData($data)
    {
        $info = $this->getInfoInstance();
        $session = Mage::getSingleton('core/session');

        // If using a saved card
        if (!empty($data['cc_saved']) && $data['cc_saved'] != 'new_card' && empty($data['cc_stripejs_token']))
        {
            $card = explode(':', $data['cc_saved']);

            $this->resetPaymentData();

            if ($this->shouldUse3DSecure($card[0], $card[1]))
            {
                if (Mage::app()->getStore()->isAdmin())
                    Mage::throwException("This card cannot be used because a 3D Secure Verification is required by the customer.");

                $source = $this->create3DSecureSource($data, $card);
                $info->setAdditionalInformation('token', $source->id);
            }
            else
                $info->setAdditionalInformation('token', $card[0]);

            $info->setAdditionalInformation('save_card', false)
                 ->setCcType($card[1])
                 ->setCcLast4($card[2]);

            return $this;
        }

        // Other scenarios by OSC modules trying to prematurely save payment details
        if (empty($data['cc_stripejs_token']) && empty($data['cc_number']))
            return $this;

        // Stripe Elements OR Stripe.js v2
        if ($this->getSecurityMethod())
        {
            if (empty($data['cc_stripejs_token']))
            {
                // Developers: If you are getting this because of an unsupported OneStepCheckout module,
                // see initOSCModules() in skin/frontend/base/default/cryozonic_stripe/js/cryozonic_stripe.js
                // for code examples on how to integrate the two modules.
                Mage::throwException($this->t("Sorry, we could not perform a card security check. Please contact us to complete your purchase."));
            }

            $card = explode(':', $data['cc_stripejs_token']);
            $data['cc_stripejs_token'] = $card[0]; // To be used by Stripe Subscriptions

            // Security check: If Stripe Elements is enabled, only accept source tokens and saved cards
            if ($this->isStripeElementsEnabled())
            {
                if (strpos($card[0], 'src_') !== 0 && strpos($card[0], 'card_') !== 0 && strpos($card[0], 'tok_') !== 0)
                    Mage::throwException($this->t("Sorry, we could not perform a card security check. Please contact us to complete your purchase."));
            }

            // assignData is called both at the Payment Information step and also at the final Order Review step, so add some safety measures
            // to avoid creating duplicate charges
            $usedToken = $info->getAdditionalInformation('stripejs_token');

            if (!empty($usedToken) && $usedToken == $card[0])
                return $this;
            else
            {
                $this->resetPaymentData();

                $info->setAdditionalInformation('stripejs_token', $card[0])
                    ->setCcType($card[1])
                    ->setCcLast4($card[2]);
            }

            // What to do at the Payment Information step
            if ($this->shouldUse3DSecure($card[0]))
            {
                if (Mage::app()->getStore()->isAdmin())
                    Mage::throwException("This card cannot be used because a 3D Secure Verification is required by the customer.");

                // Stripe Elements with 3D Secure enabled
                try
                {
                    $source = $this->create3DSecureSource($data, $card);

                    $params = array(
                        "card" => $source->id
                    );
                }
                catch (\Stripe\Error\Card $e)
                {
                    $this->resetPaymentData();
                    Mage::throwException($this->t($e->getMessage()));
                }
                catch (\Stripe\Error $e)
                {
                    $this->resetPaymentData();
                    Mage::throwException($this->t($e->getMessage()));
                }
                catch (\Exception $e)
                {
                    $this->resetPaymentData();
                    Mage::throwException($this->t($e->getMessage()));
                }
            }
            else
            {
                // Stripe Elements or Stripe.js v2 enabled
                $params = array(
                    "card" => $card[0]
                );
            }
        }
        // Stripe API (no security)
        else
        {
            $this->resetPaymentData();

            if (empty($data['cc_owner'])) Mage::throwException($this->t("Please specify the cardholder name."));
            if (empty($data['cc_number'])) Mage::throwException($this->t("Please specify a card number."));
            if (empty($data['cc_cid'])) Mage::throwException($this->t("Please specify the card CVC number."));
            if (empty($data['cc_exp_month'])) Mage::throwException($this->t("Please specify the card expiration month."));
            if (empty($data['cc_exp_year'])) Mage::throwException($this->t("Please specify the card expiration year."));

            $params = array(
                "card" => array(
                    "name" => $data['cc_owner'],
                    "number" => $data['cc_number'],
                    "cvc" => $data['cc_cid'],
                    "exp_month" => $data['cc_exp_month'],
                    "exp_year" => $data['cc_exp_year']
                )
            );
        }

        $isSourceToken = (is_string($params['card']) && strpos($params['card'], 'src_') !== false);

        // Add the card to the customer
        if ($this->saveCards && $data['cc_save'] && !$session->getRedirectUrl())
        {
            try
            {
                // @todo - we can pass a fingerprint as a second parameter if we are coming from the admin area
                $card = $this->addCardToCustomer($params['card']);
                $token = $card->id;
            }
            catch (\Stripe\Error\Card $e)
            {
                $this->resetPaymentData();
                Mage::throwException($this->t($e->getMessage()));
            }
            catch (\Stripe\Error $e)
            {
                $this->resetPaymentData();
                Mage::logException($e);
                Mage::throwException($this->t($e->getMessage()));
            }
            catch (\Exception $e)
            {
                $this->resetPaymentData();
                Mage::logException($e);
                Mage::throwException($this->t($e->getMessage()));
            }
        }
        else if (!$isSourceToken)
        {
            $token = $this->createToken($params);
        }
        else // is source token
        {
            $token = $params['card'];
        }

        $info->setAdditionalInformation('token', $token);

        if ($this->customerCard)
        {
            $info->setCcType($this->customerCard['brand'])
                ->setCcLast4($this->customerCard['last4']);
        }

        return $this;
    }

    public function setCustomerCard($card)
    {
        if (isset($card->last4) && isset($card->brand))
        {
            $this->customerCard = array(
                "last4" => $card->last4,
                "brand" => $card->brand
            );
        }
    }

    public function convertSourceToCard($source)
    {
        if (!$source || empty($source->card))
            return null;

        $card = $source->card;
        $card->id = $source->id;
        return $card;
    }

    public function findCardFromCustomer($customer, $last4, $expMonth, $expYear)
    {
        $cards = $this->listCards($customer->id);
        foreach ($cards as $card)
        {
            if ($last4 == $card->last4 &&
                $expMonth == $card->exp_month &&
                $expYear == $card->exp_year)
            {
                return $card;
            }
        }

        return false;
    }

    public function addCardToCustomer($newcard, $fingerprint = null, $customerStripeId = null)
    {
        if (empty($customerStripeId))
            $customerStripeId = $this->getCustomerStripeId();

        if (empty($customerStripeId))
            return null;

        $customer = $this->getStripeCustomer($customerStripeId);

        // Rare occation with stale test data && customerLastRetrieved < 10 mins
        if (!$customer)
            $customer = $this->reCreateStripeCustomer($customerStripeId);

        if (!$customer)
            throw new Exception("Could not save the customer's card because the customer could not be created in Stripe!");

        // The Stripe API is used
        if (is_array($newcard) && !empty($newcard['number']))
        {
            // Check if the customer already has this card and set it as the default if so
            $last4 = substr($newcard['number'], -4);
            $month = $newcard['exp_month'];
            $year = $newcard['exp_year'];
            $card = $this->findCardFromCustomer($customer, $last4, $month, $year);
            if ($card)
            {
                $customer->default_source = $card->id;
                $customer->save();
                $this->setCustomerCard($card);
                return $card;
            }
            $newcard = $this->getAvsFields($newcard);
            $newcard["object"] = "card";
            $card = $customer->sources->create(array('source' => $newcard));
            $customer->default_source = $card->id;
            $customer->save();
            $this->setCustomerCard($card);
            return $card;
        }
        // If we are adding a source
        else if (is_string($newcard) && strpos($newcard, 'src_') === 0)
        {
            $source = $this->retrieveSource($newcard);
            if ($source->type == 'card')
            {
                $card = $source->card;
                $card->id = $source->id;
            }
            else if ($source->type == 'three_d_secure')
            {
                // We can get here when:
                // 1. 3D Secure is configured as "Required or optional" and
                // 2. The card is optional and __chargeable__ at the same time, thus we are not redirecting the customer and
                // 3. The customer is trying to save this card at the checkout
                $card = $customer->sources->create(array('source' => $source->three_d_secure->card));
                $customer->default_source = $card->id;
                $customer->save();
                $this->setCustomerCard($card);
                return $card;
            }
            else if ($source->usage == 'reusable' && !isset($source->amount))
            {
                // SEPA Direct Debit with no amount set, no deduplication here
                $card = $customer->sources->create(array('source' => $source->id));
                $customer->default_source = $card->id;
                $customer->save();
                $this->setCustomerCard($card);
                return $card;
            }
            else
            {
                // Bancontact, iDEAL etc
                return null;
            }

            if (isset($card->last4))
            {
                $last4 = $card->last4;
                $month = $card->exp_month;
                $year = $card->exp_year;
                $exists = $this->findCardFromCustomer($customer, $last4, $month, $year);
                if ($exists)
                {
                    $customer->default_source = $exists->id;
                    $customer->save();
                    $this->setCustomerCard($exists);
                    return $exists;
                }
                else
                {
                    $card2 = $customer->sources->create(array('source' => $card->id));
                    $customer->default_source = $card2->id;
                    $customer->save();
                    $this->setCustomerCard($card2);
                    return $card2;
                }
            }
        }
        // This should never hit, but if it does, assume it is already saved and set the card as the default
        else if (is_string($newcard) && strpos($newcard, 'card_') === 0)
        {
            $card = $this->retrieveCard($newcard);
            $customer->default_source = $card->id;
            $customer->save();
            $this->setCustomerCard($card);
            return $card;
        }
        // Stripe.js v2
        else if (is_string($newcard) && strpos($newcard, 'tok_') === 0)
        {
            $token = $this->retrieveToken($newcard);
            $card = $token->card;
            $last4 = $card->last4;
            $month = $card->exp_month;
            $year = $card->exp_year;
            $card = $this->findCardFromCustomer($customer, $last4, $month, $year);
            if ($card)
            {
                $customer->default_source = $card->id;
                $customer->save();
                $this->setCustomerCard($card);
                return $card;
            }
            $card = $customer->sources->create(array('source' => $newcard));
            $customer->default_source = $card->id;
            $customer->save();
            $this->setCustomerCard($card);
            return $card;
        }

        return null;
    }

    public function retrieveToken($token)
    {
        if (isset($this->sources[$token]))
            return $this->sources[$token];

        $this->sources[$token] = \Stripe\Token::retrieve($token);

        return $this->sources[$token];
    }

    public function retrieveSource($token)
    {
        if (isset($this->sources[$token]))
            return $this->sources[$token];

        $this->sources[$token] = \Stripe\Source::retrieve($token);

        return $this->sources[$token];
    }

    public function retrieveCard($token)
    {
        if (isset($this->sources[$token]))
            return $this->sources[$token];

        $customer = $this->getStripeCustomer();
        $card = $customer->sources->retrieve($token);
        $this->sources[$token] = $card;

        return $card;
    }

    public function is3DSecurePending()
    {
        if (!$this->is3DSecureEnabled())
            return false;

        $info = $this->getInfoInstance();
        $token = $info->getAdditionalInformation('token');
        if (strpos($token, 'src_') !== 0)
            return false;

        try
        {
            $source = $this->retrieveSource($token);

            if ($source->type !== 'three_d_secure')
                return false;

            $isAdminCapture = (Mage::app()->getStore()->isAdmin() && $this->sources[$token]->status == 'chargeable');

            // A source is Pending authorization, and is then Chargeable until charged after a webhook event. It then becomes Consumed.
            return ($this->sources[$token]->status == 'pending' || $isAdminCapture);
        }
        catch (\Stripe\Error\Card $e)
        {
            Mage::throwException($this->t($e->getMessage()));
        }
        catch (\Stripe\Error $e)
        {
            Mage::logException($e);
            Mage::throwException($this->t($e->getMessage()));
        }
        catch (\Exception $e)
        {
            if (stripos($e->getMessage(), "a similar object exists in test mode, but a live mode key was used") !== false)
                return false;
            else if (stripos($e->getMessage(), "No such ") === 0)
            {
                Mage::getSingleton('core/session')->addError("Payment details for this order could not be found in your Stripe account (" . $e->getMessage() . ")");
                return false;
            }
            Mage::logException($e);
            Mage::throwException($this->t($e->getMessage()));
        }
    }

    public function getMultiCurrencyAmount($payment, $baseAmount)
    {
        if (!Mage::getStoreConfig('payment/cryozonic_stripe/use_store_currency'))
            return $baseAmount;

        $order = $payment->getOrder();
        $grandTotal = $order->getGrandTotal();
        $baseGrandTotal = $order->getBaseGrandTotal();

        $rate = $order->getStoreToOrderRate();

        // Full capture, ignore currency rate in case it changed
        if ($baseAmount == $baseGrandTotal)
            return $grandTotal;
        // Partial capture, consider currency rate but don't capture more than the original amount
        else if (is_numeric($rate))
            return min($baseAmount * $rate, $grandTotal);
        // Not a multicurrency capture
        else
            return $baseAmount;
    }

    public function canCapture()
    {
        return parent::canCapture() && !$this->is3DSecurePending();
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment, $amount);

        if ($amount > 0 && !$this->is3DSecurePending())
        {
            $this->createCharge($payment, false);
        }

        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);

        if ($amount > 0)
        {
            // We get in here when the store is configured in Authorize Only mode and we are capturing a payment from the admin
            $token = $payment->getTransactionId();
            if (empty($token))
                $token = $payment->getLastTransId(); // In case where the transaction was not created during the checkout, i.e. with a Stripe Webhook redirect

            if (Mage::app()->getStore()->isAdmin() && $token)
            {
                if ($payment->getAdditionalInformation('three_d_secure_pending') && !$payment->getAdditionalInformation('stripe_authorized'))
                    Mage::throwException("The customer has not yet authorized the payment using 3D Secure.");

                $token = $this->helper->cleanToken($token);
                try
                {
                    $ch = \Stripe\Charge::retrieve($token);

                    $finalAmount = $this->getMultiCurrencyAmount($payment, $amount);

                    $currency = $payment->getOrder()->getOrderCurrencyCode();
                    $cents = 100;
                    if ($this->isZeroDecimal($currency))
                        $cents = 1;

                    if ($ch->captured)
                    {
                        // In theory this condition should never evaluate, but is added for safety
                        if ($ch->currency != strtolower($currency))
                            Mage::throwException("This invoice has already been captured in Stripe using a different currency ({$ch->currency}).");

                        $capturedAmount = $ch->amount - $ch->amount_refunded;

                        if ($capturedAmount != round($finalAmount * $cents))
                        {
                            $humanReadableAmount = strtoupper($ch->currency) . " " . round($capturedAmount / $cents, 2);
                            Mage::throwException("This invoice has already been captured in Stripe for a different amount ($humanReadableAmount). Please cancel and create a new offline invoice for the correct amount.");
                        }

                        // We return instead of trying to capture the payment to simulate an Offline capture
                        return $this;
                    }

                    $ch->capture(array('amount' => round($finalAmount * $cents)));
                }
                catch (\Exception $e)
                {
                    $this->log($e->getMessage());
                    if (Mage::app()->getStore()->isAdmin() && $this->isAuthorizationExpired($e->getMessage()) && $this->retryWithSavedCard())
                        $this->createCharge($payment, true, true);
                    else
                        Mage::throwException($e->getMessage());
                }
            }
            else
            {
                // Normal checkout payments in Authorize & Capture mode
                // && Admin-placed orders in Authorize & Capture mode
                $this->createCharge($payment, true);
            }
        }

        return $this;
    }

    protected function isAuthorizationExpired($errorMessage)
    {
        return (strstr($errorMessage, "cannot be captured because the charge has expired") !== false);
    }

    protected function retryWithSavedCard()
    {
        return Mage::getStoreConfig('payment/cryozonic_stripe/expired_authorizations');
    }

    public function isZeroDecimal($currency)
    {
        return Mage::helper('cryozonic_stripe')->isZeroDecimal($currency);
    }

    public function getStripeParamsFrom($order)
    {
        if (Mage::getStoreConfig('payment/cryozonic_stripe/use_store_currency'))
        {
            $amount = $order->getGrandTotal();
            $currency = $order->getOrderCurrencyCode();
        }
        else
        {
            $amount = $order->getBaseGrandTotal();
            $currency = $order->getBaseCurrencyCode();
        }

        $cents = 100;
        if ($this->isZeroDecimal($currency))
            $cents = 1;

        $params = array(
          "amount" => round($amount * $cents),
          "currency" => $currency,
          "description" => "Order #".$order->getRealOrderId().' by '.$order->getCustomerName(),
        );

        if (Mage::getStoreConfig('payment/cryozonic_stripe/receipt_email'))
            $params["receipt_email"] = $this->getCustomerEmail();

        return $params;
    }

    public function createCharge(Varien_Object $payment, $capture, $forceUseSavedCard = false)
    {
        if ($forceUseSavedCard)
        {
            $token = $this->getSavedCardFrom($payment);
            $this->customerStripeId = $this->getCustomerStripeId($payment->getOrder()->getCustomerId());

            if (!$token || !$this->customerStripeId)
                Mage::throwException('The authorization has expired and the customer has no saved cards to re-create the order.');
        }
        else if ($payment->getAdditionalInformation('three_d_secure_pending'))
            $token = $payment->getAdditionalInformation('source_id');
        else if ($payment->getAdditionalInformation('force_source_id'))
            $token = $payment->getAdditionalInformation('source_id');
        else
            $token = $this->getToken();

        try {
            $order = $payment->getOrder();

            $params = $this->getStripeParamsFrom($order);

            $params["source"] = $token;
            $params["capture"] = $capture;

            // If this is a saved card, pass the customer id too
            if (strpos($token, 'card_') === 0 || strpos($token, 'src_') === 0)
            {
                $customerStripeId = $this->getCustomerStripeId($order->getCustomerId());
                if ($customerStripeId)
                    $params["customer"] = $customerStripeId;
            }

            // If this is a 3D Secure charge, pass the customer id
            if ($payment->getAdditionalInformation('customer_stripe_id'))
                $params["customer"] = $payment->getAdditionalInformation('customer_stripe_id');

            $stripeRadarEnabled = $this->isStripeRadarEnabled();

            if ($stripeRadarEnabled)
                $params["capture"] = false;

            $this->validateParams($params);

            $params['metadata'] = $this->getChargeMetadataFrom($payment);

            $statementDescriptor = $this->getStore()->getConfig('payment/' . $this->_code . '/statement_descriptor');

            if (!empty($statementDescriptor))
                $params['statement_descriptor'] = $statementDescriptor;

            $charge = \Stripe\Charge::create($params);

            $manualReview = false;
            if ($stripeRadarEnabled)
            {
                if (isset($charge->outcome->type) && $charge->outcome->type == 'manual_review')
                {
                    $manualReview = true;
                    if (isset($charge->outcome->risk_level) && $charge->outcome->risk_level != 'normal')
                        $payment->setIsFraudDetected(true);
                }
                else if ($capture)
                {
                    $charge->capture();
                }
            }

            if (!$charge->captured)
            {
                if ($this->getStore()->getConfig('payment/cryozonic_stripe/automatic_invoicing') || $manualReview)
                {
                    $payment->setIsTransactionPending(true);
                    $invoice = $order->prepareInvoice();
                    $invoice->register();
                    $order->addRelatedObject($invoice);
                }
            }

            // Saved cards have been AVS verified when they were initially saved
            if (strpos($token, 'card_') !== 0)
                $this->performAVSChecks($charge);

            // For 3D Secure, mark the payment as authorized so that it can be captured later
            if (!$params["capture"] && $payment->getAdditionalInformation('three_d_secure_pending'))
                $payment->setAdditionalInformation('stripe_authorized', true);

            $payment->setTransactionId($charge->id);
            $payment->setIsTransactionClosed(0);

            // Set the order status according to the configuration
            $newOrderStatus = Mage::getStoreConfig('payment/cryozonic_stripe/order_status');
            if (!empty($newOrderStatus) && !$payment->getAdditionalInformation('three_d_secure_pending'))
                $order->addStatusToHistory($newOrderStatus, $this->t('Changing order status as per New Order Status configuration'));

        }
        catch (\Stripe\Error\Card $e)
        {
            $this->log($e->getMessage());
            Mage::throwException($this->t($e->getMessage()));
        }
        catch (\Stripe\Error $e)
        {
            Mage::logException($e);
            Mage::throwException($this->t($e->getMessage()));
        }
        catch (\Exception $e)
        {
            Mage::logException($e);
            Mage::throwException($this->t($e->getMessage()));
        }
    }

    public function getChargeMetadataFrom($payment)
    {
        $metadata = array();

        $order = $payment->getOrder();
        $metadata["Order #"] = $order->getIncrementId();

        return $metadata;
    }

    public function validateParams($params)
    {
        if (is_array($params) && isset($params['card']) && is_array($params['card']) && empty($params['card']['number']))
            Mage::throwException("Unable to use Stripe.js, please see http://store.cryozonic.com/documentation/magento-1-stripe-payments#stripejs");
    }

    public function isStripeRadarEnabled()
    {
        $riskLevel = Mage::getStoreConfig('payment/cryozonic_stripe/radar_risk_level');

        return $riskLevel > 0 && !Mage::app()->getStore()->isAdmin();
    }

    protected function getSavedCardFrom(Varien_Object $payment)
    {
        $card = $payment->getAdditionalInformation('token');

        if (strstr($card, 'card_') === false)
        {
            // $cards will be NULL if the customer has no cards
            $cards = $this->getCustomerCards(true, $payment->getOrder()->getCustomerId());
            if (is_array($cards) && !empty($cards[0]))
                return $cards[0]->id;
        }

        if (strstr($card, 'card_') === false)
            return null;

        return $card;
    }

    /**
     * Cancel payment
     *
     * @param   Varien_Object $invoicePayment
     * @return  Mage_Payment_Model_Abstract
     */
    public function cancel(Varien_Object $payment, $amount = null)
    {
        if (Mage::getStoreConfig('payment/cryozonic_stripe/use_store_currency'))
        {
            // Captured
            $creditmemo = $payment->getCreditmemo();
            if (!empty($creditmemo))
            {
                $rate = $creditmemo->getStoreToOrderRate();
                if (!empty($rate) && is_numeric($rate))
                    $amount *= $rate;
            }
            // Authorized
            $amount = (empty($amount)) ? $payment->getOrder()->getTotalDue() : $amount;

            $currency = $payment->getOrder()->getOrderCurrencyCode();
        }
        else
        {
            // Authorized
            $amount = (empty($amount)) ? $payment->getOrder()->getBaseTotalDue() : $amount;

            $currency = $payment->getOrder()->getBaseCurrencyCode();
        }

        $transactionId = $payment->getParentTransactionId();

        // With asynchronous payment methods, the parent transaction may be empty
        if (empty($transactionId))
            $transactionId = $payment->getLastTransId();

        // Case where an invoice is in Pending status, with no transaction ID, receiving a source.failed event which cancels the invoice.
        if (empty($transactionId))
            return $this;

        $transactionId = $this->helper->cleanToken($transactionId);

        try {
            $cents = 100;
            if ($this->isZeroDecimal($currency))
                $cents = 1;

            $params = array(
                'amount' => round($amount * $cents)
            );

            $charge = \Stripe\Charge::retrieve($transactionId);

            // SEPA and SOFORT may have failed charges, refund those offline
            if ($charge->status == "failed")
            {
                return $this;
            }
            // This is true when an authorization has expired, when there was a refund through the Stripe account, or when a partial refund is performed
            if (!$charge->refunded)
            {
                $charge->refund($params);

                $refundId = $this->helper->getRefundIdFrom($charge);
                $payment->setAdditionalInformation('last_refund_id', $refundId);
            }
            else if ($payment->getAmountPaid() == 0)
            {
                // This is an expired authorized only order, which means that it cannot be refunded online or offline
                return $this;
            }
            else
            {
                Mage::throwException('This order has already been refunded in Stripe. To refund from Magento, please refund it offline.');
            }
        }
        catch (\Exception $e)
        {
            $this->log('Could not refund payment: '.$e->getMessage());
            Mage::throwException($this->t('Could not refund payment: ').$e->getMessage());
        }

        return $this;
    }

    /**
     * Refund money
     *
     * @param   Varien_Object $invoicePayment
     * @return  Mage_Payment_Model_Abstract
     */
    public function refund(Varien_Object $payment, $amount)
    {
        parent::refund($payment, $amount);
        $this->cancel($payment, $amount);

        return $this;
    }

    /**
     * Void payment
     *
     * @param   Varien_Object $invoicePayment
     * @return  Mage_Payment_Model_Abstract
     */
    public function void(Varien_Object $payment)
    {
        parent::void($payment);
        $this->cancel($payment);

        return $this;
    }

    public function getCustomerStripeId($customerId = null)
    {
        if ($this->customerStripeId)
            return $this->customerStripeId;

        // Get the magento customer id
        if (empty($customerId))
            $customerId = $this->getCustomerId();

        if (!empty($customerId) && $customerId < 1)
            $customerId = null;

        if (empty($customerId) && !$this->getCustomerEmail())
             return false;

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $query = $connection->select()
            ->from('cryozonic_stripesubscriptions_customers', array('*'));

        $guestSelect = $connection->quoteInto('customer_email=?', $this->getCustomerEmail());

        // Security measure for the front-end
        if (!Mage::app()->getStore()->isAdmin())
            $guestSelect .= ' and ' . $connection->quoteInto('session_id=?', Mage::getSingleton("core/session")->getEncryptedSessionId());

        if (!empty($customerId) && $this->getCustomerEmail())
            $query = $query->where('customer_id=?', $customerId)->orWhere($guestSelect);
        else if (!empty($customerId))
            $query = $query->where('customer_id=?', $customerId);
        else
            $query = $query->where($guestSelect);

        $result = $connection->fetchRow($query);
        if (empty($result)) return false;
        $this->customerLastRetrieved = $result['last_retrieved'];
        return $this->customerStripeId = $result['stripe_id'];
    }

    public function getCustomerStripeIdByEmail($maxAge = null)
    {
        $email = $this->getCustomerEmail();

        if (empty($email))
            return false;

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $query = $connection->select()
            ->from('cryozonic_stripesubscriptions_customers', array('*'))
            ->where($connection->quoteInto('customer_email=? and session_id=?', $email, Mage::getSingleton("core/session")->getEncryptedSessionId()));

        if (!empty($maxAge))
            $query = $query->where('last_retrieved >= ?', time() - $maxAge);

        $result = $connection->fetchRow($query);
        if (empty($result)) return false;
        return $this->customerStripeId = $result['stripe_id'];
    }

    protected function createStripeCustomer()
    {
        $quote = $this->getSessionQuote();
        $customerFirstname = $quote->getCustomerFirstname();
        $customerLastname = $quote->getCustomerLastname();
        $customerEmail = $quote->getCustomerEmail();
        $customerId = $quote->getCustomerId();

        // This may happen if we are creating an order from the back office
        if (empty($customerId) && empty($customerEmail))
            return;

        // When we are in guest or new customer checkout, we may have already created this customer
        if ($this->getCustomerStripeIdByEmail() !== false)
            return;

        // This is the case for new customer registrations and guest checkouts
        if (empty($customerId))
            $customerId = -1;

        try
        {
            $response = \Stripe\Customer::create(array(
              "description" => "$customerFirstname $customerLastname",
              "email" => $customerEmail
            ));
            $response->save();

            $this->setStripeCustomerId($response->id, $customerId);

            return $this->customer = $response;
        }
        catch (\Exception $e)
        {
            $this->log('Could not set up customer profile: '.$e->getMessage());
            Mage::throwException($this->t('Could not set up customer profile: ').$this->t($e->getMessage()));
        }

    }

    public function getStripeCustomer($id = null)
    {
        if (!empty($id))
        {
            $object = $this->helper->getCachedObject('customer_' . $id);
            if ($object)
                $this->customer = $object;
        }

        if ($this->customer)
            return $this->customer;

        if (empty($id))
            $id = $this->getCustomerStripeId();

        if (empty($id))
            return false;

        try
        {
            $this->customer = \Stripe\Customer::retrieve($id);
            $this->updateLastRetrieved($this->customer->id);
            if (!$this->customer || ($this->customer && isset($this->customer->deleted) && $this->customer->deleted))
                return false;

            $this->helper->cacheObject($this->customer, 'customer_' . $id, 30);

            return $this->customer;
        }
        catch (\Exception $e)
        {
            $this->log($this->t('Could not retrieve customer profile: '.$e->getMessage()));
            return false;
        }
    }

    public function deleteCards($cards)
    {
        $customer = $this->getStripeCustomer();

        if ($customer)
        {
            foreach ($cards as $cardId)
            {
                try
                {
                    $this->retrieveCard($cardId)->delete();
                }
                catch (\Exception $e)
                {
                    Mage::logException($e);
                }
            }
            $customer->save();
        }
    }

    protected function updateLastRetrieved($stripeCustomerId)
    {
        try
        {
            $resource = Mage::getSingleton('core/resource');
            $connection = $resource->getConnection('core_write');
            $fields = array();
            $fields['last_retrieved'] = time();
            $condition = array($connection->quoteInto('stripe_id=?', $stripeCustomerId));
            $result = $connection->update('cryozonic_stripesubscriptions_customers', $fields, $condition);
        }
        catch (\Exception $e)
        {
            $this->log($this->t('Could not update Stripe customers table: '.$e->getMessage()));
        }
    }

    protected function deleteStripeCustomerId($stripeId)
    {
        try
        {
            $resource = Mage::getSingleton('core/resource');
            $connection = $resource->getConnection('core_write');
            $condition = array($connection->quoteInto('stripe_id=?', $stripeId));
            $connection->delete('cryozonic_stripesubscriptions_customers',$condition);
        }
        catch (\Exception $e)
        {
            $this->log($this->t('Could not clear Stripe customers table: '.$e->getMessage()));
        }
    }

    protected function setStripeCustomerId($stripeId, $forCustomerId)
    {
        try
        {
            $resource = Mage::getSingleton('core/resource');
            $connection = $resource->getConnection('core_write');
            $fields = array();
            $fields['stripe_id'] = $stripeId;
            $fields['customer_id'] = $forCustomerId;
            $fields['last_retrieved'] = time();
            $fields['customer_email'] = $this->getCustomerEmail();
            $fields['session_id'] = Mage::getSingleton("core/session")->getEncryptedSessionId();
            $condition = array($connection->quoteInto('customer_id=? OR customer_email=?', $forCustomerId, $fields['customer_email']));
            $connection->delete('cryozonic_stripesubscriptions_customers',$condition);
            $result = $connection->insert('cryozonic_stripesubscriptions_customers', $fields);
        }
        catch (\Exception $e)
        {
            $this->log($this->t('Could not update Stripe customers table: '.$e->getMessage()));
        }
    }

    public function getCustomerCards($isAdmin = false, $customerId = null)
    {
        if (!$this->saveCards && !$isAdmin)
            return null;

        if (!$customerId)
            $customerId = $this->getCustomerId();

        if (!$customerId)
            return null;

        $customerStripeId = $this->getCustomerStripeId($customerId);
        if (!$customerStripeId)
            return null;

        return $this->listCards($customerStripeId);
    }

    private function listCards($customerStripeId, $params = array())
    {
        try
        {
            $sources = $this->getStripeCustomer($customerStripeId)->sources;
            if (!empty($sources))
            {
                // Cards created through the Sources API
                $data = $sources->all()->data;
                $cards = array();
                foreach ($data as $source) {
                    if ($source->type == 'card')
                        $cards[] = $this->convertSourceToCard($source);
                }

                // Normal cards
                $params['object'] = 'card';
                $data = $sources->all($params)->data;
                foreach ($data as $card)
                    $cards[] = $card;

                return $cards;
            }
            else
                return null;
        }
        catch (\Exception $e)
        {
            return null;
        }
    }

    protected function log($msg)
    {
        Mage::log("Stripe Payments - ".$msg);
    }

    protected function t($str) {
        return $this->helper->__($str);
    }

    public function isGuest()
    {
        $method = $this->getSessionQuote()->getCheckoutMethod();
        if ($method == "register")
            return false;
        else if ($method == "guest")
            return true;

        return false;
    }

    public function showSaveCardOption()
    {
        return ($this->saveCards && !$this->isGuest());
    }

    protected function hasRecurringProducts()
    {
        return $this->_hasRecurringProducts;
    }

    public function alwaysSaveCard()
    {
        return ($this->hasRecurringProducts() || $this->saveCards == 2);
    }

    public function getSecurityMethod()
    {
        if (empty($this->securityMethod))
            $this->securityMethod = $this->getStore()->getConfig('payment/cryozonic_stripe/stripe_js');

        if (!is_numeric($this->securityMethod))
            return 0;
        else
            return $this->securityMethod;
    }

    public function isStripeJsEnabled()
    {
        return $this->getSecurityMethod() == 1;
    }

    public function isStripeElementsEnabled()
    {
        return $this->getSecurityMethod() == 2;
    }

    public function is3DSecureEnabled()
    {
        return $this->getStore()->getConfig('payment/cryozonic_stripe/three_d_secure')
            && $this->isStripeElementsEnabled()
            && !$this->hasRecurringProducts(); // 3DS tokens cannot be used on subscriptions
    }

    public function getAmountCurrencyFromQuote($quote, $useCents = true)
    {
        $params = array();
        $items = $quote->getAllItems();

        if (Mage::getStoreConfig('payment/cryozonic_stripe/use_store_currency'))
        {
            $amount = $quote->getGrandTotal();
            $currency = $quote->getQuoteCurrencyCode();

            foreach ($items as $item)
                if ($item->getProduct()->isRecurring())
                    $amount += $item->getNominalRowTotal();
        }
        else
        {
            $amount = $quote->getBaseGrandTotal();;
            $currency = $quote->getBaseCurrencyCode();

            foreach ($items as $item)
                if ($item->getProduct()->isRecurring())
                    $amount += $item->getBaseNominalRowTotal();
        }

        if ($useCents)
        {
            $cents = 100;
            if ($this->isZeroDecimal($currency))
                $cents = 1;

            $fields["amount"] = round($amount * $cents);
        }
        else
        {
            // Used for Apple Pay only
            $fields["amount"] = number_format($amount, 2, '.', '');
        }

        $fields["currency"] = $currency;

        return $fields;
    }

    public function get3DSecureParams($encode = true)
    {
        if (!$this->is3DSecureEnabled())
            return 'null';

        $quote = $this->getSessionQuote();
        if (empty($quote))
            return 'null';

        $fields = $this->getAmountCurrencyFromQuote($quote);

        $params['amount'] = $fields["amount"];
        $params['currency'] = $fields["currency"];
        $params['initiate_three_d_secure_url'] = $this->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true) . 'cryozonic_stripe/secure/index?' . time();

        if ($encode)
            return json_encode($params);

        return $params;
    }

    public function isApplePayEnabled()
    {
        return $this->getStore()->getConfig('payment/cryozonic_stripe/apple_pay_checkout')
            && ($this->isStripeJsEnabled() || $this->isStripeElementsEnabled())
            && !Mage::app()->getStore()->isAdmin();
    }

    public function isPaymentRequestButtonEnabled()
    {
        return $this->isApplePayEnabled() && $this->isStripeElementsEnabled();
    }

    public function getApplePayParams($encode = true)
    {
        if (!$this->isApplePayEnabled())
            return 'null';

        $quote = $this->getSessionQuote();
        if (empty($quote))
            return 'null';

        $fields = $this->getAmountCurrencyFromQuote($quote, false);
        $email = $this->getCustomerEmail();
        $first = $quote->getCustomerFirstname();
        $last = $quote->getCustomerLastname();
        if (empty($email))
            $label = "Order by $first $last";
        else
            $label = "Order by $first $last <$email>";
        $countryCode = $quote->getBillingAddress()->getCountryId();

        $currency = strtolower($fields["currency"]);
        $cents = 100;
        if ($this->isZeroDecimal($currency))
            $cents = 1;

        $amount = round($fields["amount"] * $cents);

        $params = array(
            "country" => $countryCode,
            "currency" => $currency,
            "total" => array(
                "label" => $label,
                "amount" => $amount
            )
        );

        // We are likely not on the checkout page, might be the shopping cart or another page initializing the payment method
        if (empty($fields["currency"]) || empty($fields["amount"]))
            return 'null';

        if ($encode)
            return json_encode($params);

        return $params;
    }

    public function retrieveCharge($chargeId)
    {
        return \Stripe\Charge::retrieve($chargeId);
    }

    public function isAvailable($quote = null)
    {
        if (empty($quote))
            return parent::isAvailable($quote);

        $minAmount = $this->getStore()->getConfig('payment/cryozonic_stripe/minimum_order_amount');

        if (!is_numeric($minAmount) || $minAmount <= 0)
            $minAmount = 0.3;

        $fields = $this->getAmountCurrencyFromQuote($quote, false);
        $grandTotal = $fields['amount'];

        if ($grandTotal < $minAmount)
            return false;

        return parent::isAvailable($quote);
    }

    public function getOrderPlaceRedirectUrl()
    {
        $session = Mage::getSingleton('core/session');
        $session->setReservedOrderId($this->getSessionQuote()->getReservedOrderId());
        return $session->getRedirectUrl();
    }

    // Public logging method
    public function plog($msg)
    {
        return $this->log($msg);
    }
}
