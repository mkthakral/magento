<?php
class Webkul_Marketplace_Model_Mysql4_Saleslist extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
        $this->_init('marketplace/saleslist', 'autoid');
    }
}