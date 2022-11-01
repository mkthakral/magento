<?php
class Webkul_Marketplace_Model_Feedbackcount extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('marketplace/feedbackcount');
    }
}