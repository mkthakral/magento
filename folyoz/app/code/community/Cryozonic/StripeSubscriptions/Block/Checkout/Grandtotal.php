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

class Cryozonic_StripeSubscriptions_Block_Checkout_Grandtotal extends Mage_Tax_Block_Checkout_Grandtotal
{
    protected $_template = 'cryozonic_stripesubscriptions/tax/checkout/grandtotal.phtml';

    public function showGrandTotal()
    {
        $hideGrandTotal = Mage::getStoreConfig("payment/cryozonic_stripesubscriptions/fix_grand_total");

        if ($hideGrandTotal && $this->getTotalExclTax() <= 0)
            return false;

        return $this->getTotalExclTax() >= 0;
    }
}
