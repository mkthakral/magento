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

class Cryozonic_StripeSubscriptions_Model_Source_Configurable extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    const DROPDOWN   = 1;
    const RADIO      = 2;
    const CHECKBOX   = 3;

    public function toOptionArray()
    {
        return array(
            array(
                'value' => Cryozonic_StripeSubscriptions_Model_Source_Configurable::DROPDOWN,
                'label' => Mage::helper('cryozonic_stripesubscriptions')->__('Dropdown (default)')
            ),
            array(
                'value' => Cryozonic_StripeSubscriptions_Model_Source_Configurable::RADIO,
                'label' => Mage::helper('cryozonic_stripesubscriptions')->__('Radio Buttons')
            ),
            array(
                'value' => Cryozonic_StripeSubscriptions_Model_Source_Configurable::CHECKBOX,
                'label' => Mage::helper('cryozonic_stripesubscriptions')->__('Checkbox')
            ),
        );
    }

    public function getAllOptions()
    {
        $useConfigSettings = array('value' => 0, 'label' => Mage::helper('cryozonic_stripesubscriptions')->__('Use Config Settings'));
        return array_merge(array($useConfigSettings), $this->toOptionArray());
    }
}
