<?php
/**
 * Webkul Marketplace Orders Block
 *
 * @category    Webkul
 * @package     Webkul_Marketplace
 * @author      Webkul Software Private Limited
 *
 */
class Webkul_Marketplace_Block_Orders extends Mage_Core_Block_Template
{
    protected $_links = array();

    public function __construct(){
        parent::__construct();
        $ids = array();
        $ordeids = array();
        $filter_orderid = '';
        $filter_orderstatus = '';
        $filter_data_to = '';
        $filter_data_frm = '';
        $from = null;
        $to = null;

        if(isset($_GET['s'])){
            $filter_orderid = $_GET['s'] != ""?$_GET['s']:"";
        }
        if(isset($_GET['orderstatus'])){
            $filter_orderstatus = $_GET['orderstatus'] != ""?$_GET['orderstatus']:"";
        }
        if(isset($_GET['from_date'])){
            $filter_data_frm = $_GET['from_date'] != ""?$_GET['from_date']:"";
        }
        if(isset($_GET['to_date'])){
            $filter_data_to = $_GET['to_date'] != ""?$_GET['to_date']:"";
        }    

        $userid = Mage::getSingleton('customer/session')->getId();
        $collection_orders = Mage::getModel('marketplace/saleslist')->getCollection()
                                ->addFieldToFilter('mageproownerid',array('eq'=>$userid))
                                ->addFieldToSelect('mageorderid')
                                ->distinct(true);
        foreach ($collection_orders as $collection_order) {       
            if($filter_orderstatus){
                $tracking=Mage::getModel('marketplace/order')->getOrderinfo($collection_order->getMageorderid());
                if($tracking->getIsCanceled()){
                    if($filter_orderstatus=='canceled'){
                        array_push($ordeids, $collection_order->getMageorderid());
                    }
                }else{
                    $tracking = Mage::getModel('sales/order')->load($collection_order->getMageorderid());
                
                    if($tracking->getStatus()==$filter_orderstatus){
                        array_push($ordeids, $collection_order->getMageorderid());
                    }
                }
            }else{
                array_push($ordeids, $collection_order->getMageorderid());
            }
        }

        foreach ($ordeids as $ordeid) {
            $collection_ids = Mage::getModel('marketplace/saleslist')->getCollection()
                            ->addFieldToFilter('mageorderid',array('eq'=>$ordeid))
                            ->setOrder('autoid','DESC')
                            ->setPageSize(1);
            foreach ($collection_ids as $collection_id) {
                $autoid = $collection_id->getAutoid();
            }
            array_push($ids, $autoid);
        }

        if($filter_data_to){
            $todate = date_create($filter_data_to);
            $to = date_format($todate, 'Y-m-d 23:59:59');
        }
        if($filter_data_frm){
            $fromdate = date_create($filter_data_frm);
            $from = date_format($fromdate, 'Y-m-d H:i:s');
        }

        $collection = Mage::getModel('marketplace/saleslist')->getCollection();
        $collection->addFieldToFilter('autoid',array('in'=>$ids))
                   ->addFieldToFilter('cleared_at', array('datetime' => true,'from' => $from,'to' =>  $to));
        if($filter_orderid){
            $collection->addFieldToFilter('magerealorderid', array('in' => $filter_orderid));
        }                   
        $collection->setOrder('autoid','AESC');
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

    /**
     * Retrieve current order model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    public function getLinks()
    {
        $this->checkLinks();
        return $this->_links;
    }

    private function checkLinks()
    {
        $order = $this->getOrder();
        $order_id = $order->getId();
        $tracking=Mage::getModel('marketplace/order')->getOrderinfo($order->getId());
        if($tracking!=""){
            $shipmentId = $tracking->getShipmentId();
            $invoiceId=$tracking->getInvoiceId();
            $creditmemo_id=$tracking->getCreditmemoId();
        }
        if (!$order->hasInvoices()) {
            unset($this->_links['invoice']);
        }else{
            if($invoiceId){
                $this->_links['invoice'] = new Varien_Object(array(
                    'name' => 'invoice',
                    'label' => Mage::helper('marketplace')->__('Invoices'),
                    'url' => Mage::getUrl('marketplace/order_invoice/view', array('order_id'=>$order_id,'invoice_id'=>$invoiceId))
                ));
            }
        }
        if (!$order->hasShipments()) {
            unset($this->_links['shipment']);
        }else{
            if($shipmentId){
                $this->_links['shipment'] = new Varien_Object(array(
                    'name' => 'shipment',
                    'label' => Mage::helper('marketplace')->__('Shipments'),
                    'url' => Mage::getUrl('marketplace/order_shipment/view', array('order_id'=>$order_id,'shipment_id'=>$shipmentId))
                ));
            }
        }
        if (!$order->hasCreditmemos()) {
            unset($this->_links['creditmemo']);
        }else{
            if($creditmemo_id){
                $this->_links['creditmemo'] = new Varien_Object(array(
                    'name' => 'creditmemo',
                    'label' => Mage::helper('marketplace')->__('Refunds'),
                    'url' => Mage::getUrl('marketplace/order_creditmemo/viewlist', array('order_id'=>$order_id))
                ));
            }
        }
    }

    protected function _isAllowedAction($action)
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/' . $action);
    }
}
