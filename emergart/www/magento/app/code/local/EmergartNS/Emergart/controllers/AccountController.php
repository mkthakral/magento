<?php
/**
 * Customer account controller
 */
require_once 'Mage/Customer/controllers/AccountController.php';

class EmergartNS_Emergart_AccountController extends Mage_Customer_AccountController {

 public function logoutAction()
        {
             $session = $this->_getSession();
        $session->logout()->renewSession();

        
            $url = Mage::getBaseUrl();
	    $this->_redirectUrl($url);
        }
}
