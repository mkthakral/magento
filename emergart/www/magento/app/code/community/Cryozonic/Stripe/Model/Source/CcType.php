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

class Cryozonic_Stripe_Model_Source_CcType
{
    public function toOptionArray()
    {
        $options =  array();

        $_types = Mage::getConfig()->getNode('global/payment/cryozonic_stripe/cc_types')->asArray();

        uasort($_types, array('Mage_Payment_Model_Config', 'compareCcTypes'));

        foreach ($_types as $data)
        {
            if (isset($data['code']) && isset($data['name']))
            {
                $options[] = array(
                   'value' => $data['code'],
                   'label' => $data['name']
                );
            }
        }

        return $options;
    }
}
