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

class Cryozonic_Stripe_SecureController extends Mage_Core_Controller_Front_Action
{
    protected function crashWithError($msg)
    {
        $this->getResponse()->setHeader('HTTP/1.0','400', true);
        $this->getResponse()->setBody(json_encode(array('error' => $msg)));
    }

    public function create3DSecureSource($cardToken)
    {
        $stripe = Mage::getModel('cryozonic_stripe/standard');
        $params3DS = $stripe->get3DSecureParams(false);

        return \Stripe\Source::create(array(
            "amount" => $params3DS['amount'],
            "currency" => $params3DS['currency'],
            "type" => "three_d_secure",
            "three_d_secure" => array(
                "card" => $cardToken,
            ),
            "redirect" => array(
                "return_url" => Mage::getUrl('cryozonic_stripe/return')
            ),
        ));
    }

    public function indexAction()
    {
        $token = $this->getRequest()->getParam('token', null);
        $fingerprint = $this->getRequest()->getParam('fingerprint', null);

        try
        {
            $source = $this->create3DSecureSource($token);

            if (empty($source) || !isset($source->id))
                throw new Exception("Sorry, we could not initiate a card authentication with your bank.");

            $session = Mage::getSingleton('core/session');
            if (!empty($source->redirect->url))
                $session->setRedirectUrl($source->redirect->url);
            else
                $session->setRedirectUrl(null);
            $session->setClientSecret($source->client_secret);

            $data = array(
                'id' => $source->id,
                'status' => $source->status
            );
            $this->getResponse()->setBody(json_encode($data));
        }
        catch (\Stripe\Error\Card $e)
        {
            $this->crashWithError($e->getMessage());
        }
        catch (\Stripe\Error $e)
        {
            $this->crashWithError($e->getMessage());
        }
        catch (\Exception $e)
        {
            $this->crashWithError($e->getMessage());
        }
    }
}
