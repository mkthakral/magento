<?php
class Webkul_Marketplace_Adminhtml_Marketplace_TransactionController extends Mage_Adminhtml_Controller_Action
{
    protected function _isAllowed(){
        return Mage::getSingleton('admin/session')->isAllowed('admin/marketplace/marketplace_transaction');
    }
    
	protected function _initAction() {		
		$this->_title(Mage::helper('marketplace')->__("Seller's Transactions"));
		$this->loadLayout()
			->_setActiveMenu('marketplace/marketplace_transaction')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}

	public function gridAction(){
        $this->loadLayout();
        $this->getResponse()->setBody($this->getLayout()->createBlock("marketplace/adminhtml_transaction_grid")->toHtml()); 
    }

    public function exportCsvAction(){
        $fileName   = 'Sellerstransaction.csv';
        $content    = $this->getLayout()
							->createBlock('marketplace/adminhtml_transaction_grid')->getCsv();
        $this->_sendUploadResponse($fileName, $content);
    }

    public function exportXmlAction(){
        $fileName   = 'Sellerstransaction.xml';
        $content    = $this->getLayout()
							->createBlock('marketplace/adminhtml_transaction_grid')->getXml();
        $this->_sendUploadResponse($fileName, $content);
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