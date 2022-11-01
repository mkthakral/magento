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

class Cryozonic_Stripe_Block_Form_StripeJs extends Mage_Core_Block_Template
{
    public $billingInfo;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('cryozonic/stripe/form/stripejs.phtml');
        $this->stripe = Mage::getModel('cryozonic_stripe/standard');
        $this->billingInfo = Mage::helper('cryozonic_stripe')->getSanitizedBillingInfo();
    }

    public function getPublishableKey()
    {
        $mode = $this->stripe->store->getConfig('payment/cryozonic_stripe/stripe_mode');
        $path = "payment/cryozonic_stripe/stripe_{$mode}_pk";
        return trim($this->stripe->store->getConfig($path));
    }

    public function hasBillingAddress()
    {
        return isset($this->billingInfo) && !empty($this->billingInfo);
    }
}
