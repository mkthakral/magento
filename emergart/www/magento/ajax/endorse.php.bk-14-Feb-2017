<?php

require_once '../app/Mage.php';
//Mage::app();
//Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
umask(0);
Mage::app('default');
 ini_set('max_execution_time',180000);
ini_set('memory_limit','1024M'); 



  $proid=$_REQUEST['proid'];
  $galid=$_REQUEST['galid'];
  $artistid=$_REQUEST['artid'];
  $storeid=1;
  
  $galdata=Mage::getModel('customer/customer')->load($galid);
  $artdata=Mage::getModel('customer/customer')->load($artistid);
  
  $galemail=$galdata->getEmail();
  $artemail=$artdata->getEmail();
 
 $product = Mage::getModel('catalog/product')->load($proid);
  Mage::getModel('catalog/product_status')->updateProductStatus($proid, $storeid, Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
  $action=Mage::getModel('catalog/resource_product_action');
$action->updateAttributes(array($proid), array('galcode' =>$galid), $storeid);



$fromemail=$galemail;
$usertype=$data['type'];
$name=$galdata->getName();
$to_email =$artemail; //fetch sender email Admin
$itemname= $product->getName();

   
    $from = $fromemail;
    $to = $to_email;
    $subject = "Product endorse by $name";
    $message = "$name is endorse your portfolio $itemname";
    $headers = "From:" . $from;
    mail($to,$subject,$message, $headers);
		   
   $tableName= Mage::getSingleton('core/resource')->getTableName('like_art');
 
	     
	
		    $sqlPaymentSystem1="SELECT * FROM ".$tableName." WHERE galid='".$galid."' AND proid='".$proid."'";
			try {
				$chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem1);
				$result1 = $chkSystem->fetchall();
			}
			catch (Exception $e){
				 echo $e->getMessage();
			}


 if (count($result1) == 0)
	       {
	      
		   $sqlPaymentSystem="INSERT INTO  ".$tableName." SET proid='".$proid."',galid='".$galid."'";
		     try {
			   $chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem);
			   echo "Successfully submit to gallery";
		     }
		     catch (Exception $e){
			//  echo $e->getMessage();
		     }
	       }
		   else
		   {
			   echo "already like by you";
		   }
		   
		   $tableName1= Mage::getSingleton('core/resource')->getTableName('follow_art');
 
	     
	
		    $sqlPaymentSystem1="SELECT * FROM ".$tableName1." WHERE galid='".$galid."' AND artistid='".$artistid."'";
			try {
				$chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem1);
				$result1 = $chkSystem->fetchall();
			}
			catch (Exception $e){
				 echo $e->getMessage();
			}


 if (count($result1) == 0)
	       {
	      
		   $sqlPaymentSystem="INSERT INTO  ".$tableName1." SET artistid='".$artistid."',galid='".$galid."'";
		     try {
			   $chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem);
			   echo "Successfully follow by you";
		     }
		     catch (Exception $e){
			//  echo $e->getMessage();
		     }
	       }
		   else
		   {
			   echo "already follow by you";
		   }
?>  