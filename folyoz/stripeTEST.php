<?php
require_once 'app/Mage.php';
umask(0) ;  
Mage::app();

$id = $_GET['id'];
$subscription = Mage::getModel('customer/customer')->checkRecurring($id); // check user user subscription form stripe api
print_r($subscription);
?> 