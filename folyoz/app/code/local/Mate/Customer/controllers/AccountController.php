<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Mage
 * @package     Mage_Customer
 * @copyright  Copyright (c) 2006-2014 X.commerce, Inc. (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer account controller
 *
 * @category   Mage
 * @package    Mage_Customer
 * @author      Magento Core Team <core@magentocommerce.com>
 */

 require_once("Mage/Customer/controllers/AccountController.php");
 class  Mate_Customer_AccountController extends Mage_Customer_AccountController 

{
    /**
     * Default customer account page
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
		
		if($this->getRequest()->isPost() and trim($this->getRequest()->getParam('message')) != ""){
			
			$customer = Mage::getSingleton('customer/session')->getCustomer();
			if($customer->getGroupId() == 1) {
				$mailSubject = 'Query form artist';
			} else {
				$mailSubject = 'Query form director';
			}
			$message = "
						<div><strong>Name: </strong>".$customer->getName()."</div>
						<div><strong>Email: </strong>".$customer->getEmail()."</div>
						<div><strong>Query: </strong>".$this->getRequest()->getParam('message')."</div>
			";
			$from_email = $customer->getEmail();
			$mail = Mage::getModel('core/email');
			//$mail->setFromName('Folyoz');
			
			$email = Mage::getStoreConfig('trans_email/ident_custom1/email');
			$mail = Mage::getModel('core/email');
			$mail->setToEmail($email);
			$mail->setBody($message);
			$mail->setSubject($mailSubject);
			$mail->setFromName(Mage::getStoreConfig('trans_email/ident_general/name'));
            $mail->setFromEmail($email);
			$mail->setType('Html');
			try {
				$mail->send();
				$this->_getSession()->addSuccess($this->_getHelper('customer')->__('Message sent'));
				$this->_redirectReferer();
			} catch (Exception $ex) {}

		}
		
		
        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('customer/account_dashboard')
        );
        $this->getLayout()->getBlock('head')->setTitle($this->__('Dashboard'));
        $this->renderLayout();
    }	
	
    /**
     * Forgot customer account information page
     */
    public function editAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');

        $block = $this->getLayout()->getBlock('customer_edit');
        if ($block) {
            $block->setRefererUrl($this->_getRefererUrl());
        }
        $data = $this->_getSession()->getCustomerFormData(true);
        $customer = $this->_getSession()->getCustomer();
        if (!empty($data)) {
            $customer->addData($data);
        }
        if ($this->getRequest()->getParam('changepass') == 1) {
            $customer->setChangePassword(1);
        }

        $this->getLayout()->getBlock('head')->setTitle($this->__('Profile Settings'));
        $this->getLayout()->getBlock('messages')->setEscapeMessageFlag(true);
        $this->renderLayout();
    }

    /**
     * Success Registration
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return Mage_Customer_AccountController
     */
    protected function _successProcessRegistration(Mage_Customer_Model_Customer $customer)
    {
        $session = $this->_getSession();
        if ($customer->isConfirmationRequired()) {
            /** @var $app Mage_Core_Model_App */
            $app = $this->_getApp();
            /** @var $store  Mage_Core_Model_Store*/
            $store = $app->getStore();
            $customer->sendNewAccountEmail(
                'confirmation',
                $session->getBeforeAuthUrl(),
                $store->getId(),
                $this->getRequest()->getPost('password')
            );
            $customerHelper = $this->_getHelper('customer');
            $session->addSuccess($this->__('Account confirmation is required. Please, check your email for the confirmation link. To resend the confirmation email please <a href="%s">click here</a>.',
                $customerHelper->getEmailConfirmationUrl($customer->getEmail())));
            $url = $this->_getUrl('*/*/index', array('_secure' => true));
        } else {
            $session->setCustomerAsLoggedIn($customer);
            $url = Mage::getBaseUrl()."customer/account";
        }
		      $_customer = Mage::getModel('customer/customer')->load($customer->getId());
			  $groupid=$_customer->getGroupId();

					if($groupid==1)
					{
					$templateId = 8;
                    $mailsubject="Lets Get Started!";					
					}
					else
					{
					$templateId = 9;
                     $mailsubject="So Glad You Made It";					
					}
		
		            
					$from_email = Mage::getStoreConfig('trans_email/ident_custom1/email');
					$emailTemplate = Mage::getModel('core/email_template')->load($templateId);
					$emailTemplate->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name'));
					$mail = Mage::getModel('core/email');
					
					$email_template_variables = array(
						'name' => $customer->getName(),
					);
					$mailSubject = $mailsubject;
					$email = $customer->getEmail();
					$processedTemplate = $emailTemplate->getProcessedTemplate($email_template_variables);
					$mail = Mage::getModel('core/email');
					$mail->setToName($customer->getName());
					$mail->setTemplateParam($email_template_variables);
					$mail->setToEmail($email);
					$mail->setBody($processedTemplate);
					$mail->setSubject($mailSubject);
					$mail->setFromName(Mage::getStoreConfig('trans_email/ident_general/name'));
					$mail->setFromEmail($from_email);
					$mail->setType('Html');
					try {
						$mail->send();
					} catch (Exception $ex) {}
		
		
		
        $this->_redirectSuccess($url);
        return $this;
    }

    public function membershipAction()
    {

	   $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
		if($this->getRequest()->isPost())
		{

			$cartHelper = Mage::helper('checkout/cart');
			$items = $cartHelper->getCart()->getItems();        
			foreach ($items as $item) 
			{
			   $itemId = $item->getItemId();
			   $cartHelper->getCart()->removeItem($itemId)->save();
			}
			$qty=1;
			$productId = $this->getRequest()->getParam('subtype');
			//$customerid=$this->getRequest()->getParam('customerid');
			//$product = Mage::getModel('catalog/product')->load($productId);
			//$customer = Mage::getModel('customer/customer')->load($customerid);
			//$quote = Mage::getModel('sales/quote')->loadByCustomer($customer);
			//$quote->addProduct($product, $qty);
			//$quote->collectTotals()->save();
            $product = Mage::getModel('catalog/product')->load($productId);
			$cart = Mage::getModel('checkout/cart');
			$cart->init();
			$params = array(
				'product' => $productId,
				'qty' => 1
				
				);
			$cart->addProduct($product, $params);
			$cart->save();
			Mage::getSingleton('checkout/session')->setCartWasUpdated(true);
			$this->_redirectReferer();
		}
    }

    /**
     * Change customer password action
     */
    public function editPostAction()
    {
        if (!$this->_validateFormKey()) {
            return $this->_redirect('*/*/edit');
        }

        if ($this->getRequest()->isPost()) {
            /** @var $customer Mage_Customer_Model_Customer */
            $customer = $this->_getSession()->getCustomer();
            $customer->setOldEmail($customer->getEmail());
            /** @var $customerForm Mage_Customer_Model_Form */
            $customerForm = $this->_getModel('customer/form');
            $customerForm->setFormCode('customer_account_edit')
                ->setEntity($customer);

            $customerData = $customerForm->extractData($this->getRequest());

            $errors = array();
            $customerErrors = $customerForm->validateData($customerData);
            if ($customerErrors !== true) {
                $errors = array_merge($customerErrors, $errors);
            } else {
                $customerForm->compactData($customerData);
                $errors = array();

                // If email change was requested then set flag
                $isChangeEmail = ($customer->getOldEmail() != $customer->getEmail()) ? true : false;
                $customer->setIsChangeEmail($isChangeEmail);

                // If password change was requested then add it to common validation scheme
                $customer->setIsChangePassword($this->getRequest()->getParam('change_password'));

                if ($customer->getIsChangePassword()) {
					if (!$customer->validatePassword($this->getRequest()->getPost('current_password'))) {
						$errors[] = $this->__('Invalid current password');
					}					
                    $newPass    = $this->getRequest()->getPost('password');
                    $confPass   = $this->getRequest()->getPost('confirmation');

                    if (strlen($newPass)) {
                        /**
                         * Set entered password and its confirmation - they
                         * will be validated later to match each other and be of right length
                         */
                        $customer->setPassword($newPass);
                        $customer->setPasswordConfirmation($confPass);
                    } else {
                        $errors[] = $this->__('New password field cannot be empty.');
                    }
                }

                // Validate account and compose list of errors if any
                $customerErrors = $customer->validate();
                if (is_array($customerErrors)) {
                    $errors = array_merge($errors, $customerErrors);
                }
            }

            if (!empty($errors)) {
                $this->_getSession()->setCustomerFormData($this->getRequest()->getPost());
                foreach ($errors as $message) {
                    $this->_getSession()->addError($message);
                }
                $this->_redirect('*/*/edit');
                return $this;
            }

            try {
                $customer->cleanPasswordsValidationData();

                // Reset all password reset tokens if all data was sufficient and correct on email change
                if ($customer->getIsChangeEmail()) {
                    $customer->setRpToken(null);
                    $customer->setRpTokenCreatedAt(null);
                }
				
                $groupid=$customer->getGroupId();
                
                
				if($customer->getData('approval') == 65){//65 - Not Requested for approval
				    if($groupid==1){
                        $customer->setData('approval',64);//64 - Requested for approval
                    }elseif ($groupid==2) {
                        $customer->setData('approval',63); //Approved. Approve art director directly
                    }
                    
				}
                
                
                //send approval email to admin
				if($customer->getData('email_for_approval')== 66){
				    //change attribute value
					$customer->setData('email_for_approval',67);
	               
                    //Send email
                    $templateId = 2;
					$from_email = $customer->getEmail();
					$emailTemplate = Mage::getModel('core/email_template')->load($templateId);
					$mail = Mage::getModel('core/email');
					$mail->setToName('Folyoz');
					$mail->setFromName('Folyoz');
					$email_template_variables = array(
						'name' => $customer->getName(),
						'email'=>$customer->getEmail(),
					);
                    
                    if($groupid==1){
                        $mailSubject = 'Approval Request | Artist';
                    }elseif ($groupid==2) {
                        $mailSubject = 'Auto Approved | Art Director';
                    }
                    				
                    $email = Mage::getStoreConfig('trans_email/ident_custom1/email');
					$processedTemplate = $emailTemplate->getProcessedTemplate($email_template_variables);
					$mail = Mage::getModel('core/email');
					$mail->setToName('folyoz');
					$mail->setTemplateParam($email_template_variables);
					$mail->setToEmail($email);
					$mail->setBody($processedTemplate);
					$mail->setSubject($mailSubject);
					$mail->setFromName(Mage::getStoreConfig('trans_email/ident_general/name'));
                    $mail->setFromEmail($email);
					$mail->setType('Html');
					try {
						$mail->send();
					} catch (Exception $ex) {}
					
				}
				if(!isset($_POST['work_type_use_for_illustration']))
					$customer->setData('work_type_use_for_illustration',0);

				if(!isset($_POST['work_type_use_for_photography']))
					$customer->setData('work_type_use_for_photography',0);				
                $customer->save();
               // $this->_getSession()->setCustomer($customer)
                   // ->addSuccess($this->__('Thanks, we’re reviewing your account to make sure you’re a real person. You’ll receive a confirmation email.'));

                if ($customer->getIsChangeEmail() || $customer->getIsChangePassword()) {
                    $customer->sendChangedPasswordOrEmail();
                }

                $this->_redirect('customer/account');
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->setCustomerFormData($this->getRequest()->getPost())
                    ->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->setCustomerFormData($this->getRequest()->getPost())
                    ->addException($e, $this->__('Cannot save the customer.'));
            }
        }

        $this->_redirect('*/*/edit');
    }
	
    public function helpwikiAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
    }	

    public function submitportfolioAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
    }		
    
    public function managesubmissionAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
    }
    public function analyticsAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
    }		
    /**
     * Logout success page
     */
    public function logoutSuccessAction()
    {
        $this->_redirect('/');
    }

    /**
     * Add welcome message and send new account email.
     * Returns success URL
     *
     * @param Mage_Customer_Model_Customer $customer
     * @param bool $isJustConfirmed
     * @return string
     */
    protected function _welcomeCustomer(Mage_Customer_Model_Customer $customer, $isJustConfirmed = false)
    {
        if ($this->_isVatValidationEnabled()) {
            // Show corresponding VAT message to customer
            $configAddressType =  $this->_getHelper('customer/address')->getTaxCalculationAddressType();
            $userPrompt = '';
            switch ($configAddressType) {
                case Mage_Customer_Model_Address_Abstract::TYPE_SHIPPING:
                    $userPrompt = $this->__('If you are a registered VAT customer, please click <a href="%s">here</a> to enter you shipping address for proper VAT calculation',
                        $this->_getUrl('customer/address/edit'));
                    break;
                default:
                    $userPrompt = $this->__('If you are a registered VAT customer, please click <a href="%s">here</a> to enter you billing address for proper VAT calculation',
                        $this->_getUrl('customer/address/edit'));
            }
            $this->_getSession()->addSuccess($userPrompt);
        }

        $customer->sendNewAccountEmail(
            $isJustConfirmed ? 'confirmed' : 'registered',
            '',
            Mage::app()->getStore()->getId(),
            $this->getRequest()->getPost('password')
        );

        $successUrl = $this->_getUrl('*/*/index', array('_secure' => true));
        if ($this->_getSession()->getBeforeAuthUrl()) {
            $successUrl = $this->_getSession()->getBeforeAuthUrl(true);
        }
        return $successUrl;
    }
	
	public function sendPortfolioToDirectorAction()
    {
		if($_REQUEST['id']){
			$userid = $_REQUEST['id'];
			$loinuserid = $loginuser = Mage::getSingleton('customer/session')->getCustomer()->getId();
			$resource = Mage::getSingleton('core/resource');
 			$readAdapter= $resource->getConnection('core_read');
			$query = "SELECT * FROM `submitted_portfolio` WHERE `submitted_user_id` = $loinuserid";
			$directorcount = $readAdapter->fetchAll($query);
			
			if($this->portfolioImageCount() <= 0) {
				return $this->getResponse()->setBody(json_encode('Oops, it seems that there are no images in your portfolio, you must have at least one image to submit.'));
			}
			$subscription = Mage::getModel('customer/customer')->checkRecurring($loinuserid);
			$maxPortfolioImage = Mage::getStoreConfig('marketplace/marketplace_inventory/max_allowed_portfolio_product_images');
			if(count($directorcount) >= $maxPortfolioImage and ($subscription['status'] == 2 or $subscription['status'] == 3))
				return $this->getResponse()->setBody(json_encode('You’ve reached your limit of 12 submissions. For unlimited submissions please see your membership page.'));

			$match_value = $this->getRequest()->getParam('match_value');
			$resource = Mage::getSingleton('core/resource');
			$writeAdapter = $resource->getConnection('core_write');
			$query = "INSERT INTO `submitted_portfolio` (`id`, `submitted_user_id`,`user_id`, `match_value`, `created`, `updated`) VALUES (NULL, $loinuserid, $userid, '$match_value', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
			$writeAdapter->query($query);						
		}
		return $this->getResponse()->setBody(json_encode(200));
		
    }
	public function deleteAccountAction()
	{	
 		Mage::register('isSecureArea', true);
		$id = $this->_getSession()->getCustomer()->getId();
		$customer = Mage::getModel('customer/customer')->load($id);
		$customer->delete();		
		Mage::unregister('isSecureArea');
		$this->_redirect("/");
	}
	
// ajax action	
    public function ajaxDirectorDetailsAction()
    {
		$id = $this->getRequest()->getParam('id');
		$customer = Mage::getModel('customer/customer')->load($id);
		$loginuser = Mage::getSingleton('customer/session')->getCustomer();
		$loginuserid = $loginuser->getId();
				
		if($customer)
		{
			$match = $fit = $match_value = 0;
			$allow = 18;
			if($loginuser->getData('profession') == 38){				
				$a1 = explode(',', $customer->getData('work_type_use_for_illustration'));
				$a2 = explode(',', $loginuser->getData('work_type_use_for_illustration'));
				$match = array_intersect($a1,$a2);
				$match = count($match)>0 ?count($match): 0;
				if($customer->getData('hire_illustrators') == "Frequently"){$fit = 82;}
				elseif($customer->getData('hire_illustrators') == "Sometimes"){$fit = 72;}
				elseif($customer->getData('hire_illustrators') == "Never"){$fit = 0;}
				$match_value = round($fit + (((100 - 82)/$allow) * $match));
				
			}elseif($loginuser->getData('profession') == 35){
				$a1 = explode(',', $customer->getData('work_type_use_for_photography'));
				$a2 = explode(',', $loginuser->getData('work_type_use_for_photography'));
				$match = array_intersect($a1,$a2);
				$match = count($match)>0 ?count($match): 0;
				if($customer->getData('hire_photographers') == "Frequently"){$fit = 82;}
				elseif($customer->getData('hire_photographers') == "Sometimes"){$fit = 72;}
				elseif($customer->getData('hire_photographers') == "Never"){$fit = 0;}
				$match_value = round($fit + (((100 - 82)/$allow) * $match));			
			}
			if($match_value > 81){$str="<div class='cus-area green'><span class='big-font'>Good Match </span><span class='short-font'> Go ahead and Submit.</span></div>";}
			elseif($match_value < 82 and $match_value > 71 ){$str="<div class='cus-area yellow'><span class='big-font'>Narrow Match </span><span class='short-font'> Your Submission may get blocked.</span></div>";}
			elseif($match_value < 72 ){$str="<div class='cus-area red'><span class='big-font'>Bad Match </span><span class='short-font'> Your Submission may get blocked.</span></div>";}
			$match = '
					<div class="dash-left-days subpotfolio-frms-outer">
						<div class="s-c1">
							<div class="big-front">'.$match_value.'%</div>
							<div>Matching Criteria</div>
						</div>
						<div class="s-c2">'.$str.'</div>
					</div>
					';		
			$tooltipclick = '
				<div class="tooltip-click">
					<span class="intergotive-sign" style="margin-left:0px">?</span>
				</div>';
			$resource = Mage::getSingleton('core/resource');
			$readAdapter= $resource->getConnection('core_read');	
			//$subscription = Mage::getModel('customer/customer')->stripeApi($loginuserid); // check user user subscription form stripe api
			
			$disabled = ""; $disabledgray = "" ;
			if($loginuser->getData('approval') != 63){ 
				$disabled = "disabled" ;
				$disabledgray = "disabled-gray" ;
			}
 			$readAdapter= $resource->getConnection('core_read');
			$query = "SELECT * FROM `submitted_portfolio` WHERE `submitted_user_id` = $loginuserid and `user_id` = $id";
			$result = $readAdapter->fetchAll($query);
			
			if(count($result) == 0){	
				$form = '<input class="button rev-btn sendPortfolioToDirector '.$disabledgray.'" id="sub_'.$id.'" type="submit" directorid="'.$id.'" value="Submit Portfolio"'.$disabled.'>'.$tooltipclick;
 			}else{
				$form = '<input class="button rev-btn disabled-gray" type="submit" id="sub_'.$id.'" directorid="'.$id.'" name="submit" value="Submitted" disabled>'.$tooltipclick;
			}
			$worktypei = $worktypep = "";
			$options = Mage::getResourceSingleton('customer/customer')->getAttribute('work_type_use_for_photography')->getSource()->getAllOptions();
			foreach ($options as $option):
				if($option["value"] != "") {
					if (in_array($option['value'], explode(',', $customer->getData('work_type_use_for_photography')))){
						$worktypep .= $option['label'].", ";
					}
				} 
			endforeach;
			$options = Mage::getResourceSingleton('customer/customer')->getAttribute('work_type_use_for_illustration')->getSource()->getAllOptions();
			foreach ($options as $option):
				if($option["value"] != "") {
					if (in_array($option['value'], explode(',', $customer->getData('work_type_use_for_illustration')))){
						$worktypei.= $option['label'].", ";
					}
				} 
			endforeach;
			$worktypep = trim($worktypep,", ");
			$worktypei = trim($worktypei,", ");
			if($customer->getAvatar()==""){
			$img = '<span class="customer-img"><img id="preview" src="'. Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) .'customer/nopropimg.png" height="125px"; width="125px"/></span>';
			} else {
			$img =	'<span class="customer-img"><img id="preview" src="'. Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'customer'. $customer->getAvatar().'" height="125px"; width="125px" /></span>';
			}
			$data = '
			<div class="details-body">
				<div class="row">
					<div class="director-pic">'.
						$img
					.'</div>
					<div class="director-personal">
						<div class="d-p-c1">
							<strong>'.$customer->getName().'</strong>
							<div>
								<span class="details-heading">Position:</span>
								<span>'.$customer->getData('position').'</span>
							</div>
							<div>
								<span class="details-heading">Publication:</span>
								<span>'.$customer->getData('company_name').'</span>
							</div>
							<div>
								<span class="details-heading">Uses Illustration:</span>
								<span>'.$customer->getData('hire_illustrators').'</span>
							</div>
							<div>
								<span class="details-heading">Uses Photography:</span>
								<span>'.$customer->getData('hire_photographers').'</span>
							</div>
						</div>
						<div class="d-p-c2">
						'.$match.'
							<div class="subpotfolio-frms-inner">'.$form.'</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="details-heading">Type of Illustration they use:</div>
					<div>'.$worktypei.'</div>
				</div>
				<div class="row">
					<div class="details-heading">Type of Photography they use:</div>
					<div>'.$worktypep.'</div>
				</div>
				<div class="row">
					<div class="details-heading">Looking for:</div>
					<div>'.nl2br($customer->getData('looking_for_information')).'</div>
				</div>
				<div class="row">
					<div class="details-heading">Not looking for:</div>
					<div>'.nl2br($customer->getData('not_want_to_see_information')).'</div>
				</div>
			</div>';
			echo $data;
		}
    }
	
    public function ajaxArtistDetailsAction()
    {
										
		$id = $this->getRequest()->getParam('id');
		$view = $this->getRequest()->getParam('view');
		$rowid = $this->getRequest()->getParam('rowid');
		$updated_portfolio = $this->getRequest()->getParam('updated_portfolio');
		$customer = Mage::getModel('customer/customer')->load($id);
		$loginuser = Mage::getSingleton('customer/session')->getCustomer();
		$loginuserid = $loginuser->getId();
		$resource = Mage::getSingleton('core/resource');
		$product = $data = "";
		
		if($view == 0 or $updated_portfolio == 1){
			$writeAdapter = $resource->getConnection('core_write');
			$query = "UPDATE `submitted_portfolio` SET `viewed` = 1 ,`updated_portfolio` = 0 WHERE `submitted_portfolio`.`id` = $rowid;";
			$writeAdapter->query($query);		
		}
		
		$readAdapter= $resource->getConnection('core_read');
		$query = "SELECT * FROM `submitted_portfolio` WHERE `submitted_user_id` = $id and `user_id` = $loginuserid";
		$result = $readAdapter->fetchAll($query);

		if($result[0]['blocked']==0){$block	= '<input type="button" id="blocked-btn-'.$rowid.'" class="blocked-btn button rev-btn" status="0" rowid="'.$rowid.'" value="Block">'; $followdisabled="";}
		else{$block	= '<input type="button" id="blocked-btn-'.$rowid.'" class="unblock-btn button rev-btn" status="1"  rowid="'.$rowid.'" value="Un-Block">'; $followdisabled="disabled";}
		
		if($result[0]['following']==0){$follow = '<input type="button" id="following-btn-'.$rowid.'" class="following-btn button rev-btn getdata" status="0" rowid="'.$rowid.'" value="Follow" submitteduser="'.$id.'" '.$followdisabled.' _action="DirectorFollowingArtist" _artistid="'.$id.'">';}
		else {$follow = '<input type="button" id="following-btn-'.$rowid.'" class="following-btn button rev-btn getdata" status="1" rowid="'.$rowid.'" value="Unfollow" submitteduser="'.$id.'" _action="DirectorUnfollowingArtist" _artistid="'.$id.'">';}
					
		if($customer){
			
			$match = $fit = $match_value = 0;
			$allow = 18;			
			if($customer->getData('profession') == 38){				
				$a1 = explode(',', $customer->getData('work_type_use_for_illustration'));
				$a2 = explode(',', $loginuser->getData('work_type_use_for_illustration'));
				$match = array_intersect($a1,$a2);
				$match = count($match)>0 ?count($match): 0;
				if($loginuser->getData('hire_illustrators') == "Frequently"){$fit = 82;}
				elseif($loginuser->getData('hire_illustrators') == "Sometimes"){$fit = 72;}
				elseif($loginuser->getData('hire_illustrators') == "Never"){$fit = 0;}
				$match_value = round($fit + (((100 - 82)/$allow) * $match));
				
			}elseif($customer->getData('profession') == 35){
				$a1 = explode(',', $customer->getData('work_type_use_for_photography'));
				$a2 = explode(',', $loginuser->getData('work_type_use_for_photography'));
				$match = array_intersect($a1,$a2);
				$match = count($match)>0 ?count($match): 0;
				if($loginuser->getData('hire_photographers') == "Frequently"){$fit = 82;}
				elseif($loginuser->getData('hire_photographers') == "Sometimes"){$fit = 72;}
				elseif($loginuser->getData('hire_photographers') == "Never"){$fit = 0;}
				$match_value = round($fit + (((100 - 82)/$allow) * $match));			
			}
			$match = '
					<div class="s-c1">
						<div class="big-front">'.$match_value.'%</div>
						<div>Matching Criteria</div>
					</div>';	
			if($customer->getAvatar()==""){
			$img = '<span class="customer-img"><img id="preview" src="'. Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) .'customer/nopropimg.png" height="157px"; width="157px"/></span>';
			} else {
			$img =	'<span class="customer-img"><img id="preview" src="'. Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'customer'. $customer->getAvatar().'" height="157px"; width="157px" /></span>';
			}				
			$collection_product = Mage::getModel('marketplace/product')->getCollection()
									->addFieldToFilter('userid',array('eq'=>$id));
			foreach($collection_product as $key)
			{
				$mageproductid = $key->getData("mageproductid");
				$p = Mage::getModel('catalog/product')->load($mageproductid);
				if($p->getData('is_portfolio') == true) {
					$v = Mage::helper('catalog/image')->init($p, 'thumbnail')->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize(350);
					$product .= '<li class="getdata" _artistid="'.$id.'" _action="ViewedProductOnSubmittedPortfolio"><img src="'.$v.'" mageproductid="'.$mageproductid.'" class="enlarge" managesubmission="1" artisid="'.$customer->getId().'"/></li>';
				}
			}
			$address = $customer->getData('state').", ".$customer->getData('country');
			$address = trim($address,",");
			$data = '
				<div class="details-body bootstrap-row-overwrite">
					<div class="row">
						<div class="artist-personal-outer">
							<div class="artist-pic">'.
								$img
							.'</div>
							<div class="artist-personal">
								<strong>'.$customer->getName().'</strong>
								<div>	
									'.$address.'
								</div>
								<button class="contact-btn button rev-btn getdata" btnid="'.$customer->getId().'" _action="ViewedContactInfo" _artistid="'.$customer->getId().'">Contact Info</button>
							</div>
						</div>
						<div class="dash-left-days managesubmission">
							<div>'.$match.'<div class="m-follow-btn">'.$follow.$block.'</div></div>
							<div style="text-align:right;" class="blue-border">
								<div class="tooltip-outer tooltip-open-left">
									<span class="intergotive-sign">?</span>
									<div class="tooltip" style="display:none">
										<div>FOLLOW: You can follow as many Artists as you want and receive one periodic email for all followed Artists (if their portfolios were updated). Adjust the frequency of how often you want to receive this one email in your profile settings.</div><br/>
										<div>BLOCK: You can block any Artist\'s submission. Blocking, mutes that submission and you won\'t see updates from that Artist and they can\'t submit to you again. If you change your mind and decide to Un-Block an Artist, you will be able to do that too.</div><br/>
										<div>DO NOTHING: You can simply do nothing with a submission and it just moves down your list, similar to unread emails.</div><br/>
									</div>					
								</div>							
							</div>
						</div>						
					</div>
					<div class="row">
						<div class="mans-product-image"><ul class="product-image">'.$product.'</ul></div>
					</div>
					<div class="row bor-size-custom">
						<strong>About this Creative:</strong>
						<div class="product-education contain-custom">'.nl2br($customer->getData('education')).'</div>
					</div>
					<div class="row bor-size-custom">
						<strong>Clients Include:</strong>
						<div class="product-clients contain-custom">'.nl2br($customer->getData('clients')).'</div>
					</div>				
				</div>';
		}
		echo $data;
	}
    public function ajaxArtistContactAction()
    {
		$id = $this->getRequest()->getParam('id');
		$customer = Mage::getModel('customer/customer')->load($id);
		$data="";
		if($customer){
			if($customer->getAvatar()==""){
			$img = '<span class="customer-img"><img id="preview" src="'. Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) .'customer/nopropimg.png" height="125px"; width="125px"/></span>';
			} else {
			$img =	'<span class="customer-img"><img id="preview" src="'. Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'customer'. $customer->getAvatar().'" height="125px"; width="125px" /></span>';
			}
			$html = '<div class="social-contact">';
			if(!empty($customer->getData('website'))){
				$html .= '<a href="'.$customer->getData('website').'" class="website getdata" target="_blank" _artistid="'.$id.'" _action="ClickedWebsite"></a>';
			}
			if(!empty($customer->getData('facebook_url'))){
				$html .= '<a href="'.$customer->getData('facebook_url').'" class="facebook getdata" target="_blank" _artistid="'.$id.'" _action="ClickedFacebook"></a>';
			}
			if(!empty($customer->getData('twitter_url'))){
				$html .= '<a href="'.$customer->getData('twitter_url').'" class="twitter getdata" target="_blank" _artistid="'.$id.'" _action="ClickedTwitter"></a>';
			}
			if(!empty($customer->getData('instagram_url'))){
				$html .= '<a href="'.$customer->getData('instagram_url').'" class="instagram getdata" target="_blank" _artistid="'.$id.'" _action="ClickedInstagram"></a>';
			}
			$address = $customer->getData('state').", ".$customer->getData('country');
			$address = trim($address,",");	
			$phone = "";
			if(!empty($customer->getData('phone'))) {
				$phone = '
					<div>
						'.substr($customer->getData('phone'), 0, 6).'<span class="getdata ph_click" _artistid="'.$id.'" _action="ClickedPhone">**** <a><u>View</u></a></span>
					</div>
					<div style="display:none" class="ph_show">
						<a href="tel:'.$customer->getData('phone').'">'.$customer->getData('phone').'</a>
					</div>
					<script>jQuery("body").on("click",".ph_click",function(){jQuery(this).parent().hide();jQuery(".ph_show").show()})</script>				
				';
			}
			$html .= '</div>';
			$data = '
			<div class="row">
				<div class="artist-personal">
					<div class="artist-personal-inner1">
						<div class="artist-pic">'.
							$img
						.'</div>
						<div class="ap-sub-inn">
							<strong>'.$customer->getName().'</strong>
							<div>	
								'.$address.'
							</div>
						</div>
					</div>
					<div class="artist-personal-inner2">
						<div>'.$phone.'</div>
						<div class="getdata" _artistid="'.$id.'" _action="ClickedEmail">
							<a href="mailto:'.$customer->getData('email').'">'.$customer->getData('email').'</a>
						</div>
						'.$html.'
					</div>
				</div>				
			<div>
			';			
		}
		echo $data;
	}
	public function ajaxFollowingAction()
	{
		$rowid = $this->getRequest()->getParam('rowid');
		$status = $this->getRequest()->getParam('status');
		$submitteduserid = $this->getRequest()->getParam('submitteduser');
		if($status == 0){
			$status=1;
			$customer = Mage::getModel('customer/customer')->load($submitteduserid);
			$loginuser = Mage::getSingleton('customer/session')->getCustomer();
			$email = $customer->getEmail();
			$templateId = 3;
			$emailTemplate = Mage::getModel('core/email_template')->load($templateId);
			$mail = Mage::getModel('core/email');
			$mail->setToName('Folyoz');
			//$mail->setFromName($loginuser->getName());
			$email_template_variables = array(
				'name' => $customer->getName(),
			);
			$mailSubject = 'Someone Likes You';
			$from_email = $loginuser->getEmail();
			$processedTemplate = $emailTemplate->getProcessedTemplate($email_template_variables);
			$mail = Mage::getModel('core/email');
			$mail->setToName($customer->getName());
			$mail->setTemplateParam($email_template_variables);
			$mail->setToEmail($email);
			$mail->setBody($processedTemplate);
			$mail->setSubject($mailSubject);
			//$mail->setFromName($loginuser->getName());
			$mail->setFromName(Mage::getStoreConfig('trans_email/ident_general/name'));
            $mail->setFromEmail(Mage::getStoreConfig('trans_email/ident_custom1/email'));
			$mail->setType('Html');
			try {
				$mail->send();
			} catch (Exception $ex) {}
			
		}else{$status=0;}
		$resource = Mage::getSingleton('core/resource');
		$writeAdapter = $resource->getConnection('core_write');
		$query = "UPDATE `submitted_portfolio` SET `following` = $status WHERE `submitted_portfolio`.`id` = $rowid;";
		$writeAdapter->query($query);
	}

	public function ajaxBlockedAction()
	{
		$rowid = $this->getRequest()->getParam('rowid');
		$status = $this->getRequest()->getParam('status');
		if($status == 0){$status=1;}else{$status=0;}
		$resource = Mage::getSingleton('core/resource');
		$writeAdapter = $resource->getConnection('core_write');
		$query = "UPDATE `submitted_portfolio` SET `blocked` = $status, `following` = 0 WHERE `submitted_portfolio`.`id` = $rowid;";
		$writeAdapter->query($query);
	}
	
	public function ajaxEnlargeProImageAction()
	{
		$mageproductid = $this->getRequest()->getParam('mageproductid');
		$list = $this->getRequest()->getParam('list');
		$managesubmission = $this->getRequest()->getParam('managesubmission');
		$artistdetails = $this->getRequest()->getParam('artistdetails');
		$artisid = $this->getRequest()->getParam('artisid');
		$customer = Mage::getSingleton('customer/session')->getCustomer();
		$p = Mage::getModel('catalog/product')->load($mageproductid);
		$v = Mage::helper('catalog/image')->init($p, 'image')->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE);
		list($width, $height) = getimagesize($v);
		if($height > 520){
			$v = Mage::helper('catalog/image')->init($p, 'image')->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize(800,520);
			list($width, $height) = getimagesize($v);
		}elseif($width > 700){
			$v = Mage::helper('catalog/image')->init($p, 'image')->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize(700,700);
			list($width, $height) = getimagesize($v);
		}else{
			$v = Mage::helper('catalog/image')->init($p, 'image')->constrainOnly(TRUE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize(700,625);
			list($width, $height) = getimagesize($v);
		}
		if(!empty($artisid)){
			$subscription = Mage::getModel('customer/customer')->stripeApi($artisid); // check user user subscription form stripe api
			$promember = "";
			if($subscription['code'] == 200){$promember = "<strong style='color:#94d0ff'>Pro Member</strong>";}		
			elseif($subscription['code'] == 503 and $customer->getId()== $artisid){$promember = "<strong style='color:#94d0ff'>Not Pro Member</strong>";}		
		}
		$product = Mage::getResourceModel('catalog/product');
		
        
        $cats = $p->getCategoryIds();
        foreach ($cats as $category_id) {
            $_cat = Mage::getModel('catalog/category')->load($category_id);
        } 
      
        $productCategoryId = $_cat->getId();
        
        if($productCategoryId == '3'){
            $styleAttribute = 'style';
        }else if($productCategoryId == '4'){
            $styleAttribute = 'style_photography';
        }
        
		$mediam = "";
        $options = $product->getAttribute($styleAttribute)->getSource()->getAllOptions();
		foreach ($options as $option){
			if(in_array($option['value'], explode(',', $p->getData($styleAttribute)))) {
				$mediam .= $option['label'].', ';
			}
		 }
        //Mage::log('Category Id '.$productCategoryId, null, 'ajaxEnlargeProImageActionsss.log', true);    
        //Mage::log('Style Attribute: '.$styleAttribute, null, 'ajaxEnlargeProImageActionsss.log', true);
        //Mage::log('Medium '.$mediam, null, 'ajaxEnlargeProImageActionsss.log', true);
        
		$html = '<img src="'.$v.'" mageproductid="'.$mageproductid.'" class="enlarged-img" id="enlarged-'.$mageproductid.'" />'; 
		$mediam = trim($mediam,", ");
		if($p->getData('re_licensing')){$availability="Yes, contact the Artist directly.";}else{$availability="No";}
		if($list == 1){
			$artist = Mage::getModel('customer/customer')->load($artisid);
			$allow = 18; $match="";
			$string = strip_tags($artist->getData('education'));
            $education = $string;
			if (strlen($string) > 150) {

				// truncate string
				$stringCut = substr($string, 0, 150);
				$endPoint = strrpos($stringCut, ' ');

				//if the string doesn't contain any space then it will cut without word basis.
				$education = $endPoint? substr($stringCut, 0, $endPoint) : substr($stringCut, 0);
				$education .= '...<a target="_blank" href="'.Mage::getBaseUrl().'art_details?id='.$artisid.'" >more</a>';
			}
			if($customer->getGroupId() == 2){ // this match section only for director login
				if($artist->getData('profession') == 38){				
					$a1 = explode(',', $customer->getData('work_type_use_for_illustration'));
					$a2 = explode(',', $artist->getData('work_type_use_for_illustration'));
					$match = array_intersect($a1,$a2);
					$match = count($match)>0 ?count($match): 0;
					if($customer->getData('hire_illustrators') == "Frequently"){$fit = 82;}
					elseif($customer->getData('hire_illustrators') == "Sometimes"){$fit = 72;}
					elseif($customer->getData('hire_illustrators') == "Never"){$fit = 0;}
					$match_value = round($fit + (((100 - 82)/$allow) * $match));
					
				}elseif($artist->getData('profession') == 35){
					$a1 = explode(',', $customer->getData('work_type_use_for_photography'));
					$a2 = explode(',', $artist->getData('work_type_use_for_photography'));
					$match = array_intersect($a1,$a2);
					$match = count($match)>0 ?count($match): 0;
					if($customer->getData('hire_photographers') == "Frequently"){$fit = 82;}
					elseif($customer->getData('hire_photographers') == "Sometimes"){$fit = 72;}
					elseif($customer->getData('hire_photographers') == "Never"){$fit = 0;}
					$match_value = round($fit + (((100 - 82)/$allow) * $match));			
				}
				if($match_value > 81){$class="green";}
				elseif($match_value < 82 and $match_value > 71 ){$class="yellow";}
				elseif($match_value < 72 ){$class="red";}
				$match = '
						<div class="s-c1 '.$class.'">
							<div class="big-front">'.$match_value.'%</div>
							<div class="matching-c">Matching Criteria</div>
						</div>
						';						
			}
			if($artist->getAvatar()==""){
			$img = '<span class="customer-img"><img id="preview" src="'. Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) .'customer/nopropimg.png" height="125px"; width="125px"/></span>';
			} else {
			$img =	'<span class="customer-img"><img id="preview" src="'. Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'customer'. $artist->getAvatar().'" height="125px"; width="125px" /></span>';
			}
            
            $client='';
            if ($p->getData('client') != ''){
                $client = '<div><span class="details-heading">Client:</span><span>'.$p->getData('client').'</span></div>';
            }
            
			$html = '
				<div class="img-pop-left">'.$html.'</div>
				<div class="img-pop-right">
					'.$promember.'
					<strong>'.$p->getName().'</strong>
					<div>
						<span class="details-heading">Style/Medium:</span>
						<span>'.$mediam .'</span>
					</div>
                    '.                    
                    ''.$client.''.
					'<div>
						<span class="details-heading">Availability:</span>
						<span>'.$availability.'</span>
					</div>
					<div>
						<span class="c1">'.$img.'</span>
						<span class="c2">'.$artist->getName().'</span>
					</div>
					<div  class="education">
						<span>'.$education.'</span>
					</div>	
					<div>
						<a target="_blank" href="'.Mage::getBaseUrl().'art_details?id='.$artisid.'" class="link-btn">View Portfolio</a>
					</div>	
					<div class="match-outer">'.$match.'</div>						
				</div>
			';
			$resulet = array("html"=>$html,"width"=>$width,"height"=>$height);
			return $this->getResponse()->setBody(json_encode($resulet));
		}
        $client='';
        if ($p->getData('client') != ''){
            $client = '<div><span class="details-heading">Client:</span><span>'.$p->getData('client').'</span></div>';
        }
		if($managesubmission == 1){
			$html .='
					<div>
						<span class="details-heading">'.$p->getData('name').'</span>
					</div>
					<div>
						<span class="details-heading">Style/Medium:</span>
						<span>'.$mediam.'</span>
					</div>
                    '.''.$client.'';
			$resulet = array("html"=>$html,"width"=>$width, "height"=>$height);
			return $this->getResponse()->setBody(json_encode($resulet));			
		}
		if($artistdetails == 1){
			$html .='
					<div class="pop-bottom-outer">
						<div>
							<span class="details-heading">'.$p->getData('name').'</span>
						</div>
						<div>
							<span class="details-heading">Style/Medium:</span>
							<span>'.$mediam.'</span>
						</div>
                        '.''.$client.''.
						'<div>
							<span class="details-heading">Availability:</span>
							<span>'.$availability.'</span>
						</div>			
					</div>			
			';			
			$resulet = array("html"=>$html,"width"=>$width, "height"=>$height);
			return $this->getResponse()->setBody(json_encode($resulet));
		}
		echo $html;
	}
	public function ajaxHideDeleteAccountAction()
	{
		$hide = $this->getRequest()->getParam('hide');
		$html = $str ="";
		$customer = $this->_getSession()->getCustomer();
		if($customer->getData('hide_account') and $hide == '0'){
			$customer->setData('hide_account',0);
			$customer->save();
		}elseif( !$customer->getData('hide_account') and $hide == '1'){
			$customer->setData('hide_account',1);
			$customer->save();
		}		
		if($customer->getData('hide_account')){
			$str = '<button class="rev-btn hide-delete-account" hide="0">Unhide My Account</button>';
		}else{
			$str = '<button class="rev-btn hide-delete-account" hide="1">Hide My Account</button>';
		}
		$html = '
			<strong>Deleting Your Account?</strong>
			<p>If for any reason you want to delete your account, we made it easy. Simply click the Delete my Account button below and confirm deletion.</p>
			<p>When you Delete your account, all of your information, images and corresponding details will be removed permanently from our records. Please make sure you really want to do this because it cannot be undone</p>
			<p>Alternatively, you can simply Hide your account and anything related to it will be hidden from public view. If you change your mind later, simply reactivate your account from the Un-Hide my Account button.</p>
			<div class="pop-btn-out"><button class="rev-btn delete-account">Delete My Account</button>'.$str.'</div>		
		';

		$resulet = array("html"=>$html,"none"=>1);
		return $this->getResponse()->setBody(json_encode($resulet));
	}
	public function ajaxHideAccountAction()
	{
		$hide = $this->getRequest()->getParam('hide');
		$html = $str ="";
		$customer = $this->_getSession()->getCustomer();
		if($customer->getData('hide_account') and $hide == '0'){
			$customer->setData('hide_account',0);
			$customer->save();
		}elseif( !$customer->getData('hide_account') and $hide == '1'){
			$customer->setData('hide_account',1);
			$customer->save();
		}		
		if($customer->getData('hide_account')){
			$str = '<button class="rev-btn hide-account" hide="0">Unhide My Account</button>';
		}else{
			$str = '<button class="rev-btn hide-account" hide="1">Hide My Account</button>';
		}
		$html = '
			<strong>Hide Your Account?</strong>
			<p>Hide your account and anything related to it will be hidden from public view. If you change your mind later, simply reactivate your account from the Un-Hide my Account button.</p>
			<div class="pop-btn-out">'.$str.'</div>		
		';

		$resulet = array("html"=>$html,"none"=>1);
		return $this->getResponse()->setBody(json_encode($resulet));
	}
//analytics input
	public function ajaxAnalyticsInputAction()
	{
		$customer = $this->_getSession()->getCustomer();
		$artist_id = $this->getRequest()->getParam('artist_id');
		$action = $this->getRequest()->getParam('action');
		if($customer->getData('group_id') == 2){
			$artist = Mage::getModel('customer/customer')->load($artist_id);
			if($artist->getId()){
				$resource = Mage::getSingleton('core/resource');
				$writeAdapter = $resource->getConnection('core_write');
				$query = "INSERT INTO `analytics_input` (`director_id`,`director_name`, `artist_id`, `artist_name`, `action`) VALUES (".$customer->getId().",'".$customer->getName()."',".$artist->getId().",'".$artist->getName()."','".$action."')";
				$writeAdapter->query($query);
				
				return $this->getResponse()->setBody(json_encode(200));
			}else{
				return $this->getResponse()->setBody(json_encode(503));
			}
		}else{
			return $this->getResponse()->setBody(json_encode(503));
		}
		
	}
	
	
	private function portfolioImageCount() 
	{
		$userid = Mage::getSingleton('customer/session')->getCustomer()->getId();
		$marketplace_collection_product = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('userid',array('eq'=>$userid));
		$collection_product = array();
		foreach($marketplace_collection_product as $key) {
			$mageproductid = $key->getData("mageproductid");
			$product = Mage::getModel('catalog/product')->load($mageproductid);
			if($product->getData('is_portfolio') == true)
			$collection_product [] = $product;
		}
		return count($collection_product);
	}

	
	// customer portfolio image details
	public function ajaxPortfolioImageAction()
	{
 		$id = $_REQUEST['product_id'];
 		$imgcount = $_REQUEST['imgcount'];
		$userid = Mage::getSingleton('customer/session')->getCustomer()->getId();
		if($id) {
			$r_poduct = Mage::getModel('catalog/product')->load($id);

			if($r_poduct->getData('is_portfolio') == true) {
				$r_poduct->setData('is_portfolio', 0);
				$r_poduct->getResource()->saveAttribute($r_poduct, 'is_portfolio');
				$resposne = array("status" => true, "code" => "unchecked", "imgcount" => $this->portfolioImageCount());
				return $this->getResponse()->setBody(json_encode($resposne));
			} elseif($r_poduct->getData('is_portfolio') == false and $this->portfolioImageCount() < 12) {
				$r_poduct->setData('is_portfolio', 1);
				$r_poduct->getResource()->saveAttribute($r_poduct, 'is_portfolio');
				// for alert director manage submission on product update
				$resource = Mage::getSingleton('core/resource');
				$writeAdapter = $resource->getConnection('core_write');
				$query = "UPDATE `submitted_portfolio` SET `updated_portfolio` = 1, updated_portfolio_counter = updated_portfolio_counter + 1 WHERE `submitted_portfolio`.`submitted_user_id` = $userid;";
				$writeAdapter->query($query);				
				$resposne = array("status" => true, "code" => "checked", "imgcount" => $this->portfolioImageCount());
				return $this->getResponse()->setBody(json_encode($resposne));
			} elseif($r_poduct->getData('is_portfolio') == false and $this->portfolioImageCount() >= 12) {
				$resposne = array("status" => false, "code" => "You already have 12 images selected, you will need to deselect one of them in order to select another.", "imgcount" => $this->portfolioImageCount());
				return $this->getResponse()->setBody(json_encode($resposne));
			}
		} elseif($imgcount) {
			$resposne = array("imgcount" => $this->portfolioImageCount());
			return $this->getResponse()->setBody(json_encode($resposne));
		} else {
			$resposne = array("status" => false, "code" => "Invalid Product", "imgcount" => $this->portfolioImageCount());
			return $this->getResponse()->setBody(json_encode($resposne));
		} 
	}
	
	/* states of county */
	
	public function ajaxCountryStatesAction()
	{
		$statearray = Mage::getModel('directory/region')->getResourceCollection()->addCountryFilter($_REQUEST['country'])->load();
		$statename = $_REQUEST['state'] ? $_REQUEST['state']: "";
		$savedData = Mage::getSingleton('customer/session')->getCustomer()->getData('state');
		$html = "";
		if(count($statearray) == 0) {
			$html .= '<div class="input-box">';	
			$html .= '<input type="text" placeholder="" title="State" class="input-text required-entry" name="state" id="state" value="'.$savedData.'"/>';
			$html .= '</div>';
		} else {
			$html .= '<div class="input-box">';		
			$html .= '<select name="state" id="state" class="input-text required-entry" >';				
			foreach ($statearray as $_state) {
				$selected = "";
				if($savedData == $_state->getDefaultName()) {
					$selected = "selected";
				}
				$html .= '<option value="'.$_state->getDefaultName().'" '.$selected.'>'.$_state->getDefaultName().'</option>';
			}
			$html .= '</select>';
			$html .= '</div>';
		}
		$return = array('html' => $html);
		return $this->getResponse()->setBody(json_encode($return));
	}
	
}
