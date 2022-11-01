<?php
class Webkul_Marketplace_Block_Adminhtml_Commisions_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
      parent::__construct();
      $this->setId('marketplaceGrid');
	  $this->setUseAjax(true);
      $this->setDefaultSort('entity_id');
      $this->setSaveParametersInSession(true);
  }

   protected function _prepareCollection()     {
       // $global_comm_percent = 20;
        $prefix = Mage::getConfig()->getTablePrefix();
        $fnameid = Mage::getModel("eav/entity_attribute")->loadByCode("1", "firstname")->getAttributeId();
        $lnameid = Mage::getModel("eav/entity_attribute")->loadByCode("1", "lastname")->getAttributeId();
        $collection = Mage::getModel("marketplace/saleperpartner")->getCollection();
        $collection->getSelect()
        ->join(array("ce1" => $prefix."customer_entity_varchar"),"ce1.entity_id = main_table.mageuserid",array("fname" => "value"))->where("ce1.attribute_id = ".$fnameid)
        ->join(array("ce2" => $prefix."customer_entity_varchar"),"ce2.entity_id = main_table.mageuserid",array("lname" => "value"))->where("ce2.attribute_id = ".$lnameid)
        ->columns(new Zend_Db_Expr("CONCAT(`ce1`.`value`, ' ',`ce2`.`value`) AS fullname"));
        $collection->addFilterToMap("fullname","`ce1`.`value`");
        $collection->getSelect()
        ->join(array("em" => $prefix."customer_entity"),"em.entity_id = main_table.mageuserid",array("email" => "email","created_at" => "created_at"));
        $collection->getSelect()->join(array("cpu" => $prefix."marketplace_userdata"),"cpu.mageuserid = main_table.mageuserid",array("partnerstatus"=>"partnerstatus","paymentsource"=>"paymentsource"));
        $this->setCollection($collection);
        parent::_prepareCollection(); 
        $helper = Mage::helper('marketplace');
        foreach ($collection as $data) {     
            $data->partnerstatus=Mage::helper('marketplace')->__($data->getPartnerstatus());       
            $data->fullname=sprintf('<a href="%s" title="'.$helper->__('View Customer').'">%s</a>',
                                             $this->getUrl("adminhtml/customer/edit",array("id"=>$data->getMageuserid())),$data->getFullname());
            $view_order_content = Mage::helper('marketplace')->__('View Order');
            $data->order=sprintf('<a href="%s" title="'.$view_order_content.'">'.$view_order_content.'</a>',
                                             $this->getUrl('marketplace/adminhtml_order/index/id/'.$data->getMageuserid().'/')
                                            );
        }             
    }

  protected function _prepareColumns(){
	  $currency = (string) Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE);
       $this->addColumn('entity_id', array(
            'header'    => Mage::helper('marketplace')->__('ID'),
            'width'     => '50px',
            'index'     => 'mageuserid',
            'type'  => 'number',
            'filter_index' => 'main_table.mageuserid'
        ));
        $this->addColumn('name', array(
            'header'    => Mage::helper('marketplace')->__('Name'),
            'type'  => 'text',
            'index'     => 'fullname',
        ));
        $this->addColumn('email', array(
            'header'    => Mage::helper('marketplace')->__('Email'),
            'width'     => '150',
            'index'     => 'email',
        ));
		$this->addColumn('wantpartner', array(
            'header'    => Mage::helper('marketplace')->__('Status'),
            'index'     => 'partnerstatus',
        ));
		$this->addColumn('commision', array(
            'header'    => Mage::helper('marketplace')->__('Commission %'),
            'index'     => 'commision',
        ));
		$this->addColumn('totalsale', array(
            'header'    => Mage::helper('marketplace')->__('Total sales'),
            'index'     => 'totalsale',
            'currency_code' => $currency,
            'type'  => 'price',
        ));
		$this->addColumn('amountrecived', array(
            'header'    => Mage::helper('marketplace')->__('Amount Received'),
            'index'     => 'amountrecived',
            'currency_code' => $currency,
            'type'  => 'price',
        ));
		$this->addColumn('amountremain', array(
            'header'    => Mage::helper('marketplace')->__('Amount Remain'),
            'index'     => 'amountremain',
            'currency_code' => $currency,
            'type'  => 'price',
        ));
		$this->addColumn('amountpaid', array( 
            'header'    => Mage::helper('marketplace')->__('Last Pay Amount'),
            'index'     => 'amountpaid',
            'currency_code' => $currency,
            'type'  => 'price',
        ));		
        $this->addColumn('customer_since', array(
            'header'    => Mage::helper('marketplace')->__('Seller Since'),
            'type'      => 'datetime',
            'align'     => 'center',
            'index'     => 'created_at',
        ));
		
        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('website_id', array(
                'header'    => Mage::helper('marketplace')->__('Website'),
                'align'     => 'center',
                'width'     => '80px',
                'type'      => 'options',
                'options'   => Mage::getSingleton('adminhtml/system_store')->getWebsiteOptionHash(true),
                'index'     => 'website_id',
            ));
        }
        $this->addColumn('order', array(
            'header'    => Mage::helper('marketplace')->__('Orders'),
            'index'     => 'order',
            'type'      => 'text',
            "filter"    => false,
            "sortable"  => false
        ));
        return parent::_prepareColumns();
    }

	public function getGridUrl(){
		return $this->getUrl("*/*/grid",array("_current"=>true));
	}	
}
