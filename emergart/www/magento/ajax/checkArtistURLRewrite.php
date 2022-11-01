<?php

require_once '../app/Mage.php';
umask(0);
Mage::app('default');
ini_set('max_execution_time',180000);
ini_set('memory_limit','1024M'); 



$profilePageURL=$_REQUEST['profilePageURL'];
$artistId=$_REQUEST['artistId'];

//Mage::log('profilePageURL: '.$profilePageURL, null, 'url-rewrite.log');

//check if url exists
$rewrite = Mage::getModel('core/url_rewrite')->setStoreId(Mage::app()->getStore()->getId())->loadByRequestPath($profilePageURL);



//Create error only when found rewrite belongs to someone else, not this artist
if($rewrite->getIdPath() != 'artist-profile-id-'.$artistId){
	//Mage::log('Ids are NOT matching', null, 'url-rewrite.log');
	echo $rewrite->getTargetPath();
}

//Mage::log('Rewrite: '.$rewrite->getTargetPath(), null, 'url-rewrite.log');
//Mage::log('ID: '.$rewrite->getIdPath(), null, 'url-rewrite.log');

   
?>  