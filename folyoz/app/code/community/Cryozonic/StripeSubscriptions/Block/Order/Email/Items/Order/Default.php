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

class Cryozonic_StripeSubscriptions_Block_Order_Email_Items_Order_Default extends Mage_Sales_Block_Order_Email_Items_Order_Default
{
    public function getItemOptions()
    {
        $result = parent::getItemOptions();

        // Magento bugfix: This scenario has been created by extended subscription details displaying in emails
        foreach ($result as &$option)
            if (is_array($option['value']))
                $option['value'] = implode("\n", $option['value']);

        return $result;
    }
}