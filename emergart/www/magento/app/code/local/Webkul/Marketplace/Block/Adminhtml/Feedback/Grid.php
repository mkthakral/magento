<?php
class Webkul_Marketplace_Block_Adminhtml_Feedback_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
	parent::__construct();
	$this->setId('feedbackGrid');
	$this->setUseAjax(true);
	$this->setDefaultDir('feedid');
	$this->_emptyText = Mage::helper('marketplace')->__('No Record Found.');
  }

  protected function _prepareCollection()
  {
		$collection=Mage::getModel('marketplace/feedback')->getCollection();
	  $prefix=Mage::getConfig()->getTablePrefix();
    $fnameid = Mage::getModel("eav/entity_attribute")->loadByCode("1", "firstname")->getAttributeId();
    $lnameid = Mage::getModel("eav/entity_attribute")->loadByCode("1", "lastname")->getAttributeId();
    $collection->getSelect()
    ->join(array("ce1" => $prefix."customer_entity_varchar"),"ce1.entity_id = main_table.userid",array("fname" => "value"))->where("ce1.attribute_id = ".$fnameid)
    ->join(array("ce2" => $prefix."customer_entity_varchar"),"ce2.entity_id = main_table.userid",array("lname" => "value"))->where("ce2.attribute_id = ".$lnameid)
    ->columns(new Zend_Db_Expr("CONCAT(`ce1`.`value`, ' ',`ce2`.`value`) AS name"));
    $collection->addFilterToMap("name","`ce1`.`value`");

     $collection->getSelect()
    ->join(array("ce3" => $prefix."customer_entity_varchar"),"ce3.entity_id = main_table.proownerid",array("fname" => "value"))->where("ce3.attribute_id = ".$fnameid)
    ->join(array("ce4" => $prefix."customer_entity_varchar"),"ce4.entity_id = main_table.proownerid",array("lname" => "value"))->where("ce4.attribute_id = ".$lnameid)
    ->columns(new Zend_Db_Expr("CONCAT(`ce3`.`value`, ' ',`ce4`.`value`) AS partner_name"));
    $collection->addFilterToMap("partner_name","`ce3`.`value`");
		$this->setCollection($collection);
    parent::_prepareCollection();
    foreach ($collection as $item) {
      $item->feedprice = ($item->getFeedprice()/20)*1;
      $item->feedvalue = ($item->getFeedvalue()/20)*1;
      $item->feedquality = ($item->getFeedquality()/20)*1;
    }
  }

  protected function _prepareColumns(){
		$this->addColumn('feedid', array(
            'header'    => Mage::helper('marketplace')->__('ID'),
            'width'     => '50px',
            'index'     => 'feedid',
            'type'  => 'number',
        ));
        $this->addColumn('name', array(
            'header'    => Mage::helper('marketplace')->__('Name'),
            'index'     => 'name',
        ));
        $this->addColumn('partner_name', array(
            'header'    => Mage::helper('marketplace')->__('Seller Name'),
            'width'     => '150',
            'index'     => 'partner_name',
        ));
		$this->addColumn('feedprice', array(
            'header'    => Mage::helper('marketplace')->__('Price Rating(in star)'),
            'index'     => 'feedprice',
            'type'      => 'options',
            'options'   => $this->getFeeds(),
        ));
		$this->addColumn('feedvalue', array(
            'header'    => Mage::helper('marketplace')->__('Value Rating(in star)'),
            'index'     => 'feedvalue',
            'type'      => 'options',
            'options'   => $this->getFeeds(),
        ));
		$this->addColumn('feedquality', array(
            'header'    => Mage::helper('marketplace')->__('Quality Rating(in star)'),
            'index'     => 'feedquality',
            'type'      => 'options',
            'options'   => $this->getFeeds(),
        ));
		$this->addColumn('feedsummary', array(
            'header'    => Mage::helper('marketplace')->__('Feed Summary'),
            'index'     => 'feedsummary',
        ));
		$this->addColumn('feedreview', array(
            'header'    => Mage::helper('marketplace')->__('Feed Review'),
            'index'     => 'feedreview',
        ));
		$this->addColumn('createdat', array(
            'header'    => Mage::helper('marketplace')->__('Created At'),
            'index'     => 'createdat',
            'type'  => 'datetime',
        ));
		$this->addColumn('status', array(
            'header'    => Mage::helper('marketplace')->__('Status'),
            'index'     => 'status',
             "type"      => "options",
            "align"     => "center",
            "options"   => array("1" => Mage::helper('marketplace')->__("Approved"), "0" => Mage::helper('marketplace')->__("Unapproved")
        )));
		
        return parent::_prepareColumns();
  }
  
	protected function _prepareMassaction(){
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('feedid');
		$this->getMassactionBlock()->addItem('approve', array(
             'label'    => Mage::helper('marketplace')->__('Approve'),
             'url'      => $this->getUrl('*/*/approve')
        ));
		$this->getMassactionBlock()->addItem('unapprove', array(
             'label'    => Mage::helper('marketplace')->__('Unapprove'),
             'url'      => $this->getUrl('*/*/unapprove')
        ));
		$this->getMassactionBlock()->addItem('massdelete', array(
             'label'    => Mage::helper('marketplace')->__('Delete'),
             'url'      => $this->getUrl('*/*/massdelete')
        ));
        return $this;
    }
	public function getGridUrl(){
		return $this->getUrl("*/*/grid",array("_current"=>true));
	}
  public function getFeeds(){
    return array('20'=>Mage::helper('marketplace')->__('%s Star',1),'40'=>Mage::helper('marketplace')->__('%s Star',2),'60'=>Mage::helper('marketplace')->__('%s Star',3),'80'=>Mage::helper('marketplace')->__('%s Star',4),'100'=>Mage::helper('marketplace')->__('%s Star',5));
  }
}
