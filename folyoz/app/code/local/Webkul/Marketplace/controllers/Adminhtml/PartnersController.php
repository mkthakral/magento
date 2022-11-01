<?php
class Webkul_Marketplace_Adminhtml_PartnersController extends Mage_Adminhtml_Controller_Action
{
	protected function _initAction() {
		$this->_title(Mage::helper('marketplace')->__("Manage Sellers"));
		$this->loadLayout()
			->_setActiveMenu('marketplace/marketplace_partners')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}
	
	public function denyAction(){
		$sellername = Mage::getModel('marketplace/userprofile')->denypartner($this->getRequest());
		$this->_getSession()->addSuccess(Mage::helper('marketplace')->__('%s has been successfully denied to become seller',$sellername));

		$this->_redirect('marketplace/adminhtml_partners/');
	}

	public function massispartnerAction(){
		Mage::getModel('marketplace/userprofile')->massispartner($this->getRequest());
		$this->_getSession()->addSuccess(Mage::helper('marketplace')->__('Seller has been successfully approved'));
		$this->_redirect('marketplace/adminhtml_partners/');
	}
	
	public function massnotpartnerAction(){	
		Mage::getModel('marketplace/userprofile')->massisnotpartner($this->getRequest());
		$this->_getSession()->addSuccess(Mage::helper('marketplace')->__('Seller has been successfully unapproved'));
		$this->_redirect('marketplace/adminhtml_partners/');
	}
	
	public function exportCsvAction(){
        $fileName   = 'Marketplacesellers.csv';
        $content    = $this->getLayout()
							->createBlock('marketplace/adminhtml_partners_grid')->getCsv();
        $this->_sendUploadResponse($fileName, $content);
    }

    public function exportXmlAction(){
        $fileName   = 'Marketplacesellers.xml';
        $content    = $this->getLayout()
							->createBlock('marketplace/adminhtml_partners_grid')->getXml();
        $this->_sendUploadResponse($fileName, $content);
    }
	public function gridAction(){
            $this->loadLayout();
            $this->getResponse()->setBody($this->getLayout()->createBlock("marketplace/adminhtml_partners_grid")->toHtml()); 
        }
	protected function _sendUploadResponse($fileName, $content, $contentType='application/octet-stream'){
        $response = $this->getResponse();
        $response->setHeader('HTTP/1.1 200 OK','');
        $response->setHeader('Pragma', 'public', true);
        $response->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true);
        $response->setHeader('Content-Disposition', 'attachment; filename='.$fileName);
        $response->setHeader('Last-Modified', date('r'));
        $response->setHeader('Accept-Ranges', 'bytes');
        $response->setHeader('Content-Length', strlen($content));
        $response->setHeader('Content-type', $contentType);
        $response->setBody($content);
        $response->sendResponse();
        die;
    }
}