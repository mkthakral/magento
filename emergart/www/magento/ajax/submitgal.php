<?php

require_once '../app/Mage.php';
//Mage::app();
//Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
umask(0);
Mage::app('default');
 ini_set('max_execution_time',180000);
ini_set('memory_limit','1024M'); 



   $artid=$_REQUEST['artid'];
   $galid=$_REQUEST['galid'];
   $curdate=$_REQUEST['curdate'];
   
   $customerdata=Mage::getModel('customer/customer')->load($artid);
   $name=$customerdata->getName();
 
 $tableName= Mage::getSingleton('core/resource')->getTableName('artis_submit');
 
	     
	
		    $sqlPaymentSystem1="SELECT * FROM ".$tableName." WHERE gallery_id='".$galid."' AND artist_id='". $artid."'";
			try {
				$chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem1);
				$result1 = $chkSystem->fetchall();
			}
			catch (Exception $e){
				 echo $e->getMessage();
			}


 if (count($result1) == 0)
	       {
	      
		   $sqlPaymentSystem="INSERT INTO  ".$tableName." SET artist_id='".$artid."',name='".$name."',gallery_id='".$galid."',created_date='".$curdate."'";
		     try {
			   $chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem);
			   echo "Submitted Succesfully";
		     }
		     catch (Exception $e){
			//  echo $e->getMessage();
		     }
	       }
		   else
		   {
			   echo "already submit to gallery";
		   }
		   
   
?>  
