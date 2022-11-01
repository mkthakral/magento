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
class Webkul_Marketplace_Order_ShipmentController extends Mage_Customer_AccountController{	
/**
     * Initialize shipment model instance
     *
     * @return Mage_Sales_Model_Order_Shipment
     */
    protected function _initShipment($update = false)
    {
        $this->_title(Mage::helper('marketplace')->__('Shipments'));

        $shipment = false;
        $itemsToShipment = 0;
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $orderId = $this->getRequest()->getParam('order_id');

    	$tracking=Mage::getModel('marketplace/order')->getOrderinfo($orderId);
    	if(((count($tracking)) && Mage::getStoreConfig('marketplace/marketplace_options/ordermanage',Mage::app()->getStore())) || ($this->getRequest()->getActionName() == 'print')){
	    	if ($tracking->getShipmentId() == $shipmentId) {
		        if ($shipmentId) {
		            $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
		            if (!$shipment->getId()) {
		                Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('The shipment no longer exists.'));
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
		    	Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('You are not authorize to view this shipment.'));
	            return false;
		    }
		}else{
			$this->_redirect('marketplace/order/history');
			return false;
		}

        Mage::register('current_shipment', $shipment);
        return $shipment;
    }

	/**
     * View shipment detail
     */
	public function viewAction() {
		$shipmentId = $this->getRequest()->getParam('shipment_id');
		$order_id = $this->getRequest()->getParam('order_id');
		if ($shipment = $this->_initShipment()) {
			try{
				$this->loadLayout();
		        $this->_initLayoutMessages('customer/session');
		        $this->_initLayoutMessages('catalog/session');
				$this->_title(Mage::helper('marketplace')->__("#%s Order Shipment", $shipment->getIncrementId()));
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
    	$shipmentId = $this->getRequest()->getParam('shipment_id');
		$order_id = $this->getRequest()->getParam('order_id');
        if ($shipment = $this->_initShipment()) {
            try {        		
                $shipment->sendEmail(true)
                    ->setEmailSent(true)
                    ->save();
                $historyItem = Mage::getResourceModel('sales/order_status_history_collection')
                    ->getUnnotifiedForInstance($shipment, Mage_Sales_Model_Order_Shipment::HISTORY_ENTITY_NAME);
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
        $this->_redirect('marketplace/order_shipment/view', array('order_id'=>$order_id,'shipment_id'=>$shipmentId));
    }

    /**
     * Print Shipment
     */
	public function printAction()
    {
    	$shipmentId = $this->getRequest()->getParam('shipment_id');
		$order_id = $this->getRequest()->getParam('order_id');
    	if ($shipment = $this->_initShipment()) {
	    	try{
	            if ($shipment = Mage::getModel('sales/order_shipment')->load($shipmentId)) {
	                $pdf = Mage::getModel('marketplace/order_pdf_shipment')->getPdf(array($shipment));
	                $this->_prepareDownloadResponse('packingslip'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.pdf', $pdf->render(), 'application/pdf');
	            }else {
		            $this->_forward('noRoute');
		        }

	        } catch (Exception $e) {
				$message = $e->getMessage();
				Mage::getSingleton('core/session')->addError($message);
				$this->_redirect('marketplace/order_shipment/view', array('order_id'=>$order_id,'shipment_id'=>$shipmentId));
			}
		}else{
			$this->_redirect('marketplace/order_shipment/view', array('order_id'=>$order_id,'shipment_id'=>$shipmentId));
		}
    }

     /**
     * Print all shipment
     */
    public function printallAction()
    {
    	$get=$this->getRequest()->getParams();
		$todate = date_create($get['special_to_date']);
		$to = date_format($todate, 'Y-m-d H:i:s');
		$fromdate = date_create($get['special_from_date']);
		$from = date_format($fromdate, 'Y-m-d H:i:s');

		$shipmentIds = array();

		$collection = Mage::getModel('marketplace/saleslist')->getCollection()
						->addFieldToFilter('mageproownerid', array('eq' => Mage::getSingleton('customer/session')->getCustomerId()))
						->addFieldToFilter('cleared_at', array('datetime' => true,'from' => $from,'to' =>  $to))
						->addFieldToSelect('mageorderid')
						->distinct(true);
		foreach($collection as $coll){				
			$shipping_coll = Mage::getModel('marketplace/order')->getCollection()
							->addFieldToFilter('order_id',array('eq'=>$coll->getMageorderid()))
							->addFieldToFilter('seller_id',array('eq'=>Mage::getSingleton('customer/session')->getCustomerId()));
			foreach ($shipping_coll as $tracking) {
				if($tracking->getShipmentId()){
					array_push($shipmentIds, $tracking->getShipmentId());
				}
			}
		}

        if (!empty($shipmentIds)) {
            $shipments = Mage::getResourceModel('sales/order_shipment_collection')
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('entity_id', array('in' => $shipmentIds))
                ->load();
            if (!isset($pdf)){
                $pdf = Mage::getModel('marketplace/order_pdf_shipment')->getPdf($shipments);
            } else {
                $pages = Mage::getModel('marketplace/order_pdf_shipment')->getPdf($shipments);
                $pdf->pages = array_merge ($pdf->pages, $pages->pages);
            }

            return $this->_prepareDownloadResponse('packingslip'.Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.pdf', $pdf->render(), 'application/pdf');
        }
        $this->_redirect('marketplace/order/history');
    }
}