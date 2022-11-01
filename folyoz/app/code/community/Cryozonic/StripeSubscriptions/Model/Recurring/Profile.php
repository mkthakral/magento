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

class Cryozonic_StripeSubscriptions_Model_Recurring_Profile extends Mage_Sales_Model_Recurring_Profile
{
    public function isValid()
    {
    	parent::isValid();
    	unset($this->_errors['trial_billing_amount']);
    	unset($this->_errors['trial_period_max_cycles']);
    	return empty($this->_errors);
    }
}