<?php
class Webkul_Marketplace_Block_Transactions extends Mage_Core_Block_Template
{
	protected $_productsCollection = null;
	public function __construct(){		
		parent::__construct();	
        $filter_data_to = '';
        $filter_data_frm = '';
        $transid = '';
        $from = null;
        $to = null;
    	$id = Mage::getSingleton('customer/session')->getId();
        if(isset($_GET['transid'])){
            $transid = $_GET['transid'] != ""?$_GET['transid']:"";
        }
        if(isset($_GET['from_date'])){
            $filter_data_frm = $_GET['from_date'] != ""?$_GET['from_date']:"";
        }
        if(isset($_GET['to_date'])){
            $filter_data_to = $_GET['to_date'] != ""?$_GET['to_date']:"";
        }
        if($filter_data_to){
            $todate = date_create($filter_data_to);
            $to = date_format($todate, 'Y-m-d 23:59:59');
        }
        if($filter_data_frm){
            $fromdate = date_create($filter_data_frm);
            $from = date_format($fromdate, 'Y-m-d H:i:s');
        }
        $collection = Mage::getModel('marketplace/sellertransaction')->getCollection();
        $collection->addFieldToFilter('sellerid',array('eq'=>$id));
        if($transid){
            $collection->addFieldToFilter('transactionid', array('eq' => $transid));
        }
        if($from || $to){
            $collection->addFieldToFilter('created_at', array('datetime' => true,'from' => $from,'to' =>  $to));
        }
        $collection->setOrder('transid');
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
    public function getremaintotal(){
        $id = Mage::getSingleton('customer/session')->getId();
        $collection = Mage::getModel('marketplace/saleperpartner')->getCollection();
        $collection->addFieldToFilter('mageuserid',array('eq'=>$id));
        $total=0;
        foreach($collection as $key){ 
            $total=$key->getAmountremain();
        }
        if($total<0){
            $total=0;
        }
        return $total;
    }
}
