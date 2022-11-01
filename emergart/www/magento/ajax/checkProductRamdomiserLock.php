<?php

require_once '../app/Mage.php';
umask(0);
Mage::app('default');
ini_set('max_execution_time',180000);
ini_set('memory_limit','1024M'); 


$filename = '/var/www/magento/.lock-random-product';

if (file_exists($filename)) {
    echo file_get_contents($filename);
}
   
?>  
