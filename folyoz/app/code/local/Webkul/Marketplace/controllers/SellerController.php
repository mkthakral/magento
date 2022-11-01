<?php
class Webkul_Marketplace_SellerController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
		$this->loadLayout();
		$this->renderLayout();
    }

    public function listAction(){
		$marketplacelabel=Mage::helper('marketplace')->getMarketplaceHeadLabel();
		$this->loadLayout(array('default','marketplace_seller_list'));
		$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__($marketplacelabel));
		$this->renderLayout();
    }

	public function profileAction(){
		$id = 0;
		$profileurl = Mage::helper('marketplace')->getProfileUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('wantpartner',array('eq'=>1))
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ 
				$id = $seller->getAutoid();
			}
		}
		if($id){
			$this->loadLayout();     
			$this->renderLayout();
		}else{
			$this->_redirect("marketplace/index");
		}		
	}
	public function collectionAction(){
		$id = 0;		
		$profileurl = Mage::helper('marketplace')->getCollectionUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('wantpartner',array('eq'=>1))
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ 
				$id =$seller->getAutoid();
			}
		}
		if($id){
			$this->loadLayout();     
			$this->renderLayout();
		}else{
			$this->_redirect("marketplace/index");
		}
	}
	public function locationAction(){
		$id = 0;
		$profileurl = Mage::helper('marketplace')->getLocationUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('wantpartner',array('eq'=>1))
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ 
				$id =$seller->getAutoid();
			}
		}
		if($id){
			$this->loadLayout();     
			$this->renderLayout();
		}else{
			$this->_redirect("marketplace/index");
		}
	}
	public function feedbackAction(){
		$id = 0;
		$profileurl = Mage::helper('marketplace')->getFeedbackUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('wantpartner',array('eq'=>1))
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ 
				$id =$seller->getAutoid();
			}
		}
		if($id){
			$this->loadLayout();     
			$this->renderLayout();
		}else{
			$this->_redirect("marketplace/index");
		}
	}
	public function usernameverifyAction(){
		$profileurl=$this->getRequest()->getParam('profileurl');
		$collection=Mage::getModel('marketplace/userprofile')->getCollection()
							->addFieldToFilter('wantpartner',array('eq'=>1))
							->addFieldToFilter('profileurl',array('eq'=>$profileurl));
		$this->getResponse()->setHeader('Content-type', 'text/html');
		$this->getResponse()->setBody(count($collection));
	}
	public function sendmailAction(){		
		$data = $this->getRequest()->getParams();
		if($data['seller-id']){
			Mage::dispatchEvent('mp_send_querymail', $data);
			if(!isset($data['product-id'])){
				$data['product-id'] = 0 ;
			}
			if($data['product-id']){
				$emailTemplate = Mage::getModel('core/email_template')->loadDefault('querypartner_email');
			}
			else{
				$emailTemplate = Mage::getModel('core/email_template')->loadDefault('askquerypartner_email');
			}
			if(Mage::helper('customer')->isLoggedIn()){
				$buyer_name = Mage::getSingleton('customer/session')->getCustomer()->getName();
				$buyer_email = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
			}else{				
				$buyer_email = $data['email'];
				$buyer_name = $data['name'];
				if(strlen($buyer_name)<2){
					$buyer_name="Guest";
				}
			}
			$emailTemplateVariables = array();
			$seller=Mage::getModel('customer/customer')->load($data['seller-id']);
			$emailTemplateVariables['myvar1'] =$seller->getName();
			$sellerEmail = $seller->getEmail();
			$emailTemplateVariables['myvar3'] =Mage::getModel('catalog/product')->load($data['product-id'])->getName();
			$emailTemplateVariables['myvar4'] =$data['ask'];
			$emailTemplateVariables['myvar6'] =$data['subject'];
			$emailTemplateVariables['myvar5'] =$buyer_email;
			
			$processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
			$emailTemplate->setSenderName($buyer_name);
			$emailTemplate->setSenderEmail($buyer_email);
			$emailTemplate->send($sellerEmail,$seller->getName(),$emailTemplateVariables);
		}
		$this->getResponse()->setHeader('Content-type', 'text/html');
		$this->getResponse()->setBody(json_encode("true"));
    }
	public function newfeedbackAction(){
		 if (!$this->_validateFormKey()) {
           return $this->_redirect('marketplace/marketplaceaccount/myproductslist/');
         }
		$wholedata=$this->getRequest()->getPost();
		Mage::getModel('marketplace/feedback')->saveFeedbackdetail($wholedata);
		Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Your Review was successfully saved'));
		$this->_redirect("marketplace/seller/feedback/".$wholedata['profileurl'].'/.');		
	}
}
