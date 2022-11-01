<?php
/**
 * Webkul Marketplace Orders controller
 *
 * @category    Webkul
 * @package     Webkul_Marketplace
 * @author      Webkul Software Private Limited
 *
 */
require_once 'Mage/Customer/controllers/AccountController.php';
class Webkul_Marketplace_Order_CreditmemoController extends Mage_Customer_AccountController
{	
	/**
     * Initialize order model instance
     *
     * @return Mage_Sales_Model_Order || false
     */
    protected function _initOrder()
    {
        $id = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($id);

    	$tracking=Mage::getModel('marketplace/order')->getOrderinfo($id);
    	if((count($tracking)) && Mage::getStoreConfig('marketplace/marketplace_options/ordermanage',Mage::app()->getStore())){
	    	if ($tracking->getOrderId() == $id) {
		        if (!$order->getId()) {
		            Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('This order no longer exists.'));
		            $this->_redirect('*/*/');
		            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
		            return false;
		        }
		    }else{
		    	Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('You are not authorize to manage this order.'));
	            return false;
		    }
		}else{
			$this->_redirect('marketplace/order/history');
			return false;
		}

        Mage::register('sales_order', $order);
        Mage::register('current_order', $order);
        return $order;
    }


	/**
     * View order detail
     */
	public function newAction() {
		if ($order = $this->_initOrder()) {
			try{
				$this->loadLayout();
		        $this->_initLayoutMessages('customer/session');
		        $this->_initLayoutMessages('catalog/session');
				$this->_title(Mage::helper('marketplace')->__("New Credit Memo for Order #%s", $order->getRealOrderId()));
		    	$this->renderLayout();
		    }catch(Exception $e){
		    	Mage::getSingleton('core/session')->addError($e->getMessage());
		    	$this->_redirect('marketplace/order/history');
		    }
		}else{
			$this->_redirect('marketplace/order/history');
		}
	}
	/**
     * Initialize creditmemo model instance
     *
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    protected function _initCreditmemo($update = false)
    {
        $this->_title(Mage::helper('marketplace')->__('Creditmemo'));

        $creditmemo = false;
        $itemsToCreditmemo = 0;
        $creditmemoId = $this->getRequest()->getParam('creditmemo_id');
        $orderId = $this->getRequest()->getParam('order_id');

    	$tracking=Mage::getModel('marketplace/order')->getOrderinfo($orderId);
    	if(count($tracking)&&Mage::getStoreConfig('marketplace/marketplace_options/ordermanage')){
	    	if ($tracking->getCreditmemoId() == $creditmemoId) {
		        if ($creditmemoId) {
		            $creditmemo = Mage::getModel('sales/order_creditmemo')->load($creditmemoId);
		            if (!$creditmemo->getId()) {
		                Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('The creditmemo no longer exists.'));
		                return false;
		            }
		        } elseif ($orderId) {
		            $order = Mage::getModel('sales/order')->load($orderId);
		            /**
		             * Check order existing
		             */
		            if (!$order->getId()) {
		                Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('The order no longer exists.'));
		                return false;
		            }
		        }
		    }else{
		    	Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('You are not authorize to view this creditmemo.'));
	            return false;
		    }
		}else{
			$this->_redirect('marketplace/order/history');
			return false;
		}

        Mage::register('current_creditmemo', $creditmemo);
        return $creditmemo;
    }

	/**
     * View creditmemo detail
     */
	public function viewAction() {
		$creditmemoId = $this->getRequest()->getParam('creditmemo_id');
		$order_id = $this->getRequest()->getParam('order_id');
		if ($creditmemo = $this->_initCreditmemo()) {
			try{
				$this->loadLayout();
		        $this->_initLayoutMessages('customer/session');
		        $this->_initLayoutMessages('catalog/session');
				$this->_title(Mage::helper('marketplace')->__("#%s Order Creditmemo", $creditmemo->getIncrementId()));
		    	$this->renderLayout();
		    }catch(Exception $e){
		    	$this->_redirect('marketplace/order/view', array('id'=>$order_id));
		    }
		}else{
			$this->_redirect('marketplace/order/history');
		}
	}

	/**
     * Notify user
     */
    public function emailAction()
    {
    	$creditmemoId = $this->getRequest()->getParam('creditmemo_id');
		$order_id = $this->getRequest()->getParam('order_id');
        if ($creditmemo = $this->_initCreditmemo()) {
            try {        		
                $creditmemo->sendEmail(true)
                    ->setEmailSent(true)
                    ->save();
                $historyItem = Mage::getResourceModel('sales/order_status_history_collection')
                    ->getUnnotifiedForInstance($creditmemo, Mage_Sales_Model_Order_Creditmemo::HISTORY_ENTITY_NAME);
                if ($historyItem) {
                    $historyItem->setIsCustomerNotified(1);
                    $historyItem->save();
                }
                Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('The message has been sent.'));
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('core/session')->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('Failed to send the Shipping email.'));
                Mage::logException($e);
            }
        }
        $this->_redirect('marketplace/order_creditmemo/view', array('order_id'=>$order_id,'creditmemo_id'=>$creditmemoId));
    }

    /**
     * Print Creditmemo
     */
	public function printAction()
    {
    	$creditmemoId = $this->getRequest()->getParam('creditmemo_id');
		$order_id = $this->getRequest()->getParam('order_id');
    	if ($creditmemo = $this->_initCreditmemo()) {
	    	try{
	            if ($creditmemo = Mage::getModel('sales/order_creditmemo')->load($creditmemoId)) {
	                $pdf = Mage::getModel('marketplace/order_pdf_creditmemo')->getPdf(array($creditmemo));
	                $this->_prepareDownloadResponse('creditmemo'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.pdf', $pdf->render(), 'application/pdf');
	            }else {
		            $this->_forward('noRoute');
		        }

	        } catch (Exception $e) {
				$message = $e->getMessage();
				Mage::getSingleton('core/session')->addError($message);
				$this->_redirect('marketplace/order_creditmemo/view', array('order_id'=>$order_id,'creditmemo_id'=>$creditmemoId));
			}
		}else{
			$this->_redirect('marketplace/order_creditmemo/view', array('order_id'=>$order_id,'creditmemo_id'=>$creditmemoId));
		}
    }
}