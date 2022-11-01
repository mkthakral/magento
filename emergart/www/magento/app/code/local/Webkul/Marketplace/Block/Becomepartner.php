<?php
class Webkul_Marketplace_Block_Becomepartner extends Mage_Core_Block_Template
{
	public function __construct(){		
		parent::__construct();
		$partnerId=Mage::getSingleton('customer/session')->getCustomerId(); 
		$collection=Mage::getModel('marketplace/userprofile')->getCollection(); 
		$collection->addFieldToFilter('mageuserid',array('eq'=>$partnerId)); 
		$collection->addFieldToFilter('wantpartner',array('eq'=>1)); 
		if(count($collection)==1){
			$url=$this->getUrl('marketplace/marketplaceaccount/simpleproduct/');
			Mage::app()->getFrontController()->getResponse()->setRedirect($url);
		}
	}
	public function _prepareLayout()
    {
		return parent::_prepareLayout();
    }
}