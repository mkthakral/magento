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

class Cryozonic_StripeSubscriptions_Block_Checkout_Total_Nominal extends Mage_Checkout_Block_Total_Nominal
{
    protected $_template = 'cryozonic_stripesubscriptions/checkout/total/nominal.phtml';

    protected function getCouponCode()
    {
        if ($this->couponCode) return $this->couponCode;

        $this->couponCode = Mage::getSingleton('checkout/session')->getQuote()->getCouponCode();

        return $this->couponCode;
    }

    private function getSubscriptionPrice($subscription)
    {
        $payment = 0;
        $taxable_fields = array('Regular Payment', 'Shipping');

        foreach ($this->getTotalItemDetails($subscription) as $key => $row)
        {
            $label = $this->getItemDetailsRowLabel($row);
            if (in_array($label, $taxable_fields))
                $payment += $this->getItemDetailsRowAmount($row);
        }

        return $payment;
    }

    private function convertMultiCurrency($discount)
    {
        if (empty($discount) || !is_numeric($discount)) return 0;

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $rate = $quote->getStoreToQuoteRate();

        return $discount * $rate;
    }

    public function getDiscountAmount($subscription)
    {
        $discountAmount = 0;

        try
        {
            $regularPayment = $this->getSubscriptionPrice($subscription);

            $couponCode = $this->getCouponCode();
            if ($couponCode)
                $discountAmount = Mage::helper('cryozonic_stripesubscriptions')->getDiscountAmountFor($couponCode, $regularPayment);
        }
        catch (\Exception $e)
        {
            Mage::log('Stripe Subscriptions - ' . $e->getMessage());
        }

        return $discountAmount;
    }
}