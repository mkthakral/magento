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

class Cryozonic_Stripe_Helper_Data extends Mage_Payment_Helper_Data
{
    public function __construct()
    {
        $this->cache = Mage::app()->getCache();
    }

    public function getBillingAddress($quote = null)
    {
        $quote = $this->getSessionQuote();

        if (!empty($quote) && $quote->getBillingAddress())
            return $quote->getBillingAddress();

        return null;
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

    public function getSanitizedBillingInfo()
    {
        $billingAddress = $this->getBillingAddress();
        if (!$billingAddress) return null;

        $quote = $this->getSessionQuote();

        $postcode = $billingAddress->getData('postcode');
        $email = $billingAddress->getEmail();
        $name = $billingAddress->getName();
        $city = $billingAddress->getCity();
        $country = $billingAddress->getCountryId();
        $phone = $billingAddress->getTelephone();
        $state = $billingAddress->getRegion();

        if (empty($name) && $quote->getCustomerFirstname())
        {
            $name = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();
        }

        if (empty($email))
        {
            if (Mage::getSingleton('customer/session')->isLoggedIn())
            {
                $customer = Mage::getSingleton('customer/session')->getCustomer();
                $email = $customer->getEmail();
            }
            else
            {
                if ($quote)
                    $email = $quote->getCustomerEmail();
            }
        }

        $line1 = null;
        $line2 = null;
        $street = explode('\n', $billingAddress->getData('street'));
        if (!empty($street) && is_array($street) && count($street))
        {
            $line1 = $street[0];

            if (!empty($street[1]))
                $line2 = $street[1];
        }

        // Sanitization
        $line1 = preg_replace("/\r|\n/", " ", $line1);
        $line1 = addslashes($line1);
        if (empty($line1))
            $line1 = null;

        return array(
            'name' => $name,
            'line1' => $line1,
            'line2' => $line2,
            'postcode' => $postcode,
            'email' => $email,
            'city' => $city,
            'phone' => $phone,
            'state' => $state,
            'country' => $country
        );
    }

    // Removes decorative strings that Magento adds to the transaction ID
    public function cleanToken($token)
    {
        return preg_replace('/-.*$/', '', $token);
    }

    public function cancelOrder($orderId, $isIncremental = false)
    {
        try
        {
            if (!$orderId)
                throw new Exception("Could not load order ID from session data.");

            if ($isIncremental)
                $order = Mage::getModel('sales/order')->load($orderId, 'increment_id');
            else
                $order = Mage::getModel('sales/order')->load($orderId);

            if (!$order)
                throw new Exception("Could not load order with ID $orderId.");

            $this->cancelOrCloseOrder($order);
        }
        catch (Exception $e)
        {
            Mage::logException($e);
        }
    }

    public function isZeroDecimal($currency)
    {
        return in_array(strtolower($currency), array(
            'bif', 'djf', 'jpy', 'krw', 'pyg', 'vnd', 'xaf',
            'xpf', 'clp', 'gnf', 'kmf', 'mga', 'rwf', 'vuv', 'xof'));
    }

    public function cancelOrCloseOrder($order)
    {
        $cancelled = false;

        $transaction = Mage::getModel('core/resource_transaction');

        // When in Authorize & Capture, uncaptured invoices exist, so we should cancel them first
        $service = Mage::getModel('sales/service_order', $order);

        foreach($order->getInvoiceCollection() as $invoice)
        {
            if ($invoice->canCancel())
            {
                $invoice->cancel();
                $transaction->addObject($invoice);
                $cancelled = true;
            }
        }

        // When all invoices have been canceled, the order can be canceled
        if ($order->canCancel())
        {
            $order->cancel();
            $transaction->addObject($order);
            $cancelled = true;
        }

        $transaction->save();

        return $cancelled;
    }

    public function captureOrder($order)
    {
        foreach($order->getInvoiceCollection() as $invoice)
        {
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->capture();
            $invoice->save();
        }
    }

    public function invoiceOrder($order, $transactionId = null)
    {
        $transaction = Mage::getModel('core/resource_transaction');

        // This will kick in with "Authorize Only" mode, but not with "Authorize & Capture"
        if ($order->canInvoice())
        {
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);

            if ($transactionId)
                $invoice->setTransactionId($transactionId);

            $invoice->register();

            $transaction->addObject($invoice)
                        ->addObject($order)
                        ->save();

            // try
            // {
            //     $invoice->sendEmail(true);
            // }
            // catch (Exception $e)
            // {
            //     Mage::logException($e);
            // }
        }
        // Invoices have already been generated with Authorize & Capture, but have not actually been captured because
        // the source is not chargeable yet. These should have a pending status.
        else
        {
            foreach($order->getInvoiceCollection() as $invoice)
            {
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $transaction->addObject($invoice);
            }

            $transaction->addObject($order)->save();
        }

        return $invoice;
    }

    // Pending orders are the ones that were placed with an asynchronous payment method, such as SOFORT or SEPA Direct Debit,
    // which may finalize the charge after several days or weeks
    public function invoicePendingOrder($order, $captureCase = Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE, $transactionId = null)
    {
        if (!$order->canInvoice())
            throw new Exception("Order #" . $order->getIncrementId() . " cannot be invoiced.");

        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

        $invoice->setRequestedCaptureCase($captureCase);

        if ($transactionId)
            $invoice->setTransactionId($transactionId);

        $invoice->register();

        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($order);

        $transactionSave->save();

        return $invoice;
    }

    public function getCachedObject($key)
    {
        $object = $this->cache->load($key);
        if ($object)
            return unserialize($object);
        else
            return null;
    }

    public function cacheObject($object, $key, $duration)
    {
        $this->cache->save(serialize($object), $key, array('cryozonic_stripe_general_cache'), $duration);
    }

    public function getRefundIdFrom($charge)
    {
        $lastRefundDate = 0;
        $refundId = null;

        foreach ($charge->refunds->data as $refund)
        {
            // There might be multiple refunds, and we are looking for the most recent one
            if ($refund->created > $lastRefundDate)
            {
                $lastRefundDate = $refund->created;
                $refundId = $refund->id;
            }
        }

        return $refundId;
    }
}
