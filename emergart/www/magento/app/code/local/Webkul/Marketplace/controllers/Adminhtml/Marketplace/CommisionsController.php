<?php
class Webkul_Marketplace_Adminhtml_Marketplace_CommisionsController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed(){
        return Mage::getSingleton('admin/session')->isAllowed('admin/marketplace/marketplace_commisions');
    }
    
	protected function _initAction() {
		$this->_title($this->__("Manage Seller's Commission"));
		$this->loadLayout()
			->_setActiveMenu('marketplace/marketplace_commisions')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));
		
		return $this;
	}   
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}
	public function payamountAction(){
	    $data=$this->getRequest();
		Mage::getModel('marketplace/saleperpartner')->salePayment($data);
		
		$this->_redirectReferer();	
	}
	public function masspayamountAction(){
	    $data=$this->getRequest();
		Mage::getModel('marketplace/saleperpartner')->masssalePayment($data);
		
		$this->_redirectReferer();	
	}
	public function gridAction(){
            $this->loadLayout();
            $this->getResponse()->setBody($this->getLayout()->createBlock("marketplace/adminhtml_commisions_grid")->toHtml()); 
    }
}