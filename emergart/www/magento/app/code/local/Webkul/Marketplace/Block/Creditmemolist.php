<?php
class Webkul_Marketplace_Block_Creditmemolist extends Mage_Core_Block_Template
{
	public function __construct() {
        parent::__construct();	
		$collection=$this->getcreditmemoDetail();
        $this->setCollection($collection);
    }
	protected function _prepareLayout() {
        parent::_prepareLayout(); 
        $pager = $this->getLayout()->createBlock('page/html_pager', 'custom.pager');
        $grid_per_page_values = explode(",",Mage::helper('marketplace')->getCatatlogGridPerPageValues());
        $arr_perpage = array();
        foreach ($grid_per_page_values as $value) {
        	$arr_perpage[$value] = $value;
        }
        $pager->setAvailableLimit($arr_perpage);
        $pager->setCollection($this->getCollection());
        $this->setChild('pager', $pager);
        $this->getCollection()->load();
        return $this;
    }
	public function getPagerHtml() {
        return $this->getChildHtml('pager');
    }
    
	private function getcreditmemoDetail(){
		$order_id = $this->getRequest()->getParam('order_id');
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$tracking=Mage::getModel('marketplace/order')->getOrderinfo($order_id);
		$creditmemo_ids=array();		
		$creditmemo_ids = explode(',', $tracking->getCreditmemoId());
		try{			
			$creditmemo = Mage::getModel('sales/order_creditmemo')->getCollection()
							->addFieldToFilter('entity_id', array('in' => $creditmemo_ids));
		}
		catch(Exception $e){
			return $e->getMessage();
		}
	    return $creditmemo;
	}
}
