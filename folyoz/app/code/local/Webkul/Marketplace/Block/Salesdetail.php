<?php
class Webkul_Marketplace_Block_Salesdetail extends Mage_Core_Block_Template
{
	public function __construct() {
        parent::__construct();	
		$collection=$this->getOrderDetail();
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
    
	private function getOrderDetail(){
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$trackingCollection=Mage::getModel('marketplace/order')->getCollection();
		$mytrackingarray=array();
		foreach($trackingCollection as $key => $tcc){
			if($tcc->getItemIds()){
			 	array_push($mytrackingarray , $tcc->getOrderId());
			}
		}
		try{
			
		$orderslist=Mage::getModel('marketplace/saleslist')->getCollection()
										 ->addFieldToFilter('mageproownerid',array('eq'=>$customerid))
										 ->addFieldToFilter('mageproid',array('eq'=>$this->getRequest()->getParam('id')))
										 ->addFieldToFilter('mageorderid', array('in' => $mytrackingarray))
										 ->setOrder('autoid','DESC');
		}
		catch(Exception $e){
			echo $e->getMessage();
		}
	    return $orderslist;
	}
}
