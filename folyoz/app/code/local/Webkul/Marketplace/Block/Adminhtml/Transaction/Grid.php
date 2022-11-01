<?php
class Webkul_Marketplace_Block_Adminhtml_Transaction_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
	public function __construct()
	{
		  parent::__construct();
		  $this->setId('marketplaceGrid');
		  $this->setDefaultSort('autoid');
		  $this->setDefaultDir('ASC');
		  $this->setSaveParametersInSession(true);
		  $this->setUseAjax(true);
          $this->setVarNameFilter('transaction_filter');
	}

	protected function _prepareCollection(){
		$collection = Mage::getModel('marketplace/sellertransaction')->getCollection();
		
        $prefix = Mage::getConfig()->getTablePrefix();
        $eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
        $fnameid = Mage::getModel("eav/entity_attribute")->loadByCode("1", "firstname")->getAttributeId();
        $lnameid = Mage::getModel("eav/entity_attribute")->loadByCode("1", "lastname")->getAttributeId();
        $collection->getSelect()
                ->join(array("ce1" => $prefix."customer_entity_varchar"),"ce1.entity_id = main_table.sellerid",array("fname" => "value"))->where("ce1.attribute_id = ".$fnameid)
                ->join(array("ce2" => $prefix."customer_entity_varchar"),"ce2.entity_id = main_table.sellerid",array("lname" => "value"))->where("ce2.attribute_id = ".$lnameid)                
                ->columns(new Zend_Db_Expr("CONCAT(`ce1`.`value`, ' ',`ce2`.`value`) AS fullname"));
                
        $collection->getSelect()
               ->join(array("ce3" => $prefix."customer_entity"),"ce3.entity_id = main_table.sellerid",array("email" => "email"))
               ->columns(new Zend_Db_Expr("`main_table`.`created_at` AS createddate"));
        $collection->addFilterToMap("createddate","`main_table`.`created_at`");
		$this->setCollection($collection);
        parent::_prepareCollection();  
        $helper = Mage::helper('marketplace');
		foreach ($collection as $data) {
            $data->fullname=sprintf('<a href="%s" title="'.$helper->__('View Customer').'">%s</a>',
                                             $this->getUrl("adminhtml/customer/edit",array("id"=>$data->getSellerid())),$data->getFullname());
            if($data->getOnlinetrid()){
                $data->transactionid=sprintf('<a href="%s" title="'.$helper->__('View Transaction').'">%s</a>',
											 $this->getUrl("adminhtml/sales_transactions/view/",array("txn_id"=>$data->getOnlinetrid())),$data->getTransactionid());
            }
		}
    }
	
	protected function _prepareColumns(){
        $this->addColumn('transid', array(
            'header'    => Mage::helper('marketplace')->__('ID'),
            'width'     => '50px',
            'index'     => 'transid',
            'type'  => 'number'
        ));
        $this->addColumn('fullname', array(
            'header'    => Mage::helper('marketplace')->__('Seller Name'),
            'index'     => 'fullname',
            'type'      => 'text',
        ));
        $this->addColumn('email', array(
            'header'    => Mage::helper('marketplace')->__('Seller Email'),
            'width'     => '150',
            'index'     => 'email',
        ));
		$this->addColumn('transactionid', array(
            'header'    => Mage::helper('marketplace')->__('Transaction Id'),
            'index'     => 'transactionid',
            'type'     => 'text',
        ));
        $this->addColumn('transactionamount', array(
            'header'    => Mage::helper('marketplace')->__('Amount'),
            'index'     => 'transactionamount',
        ));
        $this->addColumn('type', array(
            'header'    => Mage::helper('marketplace')->__('Type'),
            'index'     => 'type',
        ));
        $this->addColumn('method', array(
            'header'    => Mage::helper('marketplace')->__('Method'),
            'index'     => 'method',
        ));
        $this->addColumn('createddate', array(
            'header'    => Mage::helper('marketplace')->__('Created At'),
            'type' =>'datetime',
            'align'     => 'center',
            'index'     => 'createddate',
        ));
		$this->addExportType('*/*/exportCsv', Mage::helper('marketplace')->__('CSV'));
		$this->addExportType('*/*/exportXml', Mage::helper('marketplace')->__('XML'));
        return parent::_prepareColumns();
    }
	public function getGridUrl(){
		return $this->getUrl("*/*/grid",array("_current"=>true));
	}
}
