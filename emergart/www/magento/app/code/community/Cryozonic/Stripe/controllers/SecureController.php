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

    public function indexAction()
    {
        $stripe = Mage::getModel('cryozonic_stripe/standard');
        $token = $this->getRequest()->getParam('token', null);
        $fingerprint = $this->getRequest()->getParam('fingerprint', null);

        try
        {
            $response = $stripe->initiate3DSecureAuthentication($token, $fingerprint);
            if (empty($response) || !isset($response->id))
                throw new Exception("Sorry, we could not initiate a card authentication with your bank.");

            $data = array(
                'id' => $response->id,
                'status' => $response->status,
                'redirect_url' => $response->redirect_url
            );
            $this->getResponse()->setBody(json_encode($data));
        }
        catch (Exception $e)
        {
            $this->crashWithError($e->getMessage());
        }
    }
}
