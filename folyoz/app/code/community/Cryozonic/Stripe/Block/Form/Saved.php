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

class Cryozonic_Stripe_Block_Form_Saved extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('cryozonic/stripe/form/saved.phtml');
        $this->stripe = Mage::getModel('cryozonic_stripe/standard');
    }

    public function getCustomerCards()
    {
        return $this->stripe->getCustomerCards();
    }

    public function isReusableSource($source)
    {
        // SEPA Direct Debit cannot be used for arbitrary amounts in the admin, it must be the exact
        // amount agreed with the bank.
        return false;//$source->object == 'source' && $source->usage == 'reusable' && $source->type == 'sepa_debit';
    }

    public function formatSourceName($source)
    {
        return "SEPA Direct Debit Ref. " . $source->sepa_debit->mandate_reference;
    }
}
