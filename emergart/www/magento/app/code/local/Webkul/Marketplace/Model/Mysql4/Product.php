<?php
class Webkul_Marketplace_Model_Mysql4_Product extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
        $this->_init('marketplace/product', 'index_id');
    }
}