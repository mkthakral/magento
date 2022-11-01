<?php
/**
 * Webkul Marketplace Product Block
 *
 * @category    Webkul
 * @package     Webkul_Marketplace
 * @author      Webkul Software Private Limited
 *
 */
class Webkul_Marketplace_Block_Marketplace extends Mage_Customer_Block_Account_Dashboard
{
	/**
     * Seller's Product Collection
     *
     * @var Mage_Eav_Model_Entity_Collection_Abstract
     */
	protected $_productsCollection = null;

	public function __construct(){		
		parent::__construct();	
		$filter = '';
		$filter_prostatus = '';
		$filter_data_frm = '';
		$filter_data_to = '';
		$from = null;
		$to = null;
    	$userId=Mage::getSingleton('customer/session')->getCustomer()->getId();
		$collection = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('userid',array('eq'=>$userId));
		$products=array();
		foreach($collection as $data){
			array_push($products,$data->getMageproductid());
		}
		if(isset($_GET['s'])){
            $filter = $_GET['s'] != ""?$_GET['s']:"";
		}
		if(isset($_GET['prostatus'])){
            $filter_prostatus = $_GET['prostatus'] != ""?$_GET['prostatus']:"";
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

		$collection = Mage::getModel('catalog/product')->getCollection()
						   ->addAttributeToSelect('*')
						   ->addFieldToFilter('name',array('like'=>"%".$filter."%"));
		if($filter_prostatus){
			$collection->addFieldToFilter('status',array('like'=>"%".$filter_prostatus."%"));
		}
						  
		$collection->addFieldToFilter('created_at', array('datetime' => true,'from' => $from,'to' =>  $to))
				   ->addFieldToFilter('entity_id',array('in'=>$products))
				   ->setOrder('entity_id','DESC');
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
	
	/**
     * @return pager html block
     */
    public function getPagerHtml() {
        return $this->getChildHtml('pager');
    }

    /**
     * Retrieve current product model instance
     *
     * @return Mage_Catalog_Model_Product
     */
	public function getProduct() {
		$id = $this->getRequest()->getParam('id');
		$products = Mage::getModel('catalog/product')->load($id);
		return $products;
	}
}
