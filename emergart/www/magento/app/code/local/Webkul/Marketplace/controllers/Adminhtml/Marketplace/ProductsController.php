<?php
class Webkul_Marketplace_Adminhtml_Marketplace_ProductsController extends Mage_Adminhtml_Controller_Action
{
	protected function _isAllowed(){
		return Mage::getSingleton('admin/session')->isAllowed('admin/marketplace/marketplace_products');
	}

	protected function _initAction() {
		$this->_title(Mage::helper('marketplace')->__("Manage Seller's Products"));
		$this->loadLayout()
			->_setActiveMenu('marketplace/marketplace_products')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}
	public function denyAction(){
		$wholedata=$this->getRequest()->getParams();
		$productid = $wholedata['productid'];
		$sellerid = $wholedata['sellerid'];
		$collection = Mage::getModel('marketplace/product')->getCollection()
							->addFieldToFilter('mageproductid',array('eq'=>$productid))
							->addFieldToFilter('userid',array('eq'=>$sellerid));
		foreach ($collection as $row) {
			$id = $row->getMageproductid();
			$magentoProductModel = Mage::getModel('catalog/product')->load($id);
			$catarray=$magentoProductModel->getCategoryIds();
			$categoryname='';
			$catagory_model = Mage::getModel('catalog/category');
			foreach($catarray as $keycat){
			$categoriesy = $catagory_model->load($keycat);
				if($categoryname ==''){
					$categoryname=$categoriesy->getName();
				}else{
					$categoryname=$categoryname.",".$categoriesy->getName();
				}
			}
			$allStores = Mage::app()->getStores();
			foreach ($allStores as $_eachStoreId => $val)
			{
				Mage::getModel('catalog/product_status')->updateProductStatus($id,Mage::app()->getStore($_eachStoreId)->getId(), Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
			}
			$magentoProductModel->setStatus(2)->save();
			$row->setStatus(2);
			$row->save();
		}
		$customer = Mage::getModel('customer/customer')->load($sellerid);	
		$emailTemp = Mage::helper('marketplace')->getProductDenyTemplate();
		$emailTempVariables = array();
		$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
		$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
		$adminUsername = 'Admin';
		$emailTempVariables['myvar1'] = $customer->getName();
		$emailTempVariables['myvar2'] = $wholedata['product_deny_reason'];
		$emailTempVariables['myvar3'] = $magentoProductModel->getName();
		$emailTempVariables['myvar4'] = $categoryname;
		$emailTempVariables['myvar5'] = $magentoProductModel->getDescription();
		$emailTempVariables['myvar6'] = $magentoProductModel->getPrice();
		
		$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
		
		$emailTemp->setSenderName($adminUsername);
		$emailTemp->setSenderEmail($adminEmail);
		$emailTemp->send($customer->getEmail(),$adminUsername,$emailTempVariables);
		$sellername = $customer->getName();
		$this->_getSession()->addSuccess($magentoProductModel->getName().Mage::helper('marketplace')->__(' has been successfully denied'));

		$this->_redirect('adminhtml/marketplace_products');
	}
	public function approveAction(){
		$id = (int)$this->getRequest()->getParam('id');
		if(!$id){$this->_redirectReferer();}
		$lastId=Mage::getModel('marketplace/product')->approveSimpleProduct($id);
		Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('marketplace')->__('Product successfully approved.'));
		$this->_redirect('adminhtml/catalog_product/edit', array('id' => $lastId,'_current'=>true));
		
	}
	public function massapproveAction(){
		$ids = $this->getRequest()->getParam('marketplaceproduct');
        if(!is_array($ids)) {
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select item(s)'));
        } else {
            try {
                foreach ($ids as $id) {
                	$sellerproduct = Mage::getModel('marketplace/product')->load($id);
					$lastId=Mage::getModel('marketplace/product')->approveSimpleProduct($sellerproduct->getMageproductid());
				}
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__(
                        'Total of %d record(s) were successfully approved', count($ids)
                    )
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
		}
		$this->_redirect('*/*/index');
	}
	public function gridAction(){
            $this->loadLayout();
            $this->getResponse()->setBody($this->getLayout()->createBlock('marketplace/adminhtml_products_grid')->toHtml()); 
        }
}
