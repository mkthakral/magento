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

class Cryozonic_StripeSubscriptions_Model_Source_Invoice
{
    const AUTOMATIC = 0;
    const MANUALLY  = 1;

    public function toOptionArray()
    {
        return array(
            array(
                'value' => Cryozonic_StripeSubscriptions_Model_Source_Invoice::AUTOMATIC,
                'label' => Mage::helper('cryozonic_stripe')->__('Automatically')
            ),
            array(
                'value' => Cryozonic_StripeSubscriptions_Model_Source_Invoice::MANUALLY,
                'label' => Mage::helper('cryozonic_stripe')->__('Manually')
            ),
        );
    }
}
