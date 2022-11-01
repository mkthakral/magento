<?php

require_once '../app/Mage.php';
//Mage::app();
//Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
umask(0);
Mage::app('default');
 ini_set('max_execution_time',180000);
ini_set('memory_limit','1024M'); 
 $artemail=$_REQUEST['artmail'];
 $galemail=$_REQUEST['galmail'];
 $artmsg=$_REQUEST['msg']; 
 $artcustomer = Mage::getModel("customer/customer")->setWebsiteId(Mage::app()->getWebsite()->getId())->loadByEmail($artemail);
 $galcustomer = Mage::getModel("customer/customer")->setWebsiteId(Mage::app()->getWebsite()->getId())->loadByEmail($galemail);
 $artid=$artcustomer->getId();
 $galid=$galcustomer->getId();
 $curenttimestamp=date("Y-m-d H:i:s");

 $tableName= Mage::getSingleton('core/resource')->getTableName('gallery_message');
 
  $sqlPaymentSystem="INSERT INTO  ".$tableName." SET artist_id='".$artid."',gallery_id='".$galid."',message='".addslashes($artmsg)."'";
		     try {
			   $chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem);
			  
		     }
		     catch (Exception $e){
			//  echo $e->getMessage();
		     }

    
	 $emailTemplate = Mage::getModel('core/email_template')->loadByCode('Gallery Message to Artist');
	 $emailTemplateVariables = array();
	 $emailTemplateVariables['galleryName'] = $galcustomer->getGaleryname();
	 $emailTemplateVariables['artistName'] = $_REQUEST['artistName'];
	 $emailTemplateVariables['message'] = $artmsg;
	 $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
	 $emailTemplate->setSenderName('Emergart');
	 $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'));
	 $emailTemplate->setType('html');
	 $emailTemplate->setBody($processedTemplate);
	 $emailTemplate->send($artemail,$_REQUEST['artistName'], $emailTemplateVariables);
?>
