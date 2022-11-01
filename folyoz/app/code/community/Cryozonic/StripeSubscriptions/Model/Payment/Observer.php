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

class Cryozonic_StripeSubscriptions_Model_Payment_Observer extends Mage_Payment_Model_Observer
{
    /**
     * Fixes a guest checkout crash specific to the OneStepCheckout.com module
     * If you are not using OSC, this is redundant
     */
    public function salesOrderBeforeSave($observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (!$order->getPayment())
            return $this;

        return parent::salesOrderBeforeSave($observer);
    }
}