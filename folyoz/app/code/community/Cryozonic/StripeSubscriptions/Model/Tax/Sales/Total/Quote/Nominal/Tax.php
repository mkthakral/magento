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

class Cryozonic_StripeSubscriptions_Model_Tax_Sales_Total_Quote_Nominal_Tax
    extends Mage_Tax_Model_Sales_Total_Quote_Nominal_Tax
{
    protected function includeShippingInDiscountAmount($item)
    {
        // If the product has both a discount and a shipping cost, include the shipping cost in the discount
        // See http://store.cryozonic.com/documentation/magento-1-stripe-subscriptions#stripe-subscriptions-coupons
        if ($item->getDiscountPercent() > 0 && $item->getShippingAmount() > 0)
        {
            $quote = Mage::helper('cryozonic_stripesubscriptions')->getSessionQuote();
            $couponCode = $quote->getCouponCode();
            if (!empty($couponCode))
            {
                $discountAmount = Mage::helper('cryozonic_stripesubscriptions')->getDiscountAmountFor($couponCode, $item->getTaxableAmount());
                $baseDiscountAmount = Mage::helper('cryozonic_stripesubscriptions')->getDiscountAmountFor($couponCode, $item->getBaseTaxableAmount());
                $item->setDiscountAmount($discountAmount);
                $item->setBaseDiscountAmount($baseDiscountAmount);
            }
        }
    }

    protected function _aggregateTaxPerRate(
        $item, $rate, &$taxGroups, $taxId = null, $recalculateRowTotalInclTax = false
    )
    {
        $this->includeShippingInDiscountAmount($item);
        return parent::_aggregateTaxPerRate($item, $rate, $taxGroups, $taxId, $recalculateRowTotalInclTax);
    }
}