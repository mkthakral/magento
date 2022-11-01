<?php

require_once '../app/Mage.php';
//Mage::app();
//Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
umask(0);
Mage::app('default');
 ini_set('max_execution_time',180000);
ini_set('memory_limit','1024M'); 



    $email=$_REQUEST['email'];
    $adminemail=$_REQUEST['adminemail'];
    $ques=$_REQUEST['ques'];
    $msg=$_REQUEST['msg'];
    $customerGroup=$_REQUEST['custGroup'];

 

	$emailTemplate = Mage::getModel('core/email_template')->loadByCode('Account Help - Contact Emergart');
	$emailTemplateVariables = array();
	$emailTemplateVariables['cust_group'] = $customerGroup;
	$emailTemplateVariables['issue_type'] = $ques;
	$emailTemplateVariables['msg'] = $msg;
        $emailTemplateVariables['cust_email'] = $email;
	$processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
	$emailTemplate->setSenderName('Emergart');
	$emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'));
	$emailTemplate->setType('html');
	$emailTemplate->setBody($processedTemplate);
	$emailTemplate->send($adminemail,'Emergart', $emailTemplateVariables);

?>  
