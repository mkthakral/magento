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

class Cryozonic_StripeSubscriptions_Model_Tax_Sales_Total_Quote_Nominal_Subtotal
    extends Mage_Tax_Model_Sales_Total_Quote_Nominal_Subtotal
{
    private function includeShippingInTaxableAmount($item)
    {
        // If the product has both a discount and a shipping cost, include the shipping cost in the taxable amount
        // See http://store.cryozonic.com/documentation/magento-1-stripe-subscriptions#stripe-subscriptions-coupons
        $quote = Mage::helper('cryozonic_stripesubscriptions')->getSessionQuote();

        $address = $quote->getShippingAddress();
        $shippingMethod = $address->getShippingMethod();
        $address->requestShippingRates($item);
        $baseShippingAmount = $item->getBaseShippingAmount();
        if ($baseShippingAmount)
        {
            $item->setShippingAmount($address->getQuote()->getStore()->convertPrice($baseShippingAmount, false));
            $item->setTaxableAmount($item->getTaxableAmount() + $item->getShippingAmount());
            $item->setBaseTaxableAmount($item->getBaseTaxableAmount() + $item->getBaseShippingAmount());
        }
    }

    private function includeInitialFeeInTaxableAmount($item)
    {
        $quote = Mage::helper('cryozonic_stripesubscriptions')->getSessionQuote();

        $baseToQuoteRate = $quote->getBaseToQuoteRate();

        $initialFee = $item->getRecurringInitialFee();

        if (empty($initialFee))
            return;

        $baseInitialFee = round($initialFee / $baseToQuoteRate, 2);

        $item->setTaxableAmount($item->getTaxableAmount() + $initialFee);
        $item->setBaseTaxableAmount($item->getBaseTaxableAmount() + $baseInitialFee);
    }

    protected function _totalBaseCalculation($item, $request)
    {
        parent::_totalBaseCalculation($item, $request);

        if ($item->getProduct()->getIsRecurring())
        {
            if (!$item->getProduct()->isVirtual())
                $this->includeShippingInTaxableAmount($item);

            if ($item->getRecurringInitialFee())
                $this->includeInitialFeeInTaxableAmount($item);
        }

        return $this;
    }
}