<?php

require_once '../app/Mage.php';
//Mage::app();
//Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
umask(0);
Mage::app('default');
 ini_set('max_execution_time',180000);
ini_set('memory_limit','1024M'); 



   $artistid=$_REQUEST['artistid'];
   $galid=$_REQUEST['galid'];
  
 
 $tableName= Mage::getSingleton('core/resource')->getTableName('follow_art');
 
	     
	
		    $sqlPaymentSystem1="DELETE FROM ".$tableName." WHERE galid='".$galid."' AND artistid='".$artistid."'";
			try {
				$chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem1);
				echo "You No Longer Watch this Artist :(";
			}
			catch (Exception $e){
				 echo $e->getMessage();
			}


 
		   
   
?>  
