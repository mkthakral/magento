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
require_once 'Cryozonic/Stripe/lib/Stripe.php';

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

    protected $_hasRecurringProducts    = false; // Can be changed by Stripe Subscriptions

    // Docs: http://docs.magentocommerce.com/Mage_Payment/Mage_Payment_Model_Method_Abstract.html
    // mixed $_canCreateBillingAgreement
    // mixed $_canFetchTransactionInfo
    // mixed $_canManageRecurringProfiles
    // mixed $_canOrder
    // mixed $_canReviewPayment
    // array $_debugReplacePrivateDataKeys
    // mixed $_infoBlockType

    /**
     * Stripe Modes
     */
    const TEST = 'test';
    const LIVE = 'live';

    public function __construct()
    {
        $this->helper = Mage::helper('cryozonic_stripe');
        $this->store = $store = $this->getStore();
        $mode = $store->getConfig('payment/cryozonic_stripe/stripe_mode');
        $this->saveCards = $store->getConfig('payment/cryozonic_stripe/ccsave');
        $path = "payment/cryozonic_stripe/stripe_{$mode}_sk";
        $apiKey = $store->getConfig($path);
        Stripe::setApiKey($apiKey);
        Stripe::setApiVersion('2016-07-06');

        $this->ensureStripeCustomer();
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
            catch (Exception $e) {}
        }

        // Users get the store they are on
        return Mage::app()->getStore();
    }

    protected function ensureStripeCustomer()
    {
        // We only want to do this if saved cards are enabled
        if (!$this->saveCards && !$this->is3DSecureEnabled()) return;

        // If the payment method has not yet been selected, skip this step
        $quote = $this->getSessionQuote();
        $paymentMethod = $quote->getPayment()->getMethod();
        if (empty($paymentMethod) || $paymentMethod != "cryozonic_stripe") return;

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
    }

    protected function reCreateStripeCustomer($customerStripeId)
    {
        $this->deleteStripeCustomerId($customerStripeId);
        return $this->createStripeCustomer();
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

        return $this->customerEmail = $email;
    }

    protected function getCustomerId()
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

    protected function isAVSEnabled()
    {
        return Mage::getStoreConfig('payment/cryozonic_stripe/avs');
    }

    protected function getAvsFields($card)
    {
        if (!is_array($card)) return $card; // Card is a token so AVS should have already been taken care of

        if ($this->isAVSEnabled())
        {
            $billingAddress = $this->helper->getBillingAddress($this);

            if (empty($billingAddress))
                throw new Stripe_Error("You must first enter your billing address.");
            else
            {
                $card['address_line1'] = $billingAddress['address_line1'];
                $card['address_zip'] = $billingAddress['address_zip'];
            }
        }
        return $card;
    }

    protected function performAVSChecks($charge)
    {
        if (!$this->isAVSEnabled()) return;

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
        if (is_string($params['card']) && (strpos($params['card'], 'tok_') === 0 || strpos($params['card'], 'tdsrc_') === 0))
            return $params['card'];

        try
        {
            $params['card'] = $this->getAvsFields($params['card']);

            $this->validateParams($params);

            $token = Stripe_Token::create($params);

            if (empty($token['id']))
                Mage::throwException($this->t('Sorry, this payment method can not be used at the moment. Try again later.'));

            $this->setCustomerCard($token['card']);

            return $token['id'];
        }
        catch (Stripe_InvalidRequestError $e)
        {
            $this->log($e->getMessage());
            Mage::throwException($this->t($e->getMessage()));
        }
        catch (Stripe_CardError $e)
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
        if (strstr($token,'tok_') === false && strstr($token,'tdsrc_') === false)
        {
            $params = $this->getInfoInstanceCard();
            $token = $this->createToken($params);
        }

        return $token;
    }

    public function assignData($data)
    {
        $info = $this->getInfoInstance();

        if (!empty($data['cc_saved']) && $data['cc_saved'] != 'new_card' && !$this->is3DSecureEnabled())
        {
            $card = explode(':', $data['cc_saved']);
            $info->setAdditionalInformation('token', $card[0])
                ->setCcType($card[1])
                ->setCcLast4($card[2]);
            return $this;
        }

        if (empty($data['cc_stripejs_token']) && empty($data['cc_number']))
        {
            if ($this->is3DSecureEnabled())
            {
                // The only way of getting here is for a hacker trying to bypass 3D Secure in the front end
                Mage::throwException($this->t("Could not use card: Your bank requires additional authentication."));
            }
            // Other scenarios here are triggered by OSC modules trying to prematurely save payment details
            return $this;
        }

        if ($this->isStripeJsEnabled())
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

            // If 3D secure is enabled, Stripe.js tokens are not allowed
            if (strpos($card[0], 'tok_') === 0 && $this->is3DSecureEnabled())
                Mage::throwException($this->t("Could not use card: Your bank requires additional authentication."));

            // This is called both at the card filling step and also at the final step, so add some safety measures
            $usedToken = $info->getAdditionalInformation('stripejs_token');

            if (!empty($usedToken) && $usedToken == $card[0])
                return $this;

            // What to do at the card filling step
            $params = array(
                "card" => $card[0]
            );
            $info->setAdditionalInformation('stripejs_token', $card[0])
                ->setCcType($card[1])
                ->setCcLast4($card[2]);
        }
        else
        {
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

        $is3DSecureToken = (is_string($params['card']) && strpos($params['card'], 'tdsrc_') !== false);

        // Add the card to the customer
        if ($this->saveCards && $data['cc_save'] && !$is3DSecureToken)
        {
            try
            {
                // @todo - we can pass a fingerprint as a second parameter if we are coming from the admin area
                $card = $this->addCardToCustomer($params['card']);
                $token = $card->id;
            }
            catch (Stripe_Error $e)
            {
                Mage::throwException($e->getMessage());
            }
            catch (Exception $e)
            {
                // DEPRECIATED with latest Stripe PHP library - we should never get in here.

                // We may get here if a CVC check failed, but we do not
                // error out because Stripe will not give the exact reason
                // that the card was declined. The card will error again
                // at the final step with the correct reason.
                $token = $this->createToken($params);
            }
        }
        else
            $token = $this->createToken($params);

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
        if (is_object($card) && get_class($card) == 'Stripe_Card')
        {
            $this->customerCard = array(
                "last4" => $card->last4,
                "brand" => $card->brand
            );
        }
    }

    public function addCardToCustomer($newcard, $fingerprint = null)
    {
        $customerStripeId = $this->getCustomerStripeId();
        $customer = $this->getStripeCustomer($customerStripeId);

        // Rare occation with stale test data && customerLastRetrieved < 10 mins
        if (!$customer)
            $customer = $this->reCreateStripeCustomer($customerStripeId);

        if (!$customer)
            throw new Exception("Could not save the customer's card because the customer could not be created in Stripe!");

        // @todo - Handle rare occation where AVS has been enabled after a fraudulent card has been saved and is now being reused.
        // Could potentially save the AVS state on the card's metadata when it is created.
        // if (!Mage::getStoreConfig('payment/cryozonic_stripe/avs')) ...

        if (!empty($newcard['number'])) // In the case of Stripe.js, this will not be set at all
        {
            // Check if the customer already has this card, set it as the default
            $last4 = substr($newcard['number'], -4);
            $month = $newcard['exp_month'];
            $year = $newcard['exp_year'];
            foreach ($customer->sources->data as $card)
            {
                if ($last4 == $card->last4 &&
                    $month == $card->exp_month &&
                    $year == $card->exp_year)
                {
                    $customer->default_source = $card->id;
                    $customer->save();
                    $this->setCustomerCard($card);
                    return $card;
                }
            }
        }

        // When we have a 3DS token
        if (!empty($fingerprint))
        {
            // In the case of 3D Secure, we run the risk of adding the card more than once
            foreach ($customer->sources->data as $card)
            {
                $key = $card->brand . ':' . $card->exp_month . ':' . $card->exp_year . ':' . $card->last4;
                if ($key == $fingerprint)
                {
                    $customer->default_source = $card->id;
                    $customer->save();
                    $this->setCustomerCard($card);
                    return $card;
                }
            }
        }

        if (is_array($newcard))
            $newcard["object"] = "card";

        // If the customer doesn't have the card, create it and set it as the default card
        $newcard = $this->getAvsFields($newcard);
        $createdCard = $customer->sources->create(array('source'=>$newcard));
        $customer->default_source = $createdCard->id;
        $customer->save();
        $this->setCustomerCard($createdCard);
        return $createdCard;
    }

    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment, $amount);

        if ($amount > 0)
        {
            $this->createCharge($payment, $amount, false);
        }

        return $this;
    }

    protected function getMultiCurrencyAmount($payment, $baseAmount)
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

    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);

        if ($amount > 0)
        {
            $captured = $payment->getAdditionalInformation('captured');
            $action = Mage::getStoreConfig('payment/cryozonic_stripe/payment_action');
            $depreciatedVersion = (($captured === null) && ($action == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE));

            if ($captured === false || $depreciatedVersion)
            {
                // We get in here when the store is configured in Authorize Only mode and we are capturing a payment from the admin
                $token = $payment->getTransactionId();
                if (empty($token))
                    $token = $payment->getLastTransId();

                $token = $this->helper->cleanToken($token);
                try
                {
                    $ch = Stripe_Charge::retrieve($token);
                    $finalAmount = $this->getMultiCurrencyAmount($payment, $amount);

                    $currency = $payment->getOrder()->getOrderCurrencyCode();
                    $cents = 100;
                    if ($this->isZeroDecimal($currency))
                        $cents = 1;

                    $ch->capture(array('amount' => round($finalAmount * $cents)));
                }
                catch (Exception $e)
                {
                    $this->log($e->getMessage());
                    if (Mage::app()->getStore()->isAdmin() && $this->isAuthorizationExpired($e->getMessage()) && $this->retryWithSavedCard())
                        $this->createCharge($payment, $amount, true, true);
                    else
                        Mage::throwException($e->getMessage());
                }
            }
            else
            {
                // Normal checkout payments in Authorize & Capture mode
                $this->createCharge($payment, $amount, true);
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

    protected function isZeroDecimal($currency)
    {
        return in_array(strtolower($currency), array(
            'bif', 'djf', 'jpy', 'krw', 'pyg', 'vnd', 'xaf',
            'xpf', 'clp', 'gnf', 'kmf', 'mga', 'rwf', 'vuv', 'xof'));
    }

    public function createCharge(Varien_Object $payment, $amount, $capture, $forceUseSavedCard = false)
    {
        if ($forceUseSavedCard)
        {
            $token = $this->getSavedCardFrom($payment);
            $this->customerStripeId = $this->getCustomerStripeId($payment->getOrder()->getCustomerId());

            if (!$token || !$this->customerStripeId)
                Mage::throwException('The authorization has expired and the customer has no saved cards to re-create the order.');
        }
        else
            $token = $this->getToken();

        try {
            $order = $payment->getOrder();

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
              "source" => $token,
              "description" => "Order #".$order->getRealOrderId().' by '.$order->getCustomerName(),
              "capture" => $capture
            );

            if (Mage::getStoreConfig('payment/cryozonic_stripe/receipt_email'))
                $params["receipt_email"] = $this->getCustomerEmail();

            // If this is a saved card, pass the customer id too
            if (strpos($token, 'card_') === 0 || $this->is3DSecureEnabled())
            {
                $cu = $this->getCustomerStripeId($order->getCustomerId());
                if ($cu)
                    $params["customer"] = $this->getCustomerStripeId($order->getCustomerId());
            }

            $stripeRadarMarkFraudulent = $this->shouldRadarMarkFraudulent();

            try
            {
                if ($stripeRadarMarkFraudulent)
                    $params["capture"] = false;

                $this->validateParams($params);

                $charge = Stripe_Charge::create($params);

                if ($stripeRadarMarkFraudulent)
                {
                    if (isset($charge->outcome->risk_level) && $charge->outcome->risk_level != 'normal')
                    {
                        $payment->setIsFraudDetected(true);
                        $payment->setIsTransactionPending(true);
                        $payment->setAdditionalInformation('captured', false);

                        if (!$capture)
                        {
                            $invoice = $order->prepareInvoice();
                            $invoice->register();
                            $order->addRelatedObject($invoice);
                        }
                    }
                    else
                    {
                        if ($params["capture"] != $capture)
                        {
                            $charge->capture();
                            $payment->setAdditionalInformation('captured', true);
                        }
                    }
                }
                else
                {
                    $payment->setAdditionalInformation('captured', $capture);
                }
            }
            catch (Exception $e)
            {
                // Necessary nested try-catch for the back-end
                Mage::throwException($this->t($e->getMessage()));
            }

            // Saved cards have been AVS verified when they were initially saved
            if (strpos($token, 'card_') !== 0)
                $this->performAVSChecks($charge);

            $payment->setTransactionId($charge->id);
            $payment->setIsTransactionClosed(0);

            // Set the order status according to the configuration
            $newOrderStatus = Mage::getStoreConfig('payment/cryozonic_stripe/order_status');
            if (!empty($newOrderStatus))
            {
                $order->addStatusToHistory($newOrderStatus, $this->t('Changing order status as per New Order Status configuration'));
            }

            $payment->setAdditionalInformation('address_line1_check', $charge->source->address_line1_check);
            $payment->setAdditionalInformation('address_zip_check', $charge->source->address_zip_check);
        }
        catch(Stripe_CardError $e)
        {
            $this->log($e->getMessage());
            Mage::throwException($this->t($e->getMessage()));
        }
    }

    public function validateParams($params)
    {
        if (is_array($params) && isset($params['card']) && is_array($params['card']) && empty($params['card']['number']))
            Mage::throwException("Unable to use Stripe.js, please see http://store.cryozonic.com/documentation/magento-1-stripe-payments#stripejs");
    }

    public function shouldRadarMarkFraudulent()
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
        $transactionId = $this->helper->cleanToken($transactionId);

        try {
            $cents = 100;
            if ($this->isZeroDecimal($currency))
                $cents = 1;

            $params = array(
                'amount' => round($amount * $cents)
            );
            $charge = Stripe_Charge::retrieve($transactionId);

            // This is true when an authorization has expired or when there was a refund through the Stripe account
            if (!$charge->refunded)
            {
                $charge->refund($params);

                $payment->getOrder()->addStatusToHistory(
                    Mage_Sales_Model_Order::STATE_CANCELED,
                    $this->t('Customer was refunded the amount of '). $amount
                );
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
        catch (Exception $e)
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

    protected function getCustomerStripeId($customerId = null)
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

    protected function getCustomerStripeIdByEmail($maxAge = null)
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
            $response = Stripe_Customer::create(array(
              "description" => "$customerFirstname $customerLastname",
              "email" => $customerEmail
            ));
            $response->save();

            $this->setStripeCustomerId($response->id, $customerId);

            return $this->customer = $response;
        }
        catch (Exception $e)
        {
            $this->log('Could not set up customer profile: '.$e->getMessage());
            Mage::throwException($this->t('Could not set up customer profile: ').$this->t($e->getMessage()));
        }

    }

    public function getStripeCustomer($id = null)
    {
        if ($this->customer)
            return $this->customer;

        if (empty($id))
            $id = $this->getCustomerStripeId();

        if (empty($id))
            return false;

        try
        {
            $this->customer = Stripe_Customer::retrieve($id);
            $this->updateLastRetrieved($this->customer->id);
            if (!$this->customer || ($this->customer && isset($this->customer->deleted) && $this->customer->deleted))
                return false;
            return $this->customer;
        }
        catch (Exception $e)
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
                    $customer->sources->retrieve($cardId)->delete();
                }
                catch (Exception $e)
                {
                    // @todo
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
        catch (Exception $e)
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
        catch (Exception $e)
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
        catch (Exception $e)
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

        // Saved cards not supported on IE7
        if (!$isAdmin && strpos($_SERVER['HTTP_USER_AGENT'],'MSIE 7.0') !== false)
            return null;

        return $this->listCards($customerStripeId);
    }

    private function listCards($customerStripeId, $params = array())
    {
        try
        {
            $cards = Stripe_Customer::retrieve($customerStripeId)->sources;
            if (!empty($cards))
            {
                $params['object'] = 'card';
                return $cards->all($params)->data;
            }
            else
                return null;
        }
        catch (Exception $e)
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
        return ($method == 'guest' || empty($method));
    }

    public function showSaveCardOption()
    {
        return ($this->saveCards && !$this->isGuest() && !$this->is3DSecureEnabled());
    }

    protected function hasRecurringProducts()
    {
        return $this->_hasRecurringProducts;
    }

    public function alwaysSaveCard()
    {
        return ($this->hasRecurringProducts() || $this->is3DSecureEnabled() || $this->saveCards == 2);
    }

    public function isStripeJsEnabled()
    {
        return $this->getStore()->getConfig('payment/cryozonic_stripe/stripe_js');
    }

    public function is3DSecureEnabled()
    {
        return $this->getStore()->getConfig('payment/cryozonic_stripe/three_d_secure')
            && $this->isStripeJsEnabled()
            && !Mage::app()->getStore()->isAdmin()
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
                    $amount += $item->getRowTotalInclTax() + $item->getShippingAmount();
        }
        else
        {
            $amount = $quote->getBaseGrandTotal();;
            $currency = $quote->getBaseCurrencyCode();

            foreach ($items as $item)
                if ($item->getProduct()->isRecurring())
                    $amount += $item->getBaseRowTotalInclTax() + $item->getBaseShippingAmount();
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
            $fields["amount"] = number_format($amount, 2);
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

    public function initiate3DSecureAuthentication($cardToken, $fingerprint)
    {
        $params3DS = $this->get3DSecureParams(false);

        // This is a mandatory step for 3DS
        if (strpos($cardToken, 'card_') !== 0)
        {
            $card = $this->addCardToCustomer($cardToken, $fingerprint);
            $cardToken = $card->id;
        }

        $params = array(
          "card" => $cardToken,
          "customer" => $this->getCustomerStripeId(),
          "amount" => $params3DS['amount'],
          "currency" => $params3DS['currency'],
          "return_url" => '_callback'
        );

        return Stripe_ThreeDSecure::create($params);
    }

    public function isApplePayEnabled()
    {
        return $this->isStripeJsEnabled()
            && !Mage::app()->getStore()->isAdmin();
    }

    public function getApplePayParams($encode = true)
    {
        if (!$this->isApplePayEnabled())
            return 'null';

        $quote = $this->getSessionQuote();
        if (empty($quote))
            return 'null';

        $fields = $this->getAmountCurrencyFromQuote($quote, false);
        $email = $quote->getCustomerEmail();
        $first = $quote->getCustomerFirstname();
        $last = $quote->getCustomerLastname();
        $label = "Order by $first $last <$email>";
        $countryCode = $quote->getBillingAddress()->getCountryId();

        $params = array(
            "countryCode" => $countryCode,
            "currencyCode" => $fields["currency"],
            "total" => array(
                "label" => $label,
                "amount" => $fields["amount"]
            )
        );

        // a) The billing address has not been set yet, typically because of a guest checkout with a OSC module
        // b) We are likely not on the checkout page, might be the shopping cart or another page initializing the payment method
        if (empty($countryCode) || empty($fields["currency"]) || empty($email) || empty($fields["amount"]))
            return 'null';

        if ($encode)
            return json_encode($params);

        return $params;
    }

    public function retrieveCharge($chargeId)
    {
        return Stripe_Charge::retrieve($chargeId);
    }

    // Public logging method
    public function plog($msg)
    {
        return $this->log($msg);
    }
}
?>