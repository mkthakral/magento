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
  
 
 $tableName= Mage::getSingleton('core/resource')->getTableName('delete_artis');
 
	     
	
		    $sqlPaymentSystem1="SELECT * FROM ".$tableName." WHERE galid='".$galid."' AND artist_id='".$artistid."'";
			try {
				$chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem1);
				$result1 = $chkSystem->fetchall();
			}
			catch (Exception $e){
				 echo $e->getMessage();
			}


 if (count($result1) == 0)
	       {
	      
		   $sqlPaymentSystem="INSERT INTO  ".$tableName." SET artist_id='".$artistid."',galid='".$galid."'";
		     try {
			   $chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlPaymentSystem);
			   echo "This Artist Is Now Hidden :(";
		     }
		     catch (Exception $e){
			//  echo $e->getMessage();
		     }
	       }
		   else
		   {
			   echo "Successfully deleted";
		   }
		   
   
?>  
