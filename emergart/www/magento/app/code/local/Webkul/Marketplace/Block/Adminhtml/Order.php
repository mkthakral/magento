<?php
class Webkul_Marketplace_Block_Adminhtml_Order extends Mage_Adminhtml_Block_Widget_Grid_Container
{
	public function __construct(){
        $this->_controller = 'adminhtml_order';
        $this->_headerText = Mage::helper('marketplace')->__("Manage Seller's Order");
        $this->_blockGroup = 'marketplace';
        parent::__construct();
        $this->_removeButton('add');
		$this->_removeButton('reset_filter_button');
		$this->_removeButton('search_button');   
    }	
}
