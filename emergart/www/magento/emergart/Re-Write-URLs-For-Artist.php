<?php
require_once '../app/Mage.php';
umask(0);
Mage::app('default');
ini_set('max_execution_time',360000);
ini_set('memory_limit','2048M'); 

$collection = Mage::getModel('customer/customer')
        ->getCollection()
        ->addAttributeToSelect('*')
		->addAttributeToFilter('isapproved',1)
        ->addFieldToFilter('group_id', 3);
        
//$i=0;
	//	echo"<pre>";print_r($collection->getData());echo"</pre>";
		foreach($collection  as $customer)
		{
			$artistCustomerId = $customer->getentityId();
			//Mage::log('Artist Name: '.$customer->getName(), null, 'rewrite-artist-urls.log');
			//Mage::log('Artist Id: '.$artistCustomerId, null, 'rewrite-artist-urls.log');
			
			
			$artistRewriteUniquePattern = "artist-profile-id-$artistCustomerId";
			Mage::log('artistRewriteUniquePattern: '.$artistRewriteUniquePattern, null, 'rewrite-artist-urls.log');
			
			$newProfilePageURL = trim(strtolower($customer->getFirstname()),' ').'_'.trim(strtolower($customer->getLastname()),' ');
			$newProfilePageURL = str_replace(' ','',$newProfilePageURL);
			$newProfilePageURL = 'artist/'.$newProfilePageURL;
			//Mage::log('newProfilePageURL: '.$newProfilePageURL, null, 'rewrite-artist-urls.log');
			
			$rewrite = Mage::getModel('core/url_rewrite')->loadByIdPath($artistRewriteUniquePattern);
			
			if ($rewrite->getId()){
				$rewrite->setRequestPath("".$newProfilePageURL);
				$rewrite->save();
			}else{
				$rewrite = Mage::getModel('core/url_rewrite');
				 $rewrite->setStoreId(Mage::app()->getStore()->getStoreId());
 				 //$rewrite->setOptions('RP');
				 $rewrite->setIdPath($artistRewriteUniquePattern);
				 $rewrite->setRequestPath("".$newProfilePageURL);
				 $rewrite->setIsSystem(0);
				 $rewrite->setTargetPath('/custom/index/artist/id/'.$artistCustomerId);
				 $rewrite->save();
			}
			
		
		} ?>
