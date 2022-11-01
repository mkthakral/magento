<?php
/**
 * Webkul Marketplace Mysql4 Order abstract
 *
 * @category    Webkul
 * @package     Webkul_Marketplace
 * @author      Webkul Software Private Limited 
 */
class Webkul_Marketplace_Model_Mysql4_Order extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
        // Note that the id refers to the key field in your database table.
        $this->_init('marketplace/order', 'id'); 
    }
}
