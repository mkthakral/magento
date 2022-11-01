<?php
class Webkul_Marketplace_Model_Sellertransaction extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('marketplace/sellertransaction');
    }
}