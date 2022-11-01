<?php

require_once '../app/Mage.php';
umask(0);
Mage::app('default');
ini_set('max_execution_time',180000);
ini_set('memory_limit','1024M'); 



$height=$_REQUEST['height'];
$width=$_REQUEST['width'];
$depth=$_REQUEST['depth'];

$weight=$_REQUEST['weight'];
$price=$_REQUEST['price'];
$packageType=$_REQUEST['packageType'];

$shippingTableName;

if ($packageType == 'tube') {
    $shippingTableName = 'emergart_shipping_rule_tube';
} elseif ($packageType == 'box') {
   $shippingTableName = 'emergart_shipping_rule_box';
} elseif ($packageType == 'crate') {
   $shippingTableName = 'emergart_shipping_rule_crate';
}else{
    echo 'error';
    return;
}
 
$tableName= Mage::getSingleton('core/resource')->getTableName($shippingTableName);

$sqlQuery="SELECT shipping_cost FROM ".$shippingTableName." WHERE max_height>=".$height." AND max_width>=".$width." AND max_depth>=".$depth." AND max_weight>=".$weight. " LIMIT 1";

//Mage::log($sqlQuery, null, 'product-upload.log');

$result1='';
try {
       $chkSystem = Mage::getSingleton('core/resource')->getConnection('core_write')->query($sqlQuery);
	   $result1 = $chkSystem->fetchall();
	}
catch (Exception $e){
    echo $e->getMessage();
}



if (count($result1) == 1){
    echo $result1[0]['shipping_cost'];
} else {
	echo "error";
}
		   
   
?>  