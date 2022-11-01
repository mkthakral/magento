<?php
class Webkul_Marketplace_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(){
		$marketplacelabel=Mage::helper('marketplace')->getMarketplaceHeadLabel();
		$this->loadLayout(array('default','marketplace_index_toplinkmarketplace'));
		$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__($marketplacelabel));
		$this->renderLayout();
    }
	public function toplinkmarketplaceAction(){
		$this->loadLayout(); 
		$this->renderLayout();
	}
}