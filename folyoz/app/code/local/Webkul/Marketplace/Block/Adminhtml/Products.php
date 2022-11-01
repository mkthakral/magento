<?php
class Webkul_Marketplace_Block_Adminhtml_Products extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {	
	$this->_controller = 'adminhtml_products';
        $this->_headerText = Mage::helper('marketplace')->__("Manage Seller's Product");
        $this->_blockGroup = 'marketplace';
        parent::__construct();
        $this->_removeButton('add');
        $this->_addButton('suo', array(
                'label'     => Mage::helper('marketplace')->__('Show Unapproved Only'),
                'onclick'   => 'setLocation(\'' . $this->getShowUnapprovedOnlyUrl() .'\')',
                'class'     => '',
        ));
  }
  
  public function getShowUnapprovedOnlyUrl(){
		return $this->getUrl('*/*/index/unapp/1');
	}
}
