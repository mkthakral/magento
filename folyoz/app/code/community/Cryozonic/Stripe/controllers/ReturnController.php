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

class Cryozonic_Stripe_ReturnController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $this->stripe = Mage::getModel('cryozonic_stripe/standard');

        $sourceId = $this->getRequest()->getParam('source', null);
        $clientSecret = $this->getRequest()->getParam('client_secret', null);

        // Missing parameters, this should never happen under normal circumstances
        if (!$sourceId || !$clientSecret)
        {
            Mage::getSingleton('core/session')->addError($this->__('Something has gone wrong with your payment. Please contact us.'));
            $this->norouteAction();
            return;
        }

        $session = Mage::getSingleton('core/session');
        $sessionClientSecret = $session->getClientSecret();

        // Security, the error message is a bit confusing on purpose
        if ($clientSecret != $sessionClientSecret)
        {
            Mage::getSingleton('core/session')->addError($this->__('Your session has expired.'));
            $this->norouteAction();
            return;
        }

        // Retrieve source
        try
        {
            $source = \Stripe\Source::retrieve($sourceId);
            if (!$source || !isset($source->id))
                throw new Exception("The source with ID $sourceId could not be retrieved from Stripe");
        }
        catch (Exception $e)
        {
            Mage::logException($e);
            Mage::getSingleton('core/session')->addError($this->__('Could not retrieve payment details. Please contact us.'));
            $this->norouteAction();
            return;
        }

        if ($source->status == 'chargeable' || $source->status == 'pending' || $source->status == 'consumed')
        {
            $this->_redirect('checkout/onepage/success', array('_secure' => true));
            return;
        }

        if ($source->status == 'failed' || $source->status == 'canceled')
        {
            $orderId = $session->getReservedOrderId();
            Mage::helper('cryozonic_stripe')->cancelOrder($orderId, true);
        }

        // Attempt to resurrect the quote of the placed order
        if ($session->getQuoteId())
        {
            $quote = Mage::getModel('sales/quote')->load($session->getQuoteId());
            $checkoutSession = Mage::getSingleton('checkout/session');
            $quote->setStore(Mage::app()->getStore())
                ->setStoreId(Mage::app()->getStore()->getId())
                ->setIsActive(1)
                ->setReservedOrderId(null)
                ->collectTotals()
                ->save();
            $checkoutSession->replaceQuote($quote);
            $checkoutSession->setCartWasUpdated(true);
        }

        // Redirect to the shopping cart with an error
        Mage::getSingleton('core/session')->addError(Mage::helper('cryozonic_stripe')->__('The payment was not authorized.'));
        $this->_redirect('checkout/cart', array('_secure' => true));
    }
}
