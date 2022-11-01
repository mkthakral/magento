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

class Cryozonic_StripeSubscriptions_Model_Source_Groups
{
    public function toOptionArray()
    {
        $customerGroupModel = new Mage_Customer_Model_Group();
        $groups  = $customerGroupModel->getCollection()->toOptionHash();
        $customerGroups['none'] = array(
            'value' => 'none',
            'label' => '-- None --'
            );

        foreach ($groups as $key => $value){
            if ($key > 0)
            {
                $customerGroups[$key] = array(
                    'value' => $key,
                    'label' => $value
                );
            }
        }

        return $customerGroups;
    }
}
