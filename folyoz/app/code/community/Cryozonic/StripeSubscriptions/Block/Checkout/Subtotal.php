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

class Cryozonic_StripeSubscriptions_Block_Checkout_Subtotal extends Mage_Tax_Block_Checkout_Subtotal
{
    protected $_template = 'cryozonic_stripesubscriptions/tax/checkout/subtotal.phtml';

    public function showSubtotal()
    {
        $grandTotal = Mage::helper('checkout')->getQuote()->getGrandTotal();

        if ($grandTotal <= 0)
            return !Mage::getStoreConfig("payment/cryozonic_stripesubscriptions/fix_grand_total");

        return $this->getTotal()->getValue() > 0;
    }
}
