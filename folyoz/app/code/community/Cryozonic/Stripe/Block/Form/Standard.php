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

class Cryozonic_Stripe_Block_Form_Standard extends Mage_Payment_Block_Form_Cc
{
    public $stripe;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('cryozonic/stripe/form/standard.phtml');

        $this->stripe = Mage::getModel('cryozonic_stripe/standard');

        $this->cardAutoDetect = $this->stripe->store->getConfig('payment/cryozonic_stripe/card_autodetect');
    }

    public function autoDetectCard()
    {
        return $this->cardAutoDetect && $this->cardAutoDetect > 0;
    }

    public function showAcceptedCardTypes()
    {
        return $this->cardAutoDetect == 1;
    }

    public function getOnCardNumberChangedAnimation()
    {
        switch ($this->cardAutoDetect)
        {
            case 1: return 'onCardNumberChangedFade';
            case 2: return 'onCardNumberChangedSlide';
            default: return '';
        }
    }

    public function getOnKeyUpCardNumber()
    {
        if ($this->autoDetectCard())
        {
            $callback = $this->getOnCardNumberChangedAnimation();
            return "onkeyup=\"$callback(this)\"";
        }

        return '';
    }

    public function getAcceptedCardTypes()
    {
        $types = Mage::getConfig()->getNode('global/payment/cryozonic_stripe/cc_types')->asArray();
        $acceptedTypes = $this->stripe->store->getConfig('payment/cryozonic_stripe/cctypes');

        uasort($types, array('Mage_Payment_Model_Config', 'compareCcTypes'));

        foreach ($types as $data)
        {
            if (empty($acceptedTypes)) // Slide animation, returns all possible types
            {
                $cardTypes[$data['code']] = $data['name'];
            }
            else if (isset($data['code']) && isset($data['name']) && strstr($acceptedTypes, $data['code'])) // Fade animation, takes into account selected types
            {
                $cardTypes[$data['code']] = $data['name'];
            }
        }

        return $cardTypes;
    }
}
