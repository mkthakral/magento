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
 * @package    Cryozonic_StripeSubscriptions
 * @copyright  Copyright (c) Cryozonic Ltd (http://cryozonic.com)
 */

class Cryozonic_StripeSubscriptions_Helper_Data extends Mage_Payment_Helper_Data
{
    private function convertMultiCurrency($discount)
    {
        if (empty($discount) || !is_numeric($discount)) return 0;

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $rate = $quote->getStoreToQuoteRate();

        return $discount * $rate;
    }

    public function getSessionQuote()
    {
        // If we are in the back office
        if (Mage::app()->getStore()->isAdmin())
        {
            return Mage::getSingleton('adminhtml/sales_order_create')->getQuote();
        }
        // If we are a user
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    public function getDiscountAmountFor($couponCode, $regularPayment)
    {
        $coupon = Mage::getModel('salesrule/coupon')->load($couponCode, 'code');
        $rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
        $discount = $rule->getDiscountAmount();
        $action = $rule->getSimpleAction();

        if ($action == 'by_percent')
        {
            return $regularPayment * $discount / 100;
        }
        else if ($action == 'by_fixed')
        {
            return $this->convertMultiCurrency($discount);
        }
        else
        {
            // Other magento discount rules are not supported
            return 0;
        }
    }

    // This is shared between the email totals and the admin totals
    // Magento doesn't add initial fees for us when it comes to recurring profiles :-(
    public function addInitialFeeTo(&$totalsBlock)
    {
        if (isset($totalsBlock->_totals['initial'])) return;

        $order = $totalsBlock->getSource();

        // Don't add an initial fee in the email if this is a recurring order
        if ($order->getRemoteIp() == 'stripe.com') return;

        $items = $order->getAllItems();

        $this->_addInitialFeeTo($totalsBlock, $items, $order);
    }

    public function addInitialFeeToInvoice(&$totalsBlock)
    {
        if (isset($totalsBlock->_totals['initial'])) return;

        $invoice = $totalsBlock->getSource();
        $order = $invoice->getOrder();

        // Don't add an initial fee in the email if this is a recurring order
        if ($order->getRemoteIp() == 'stripe.com') return;

        $items = $invoice->getAllItems();

        $this->_addInitialFeeTo($totalsBlock, $items, $order);
    }

    private function _addInitialFeeTo(&$totalsBlock, $items, $order)
    {
        foreach ($items as $item)
        {
            $product = $item->getProduct(); // Magento 1.9
            if (!$product)
                $product = Mage::getModel('catalog/product')->load($item->getProductId());

            if ($product->getIsRecurring())
            {
                $profile = $product->getRecurringProfile();
                if ($profile)
                {
                    // Add the initial fee as a total
                    $initAmount = $profile['init_amount'] * $order->getStoreToOrderRate();
                    if (is_numeric($initAmount) && $initAmount > 0)
                    {
                        $fee = new Varien_Object(array(
                            'code'  => 'initial',
                            'field' => 'initial_amount',
                            'value' => $initAmount,
                            'base_value'=> $profile['init_amount'],
                            'label' => $totalsBlock->__('Initial Fee')
                        ));

                        if ($totalsBlock->getTotal('shipping'))
                            $totalsBlock->addTotalBefore($fee, 'shipping');
                        elseif ($totalsBlock->getTotal('tax'))
                            $totalsBlock->addTotalBefore($fee, 'tax');
                        else
                            $totalsBlock->addTotalBefore($fee, 'subtotal');
                    }
                }
            }
        }
    }

    public function getInitialFeeFor($productId)
    {
        $product = Mage::getModel('catalog/product')->load($productId);
        if (empty($product)) return 0;

        $profile = $product->getRecurringProfile();
        if (empty($profile) || empty($profile['init_amount']) || !is_numeric($profile['init_amount'])) return 0;

        return $profile['init_amount'];
    }

    public function hasRecurringProducts($items)
    {
        foreach ($items as $item)
        {
            $product = $item->getProduct(); // Magento 1.9
            if (!$product)
                $product = Mage::getModel('catalog/product')->load($item->getProductId());

            if ($product->getIsRecurring())
                return true;
        }

        return false;
    }

    public function isRecurringOrder($order)
    {
        return ($order->getRemoteIp() == 'stripe.com');
    }

    public function invoice($order)
    {
        // This will kick in when invoices have already been generated, as in the
        // case when "Email Copy of Invoice" is configured with Stripe Payments
        foreach($order->getInvoiceCollection() as $invoice)
            return $invoice;

        // If no invoices exist, create one
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

        // In an odd reported case, Magento does not clone the tax figures correctly on the generated invoice, thus:
        $orderItems = $order->getAllItems();
        $invoiceItems = $invoice->getAllItems();

        foreach ($invoiceItems as $invoiceItem)
        {
            foreach ($orderItems as $orderItem)
            {
                if ($invoiceItem->getSku() == $orderItem->getSku())
                {
                    $invoiceItem->setTaxAmount($orderItem->getTaxAmount());
                    $invoiceItem->setBaseTaxAmount($orderItem->getBaseTaxAmount());
                    $invoiceItem->setTaxPercent($orderItem->getTaxPercent());
                }
            }
        }

        $invoice->setTaxAmount($order->getTaxAmount());
        $invoice->setBaseTaxAmount($order->getBaseTaxAmount());
        // end of tax figure cloning

        $invoice->setSubtotal($order->getSubtotal());
        $invoice->setBaseSubtotal($order->getBaseSubtotal());
        $invoice->setGrandTotal($order->getGrandTotal());
        $invoice->setBaseGrandTotal($order->getBaseGrandTotal());
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $invoice->capture();
        $invoice->register();
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();
        return $invoice;
    }

    public function setCustomerGroup($groupId, $customerId = null)
    {
        if (is_numeric($groupId) && $groupId > 0)
        {
            try
            {
                // This works only from the front-end. Subscriptions cannot be created from the back office.
                if (is_numeric($customerId) && $customerId > 0)
                    $magentoCustomer = Mage::getModel('customer/customer')->load($customerId);
                else
                    $magentoCustomer = Mage::getSingleton('customer/session')->getCustomer();

                if ($magentoCustomer && $magentoCustomer->getEmail()) // Email is not available on guest checkout registrations
                {
                    $magentoCustomer->setGroupId($groupId);
                    $magentoCustomer->save();
                }
            }
            catch (\Exception $e)
            {
                $this->log('Could not set customer group: '.$e->getMessage());
            }
        }
    }

    public function isZeroDecimal($currency)
    {
        return in_array(strtolower($currency), array(
            'bif', 'djf', 'jpy', 'krw', 'pyg', 'vnd', 'xaf',
            'xpf', 'clp', 'gnf', 'kmf', 'mga', 'rwf', 'vuv', 'xof'));
    }

    public function requireGuestLogin()
    {
        if (isset($this->guestLoginChecked))
            return;
        else
            $this->guestLoginChecked = 1;

        $required = Mage::getStoreConfig('payment/cryozonic_stripesubscriptions/guestlogin');
        if (!$required) return;

        $session = Mage::getSingleton('customer/session');
        if ($session->isLoggedIn()) return;

        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
        $session->setBeforeAuthUrl($currentUrl);
        Mage::getSingleton('core/session')->addError($this->__("You must be logged in before you can buy a subscription."));
        Mage::app()->getResponse()->setRedirect(Mage::getUrl("customer/account/login"));
    }
}
