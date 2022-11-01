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

class Cryozonic_Stripe_Block_Payment_Info extends Mage_Payment_Block_Info
{
    public $charge;

	protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('cryozonic/stripe/payment/info/default.phtml');
        $this->helper = Mage::helper('cryozonic_stripe');
    }

    public function shouldDisplayStripeSection()
    {
        return ($this->getMethod()->getCode() == 'cryozonic_stripe');
    }

    public function getStreetCheck()
    {
        $check = $this->getInfo()->getAdditionalInformation('address_line1_check');

        if (empty($check))
            return 'unchecked';

        return $check;
    }

    public function getZipCheck()
    {
        $check = $this->getInfo()->getAdditionalInformation('address_zip_check');

        if (empty($check))
            return 'unchecked';

        return $check;
    }

    public function getRadarRisk()
    {
        $charge = $this->getCharge();

        if (isset($charge->outcome->risk_level))
            return $charge->outcome->risk_level;

        return 'Unchecked';
    }

    public function getCharge()
    {
        if (isset($this->charge))
            return $this->charge;

        $stripe = Mage::getModel('cryozonic_stripe/standard');

        try
        {
            $token = $this->helper->cleanToken($this->getMethod()->getInfoInstance()->getLastTransId());
            $this->charge = $stripe->retrieveCharge($token);
        }
        catch (Stripe_Error $e)
        {
            $stripe->plog($e->getMessage());
            return null;
        }
        catch (Exception $e)
        {
            $stripe->plog($e->getMessage());
            return null;
        }

        return $this->charge;
    }

    public function getCaptured()
    {
        $charge = $this->getCharge();

        if (isset($charge->captured) && $charge->captured == 1)
            return "Yes";

        return 'No';
    }

    public function getRefunded()
    {
        $charge = $this->getCharge();

        if (isset($charge->amount_refunded) && $charge->amount_refunded > 0)
            return $charge->amount_refunded;

        return 'No';
    }

    public function getCustomerId()
    {
        $charge = $this->getCharge();

        if (isset($charge->customer) && !empty($charge->customer))
            return $charge->customer;

        return null;
    }

    public function getPaymentId()
    {
        $charge = $this->getCharge();

        if (isset($charge->id))
            return $charge->id;

        return null;
    }
}