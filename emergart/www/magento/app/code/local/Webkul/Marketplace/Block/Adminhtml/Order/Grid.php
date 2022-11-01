<?php
class Webkul_Marketplace_Block_Adminhtml_Order_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
    parent::__construct();
    $this->setId('marketplaceGrid');
    $this->setUseAjax(true);
    
    $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {  
    $helper = Mage::helper('marketplace'); 
    $customerid=$this->getRequest()->getParam('id');
    $saleslist_ids = array();
    $collection1 = Mage::getModel('marketplace/saleslist')->getCollection();
    $collection1->addFieldToFilter('mageproownerid',array('eq'=>$customerid));
    $collection1->addFieldToFilter('parent_item_id',array('null' => 'true' ));
    $collection1->addFieldToFilter('mageorderid',array('neq'=>0));    
    foreach ($collection1 as $value) {
      array_push($saleslist_ids, $value['autoid']);
    }
    $collection = Mage::getModel('marketplace/saleslist')->getCollection();
    $collection->addFieldToFilter('autoid',array('in'=>$saleslist_ids));
    $prefix = Mage::getConfig()->getTablePrefix();
    $collection->getSelect()
        ->join(array("ccp" => $prefix."sales_flat_order"),"ccp.entity_id = main_table.mageorderid",array("status" => "status"))
        ->join(array("ccp2" => $prefix."sales_flat_order_item"),"ccp2.item_id = main_table.order_item_id AND ccp2.order_id = main_table.mageorderid",array("item_id" => "item_id","qty_canceled"=>"qty_canceled","qty_invoiced"=>"qty_invoiced","qty_ordered"=>"qty_ordered","qty_refunded"=>"qty_refunded","qty_shipped"=>"qty_shipped","product_options"=>"product_options","mage_parent_item_id"=>"parent_item_id"));
    $collection->setOrder('mageorderid','desc');
    $this->setCollection($collection);
    parent::_prepareCollection();
    foreach ($collection as $item) {
      $tax_amount = $item['totaltax'];
      $vendor_tax_amount = 0;
      $admin_tax_amount = 0;
      if(Mage::helper('marketplace')->getConfigTaxMange()){
        $vendor_tax_amount = $tax_amount;
      }else{
        $admin_tax_amount = $tax_amount;
      }
      if($item['actualparterprocost']*1){
        $item->actualparterprocost = $item['actualparterprocost']+$vendor_tax_amount;
      }
      if($item['totalcommision']*1){
        $item->totalcommision = $item['totalcommision']+$admin_tax_amount;
      }

      $item->view='<a class="wk_sellerorderstatus" wk_cpprostatus="'.$item->getCpprostatus().'" href="'.$this->getUrl('adminhtml/sales_order/view/',array('order_id'=>$item->getMageorderid())).'" title="'.$helper->__('View Order').'">'.$helper->__('View Order').'</a>';      
      
      $product_name = $item->getMageproname();
      $result = array();
      if ($options = unserialize($item->getProductOptions())) {
          if (isset($options['options'])) {
              $result = array_merge($result, $options['options']);
          }
          if (isset($options['additional_options'])) {
              $result = array_merge($result, $options['additional_options']);
          }
          if (isset($options['attributes_info'])) {
              $result = array_merge($result, $options['attributes_info']);
          }
      }
      if($_options = $result){        
        $pro_option_data = '<dl class="item-options">';
          foreach ($_options as $_option) {
              $pro_option_data .= '<dt>'.$this->escapeHtml($_option['label']).'</dt>';
              if (!$this->getPrintStatus()){
                $_formatedOptionValue = $this->getFormatedOptionValue($_option);
                $class = '';
                if (isset($_formatedOptionValue['full_view'])){ 
                  $class = "truncated"; 
                }
                $pro_option_data .= '<dd class="'.$class.'">'.$this->escapeHtml($_option['value']);
                if (isset($_formatedOptionValue['full_view'])){
                  $pro_option_data .= '<div class="truncated_full_value"><dl class="item-options"><dt>'.$this->escapeHtml($_option['label']).'</dt><dd>'.$_formatedOptionValue['full_view'].'</dd></dl></div>';
                }
                $pro_option_data .= '</dd>';
              }else {
                $pro_option_data .= '<dd>'.nl2br($this->escapeHtml( (isset($_option['print_value']) ? $_option['print_value'] : $_option['value']) )).'</dd>';
              }
          }
        $pro_option_data .= "</dl>";
        $product_name = $product_name."<br/>".$pro_option_data;
      }else{
        $product_name = $product_name."<br/>";
      }
      /*prepare product quantity status*/
      $is_for_item_pay = 0;
      if ($item->getQtyOrdered() > 0){
        $product_name = $product_name.$helper->__('Ordered').": <strong>".($item->getQtyOrdered()*1)."</strong><br />";
      }
      if ($item->getQtyInvoiced() > 0){
        $is_for_item_pay++;
        $product_name = $product_name.$helper->__('Invoiced').": <strong>".($item->getQtyInvoiced()*1)."</strong><br />";
      }
      if ($item->getQtyShipped() > 0){
        $is_for_item_pay++;
        $product_name = $product_name.$helper->__('Shipped').": <strong>".($item->getQtyShipped()*1)."</strong><br />";
      }
      if ($item->getQtyCanceled() > 0){
        $is_for_item_pay=4;
        $product_name = $product_name.$helper->__('Canceled').": <strong>".($item->getQtyCanceled()*1)."</strong><br />";
      }
      if ($item->getQtyRefunded() > 0){
        $is_for_item_pay=3;
        $product_name = $product_name.$helper->__('Refunded').": <strong>".($item->getQtyRefunded()*1)."</strong><br />";
      }
      $item->mageproname = $product_name;

      if(($item->getPaidstatus()==0) && ($item->getCpprostatus()==1) && ($item->getActualparterprocost()!=0)){
        $item->payseller='<button type="button" class="button wk_payseller" auto-id="'.$item->getAutoid().'" title="'.$helper->__('Pay Seller').'"><span><span><span>'.$helper->__('Pay Seller').'</span></span></span></button>';
      }
      else if(($item->getPaidstatus()==0||$item->getPaidstatus()==4) && ($item->getCpprostatus()==0)){
        $item->payseller=$helper->__('Item Pending');
      }else if(($item->getPaidstatus()==0 || $item->getPaidstatus()==4 || $item->getPaidstatus()==2) && ($item->getCpprostatus()==1) && ($item->getStatus()!='complete')){
        $item->payseller=$helper->__('Item Pending');
      }else{
        if($is_for_item_pay==4){
          $item->payseller=$helper->__('Item Cancelled');
        }else if($is_for_item_pay==3){
          $item->payseller=$helper->__('Item Refunded');          
        }else{
          $item->payseller=$helper->__('Already Paid');
        }
      }
    }
  }

  protected function _prepareColumns(){
    $this->addColumn('magerealorderid', array(
      'header'    => Mage::helper('marketplace')->__('Order#'),
      'index'     => 'magerealorderid',
    ));    
    $this->addColumn('cleared_at', array(
      'header'    => Mage::helper('marketplace')->__('Purchased On'),
      'type'      => 'datetime',
      'index'     => 'cleared_at',
    ));
    $this->addColumn('mageproname', array(
      'header'    => Mage::helper('marketplace')->__('Product Name'),
      'index'     => 'mageproname',
      'type'      => 'text'
    ));
    $this->addColumn('magequantity', array(
      'header'    => Mage::helper('marketplace')->__('Quantity to be Paid'),
      'index'     => 'magequantity',
    ));
    $this->addColumn('totalamount', array(
      'header'    => Mage::helper('marketplace')->__('Product Total'),
      'index'     => 'totalamount',
      'currency_code' => $this->getcurrency(),
      'type'      => 'price',
      'column_css_class' => 'wktotalamount',
    ));
    $this->addColumn('totaltax', array(
      'header'    => Mage::helper('marketplace')->__('Total Tax'),
      'index'     => 'totaltax',
      'currency_code' => $this->getcurrency(),
      'type'      => 'price',
      'column_css_class' => 'wktotaltax',
    ));
    $this->addColumn('actualparterprocost', array(
      'header'    => Mage::helper('marketplace')->__('Total Seller Amount'),
      'index'     => 'actualparterprocost',
      'currency_code' => $this->getcurrency(),
      'type'      => 'price',
      'column_css_class' => 'wkactualparterprocost'
    ));
    $this->addColumn('totalcommision', array(
      'header'    => Mage::helper('marketplace')->__('Total Commission'),
      'index'     => 'totalcommision',
      'currency_code' => $this->getcurrency(),
      'type'      => 'price',
      'column_css_class' => 'wktotalcommision',
    ));
    $this->addColumn('status', array(
      'header'    => Mage::helper('marketplace')->__('Status'),
      'index'     => 'status',
      'type'      => 'options',
      'column_css_class' => 'wk_orderstatus',
      'options'   => Mage::getSingleton('sales/order_config')->getStatuses(),
    ));
    $this->addColumn('paidstatus', array(
      'header' => Mage::helper('marketplace')->__('Paid Status'),
      'index' => 'paidstatus',
      'type'  => 'options',
      'column_css_class' => 'wk_paidstatus',
      'options' => $this->getpaidStatuses(),
    ));
    $this->addColumn('view', array(
      'header'    => Mage::helper('marketplace')->__('View'),
      'index'     => 'view',
      'type'      => 'text',
      'filter'    => false,
      'sortable'  => false
    ));
    $this->addColumn('payseller', array(
      'header'    => Mage::helper('marketplace')->__('Pay'),
      'index'     => 'payseller',
      'type'      => 'text',
      'filter'    => false,
      'sortable'  => false
    ));
    return parent::_prepareColumns();
  }

  protected function _prepareMassaction(){
    $this->setMassactionIdField('mageorderid');
    $this->getMassactionBlock()->setFormFieldName('sellerorderids');
    $this->getMassactionBlock()->setUseSelectAll(false);
    $this->getMassactionBlock()->setUseUnSelectAll(false);

    $this->getMassactionBlock()->addItem('pay', array(
     'label'    => Mage::helper('marketplace')->__('Pay'),
     'url'      => $this->getUrl('*/*/masspay'),
     'confirm' => Mage::helper('marketplace')->__('Are you want to make this payment?')
    ));
    return $this;
  }
  public function getGridUrl(){
    return $this->getUrl("*/*/grid",array("_current"=>true));
  }
  public function getRowUrl($row) {
    return '#';
  }
  public function getcurrency(){        
    return (string)Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE);
  }
  public function getpaidStatuses(){
    return array('0'=>Mage::helper('marketplace')->__('Pending'),'1'=>Mage::helper('marketplace')->__('Paid'),'2'=>Mage::helper('marketplace')->__('Hold'),'3'=>Mage::helper('marketplace')->__('Refunded'),'4'=>Mage::helper('marketplace')->__('Voided'));
  }
}