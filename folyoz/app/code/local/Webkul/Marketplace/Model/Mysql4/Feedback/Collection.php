<?php
class Webkul_Marketplace_Model_Mysql4_Feedback_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('marketplace/feedback');
    }
}