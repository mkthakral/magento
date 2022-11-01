<?php
/**
 * Webkul Marketplace Order Model
 *
 * @category    Webkul
 * @package     Webkul_Marketplace
 * @author      Webkul Software Private Limited 
 */
class Webkul_Marketplace_Model_Order extends Mage_Core_Model_Abstract
{	
	/**
     * Construct Mage::getModel('marketplace/order')
     */
	public function _construct()
	{
		parent::_construct();
		$this->_init('marketplace/order');
	}

	/**
     * get seller order model
     */
	public function getOrderinfo($orderid){
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$trackingsdata=Mage::getModel('marketplace/order')->getCollection()
						->addFieldToFilter('order_id',array('eq'=>$orderid))
						->addFieldToFilter('seller_id',array('eq'=>Mage::getSingleton('customer/session')->getCustomerId()));
		foreach($trackingsdata as $tracking){
			return $tracking;
		}
	
	}

	/**
     * get admin cod order model
     */
	public function getAdminOrderinfo($orderid){
		$trackingsdata=Mage::getModel('marketplace/order')->getCollection()
						->addFieldToFilter('order_id',array('eq'=>$orderid))
						->addFieldToFilter('seller_id',array('eq'=>0));
		foreach($trackingsdata as $tracking){
			return $tracking;
		}
	
	}

	/**
	 * @param string $sellerid, $comment
     * Cancel order
     *
     * @return Webkul_Marketplace_Model_Order
     */
    public function cancelorder($order,$partnerid)
    {
    	$flag = 0;
        if ($order->canCancel()) {
            $order->getPayment()->cancel();
            $flag = $this->mpregisterCancellation($order,$partnerid);
        }

        return $flag;
    }

	/**
     * @param string $sellerid, $comment
     * @return Webkul_Marketplace_Model_Order
     * @throws Mage_Core_Exception
     */
    public function mpregisterCancellation($order,$partnerid,$comment = '')
    {
    	$flag = 0;
        if ($order->canCancel()) {

            $cancelState = 'canceled';
			$items=array();
			$shippingAmount=0;
			$trackingsdata=Mage::getModel('marketplace/order')->getCollection()
								 ->addFieldToFilter('order_id',array('eq'=>$order->getId()))
								 ->addFieldToFilter('seller_id',array('eq'=>$partnerid));

			foreach($trackingsdata as $tracking){

				$shippingAmount=$tracking->getShippingCharges();

				$items=explode(',',$tracking->getItemIds());

				$itemsarray = $this->_getItemQtys($order,$items);
				foreach($order->getAllItems() as $item){
					if(in_array($item->getProductId(),$items)){
						$flag = 1;
		               	$item->setQtyCanceled($item->getQtyOrdered())->save();
		            }
		        }
		        foreach($order->getAllItems() as $item){
	                if ($cancelState != 'processing' && $item->getQtyToRefund()) {
	                    if ($item->getQtyToShip() > $item->getQtyToCancel()) {
	                        $cancelState = 'processing';
	                    } else {
	                        $cancelState = 'complete';
	                    }
	                }else if($item->getQtyToInvoice()){
	                	$cancelState = 'processing';
	                }
		        }
	            $order->setState($cancelState, true, $comment)->save();
            }      
        }
        return $flag;
    }

    /**
     * Get requested items qtys
     */
    protected function _getItemQtys($order,$items){
		$data=array();
		$subtotal = 0;
		$baseSubtotal = 0;
		foreach($order->getAllItems() as $item){
			if(in_array($item->getProductId(),$items)){
				$data[$item->getItemId()]=intval($item->getQtyOrdered());
				$subtotal+=$item->getRowTotal();
				$baseSubtotal+=$item->getBaseRowTotal();
			}else{
				$data[$item->getItemId()]=0;
			}   
		}
		return array('data'=>$data,'subtotal'=>$subtotal,'basesubtotal'=>$baseSubtotal);
	}

	/**
     * @param string $order
     * @return Webkul_Marketplace_Model_Order
     */
	public function getCommsionCalculation($order){
		$percent = Mage::helper('marketplace')->getConfigCommissionRate();
		$lastOrderId=$order->getId();
		$codCharges = 0;
		$shippingamount = 0;
		$tax = 0;
		$sellerid=Mage::getSingleton('customer/session')->getCustomer()->getId();
		$is_mpshippingmanager = Mage::helper('core/data')->isModuleOutputEnabled('Webkul_Mpshippingmanager');
		if($is_mpshippingmanager){
	        if(Mage::getModel("mpshippingmanager/tracking")){
				$trackingcol1=Mage::getModel('mpshippingmanager/tracking')->getCollection()
					->addFieldtoFilter('order_id',array('eq'=>$lastOrderId))
					->addFieldtoFilter('seller_id ',array('in'=>$sellerid));
				foreach($trackingcol1 as $row) {
					$shippingamount=$row->getShippingCharges();
				}
			}
		}
		$ordercollection=Mage::getModel('marketplace/saleslist')->getCollection()
										->addFieldToFilter('mageorderid',array('eq'=>$lastOrderId))
										->addFieldToFilter('cpprostatus',array('eq'=>0));
		foreach($ordercollection as $item){
			$actparterprocost = $item->getActualparterprocost();
			$codCharges = $item->getCodCharges();
			$totalamount = $item->getTotalamount();
			$seller_id = $item->getMageproownerid();
			if(Mage::helper('marketplace')->getConfigTaxMange()){
                $tax = $item->getTotaltax();
            }
							
			$collectionverifyread = Mage::getModel('marketplace/saleperpartner')->getCollection();
			$collectionverifyread->addFieldToFilter('mageuserid',array('eq'=>$seller_id));
			if(count($collectionverifyread)>=1){
				foreach($collectionverifyread as $verifyrow){
					$totalsale=$verifyrow->getTotalsale()+$totalamount+$codCharges+$tax+$shippingamount;
					$totalremain=$verifyrow->getAmountremain()+$actparterprocost+$codCharges+$tax+$shippingamount;
					$verifyrow->setTotalsale($totalsale);
					$verifyrow->setAmountremain($totalremain);
					$verifyrow->save();
					$shippingamount = 0;
				}
			}
			else{
				$percent = Mage::helper('marketplace')->getConfigCommissionRate();			
				$collectionf=Mage::getModel('marketplace/saleperpartner');
				$collectionf->setMageuserid($seller_id);
				$collectionf->setTotalsale($totalamount+$codCharges+$tax+$shippingamount);
				$collectionf->setAmountremain($actparterprocost+$codCharges+$tax+$shippingamount);
				$collectionf->setCommision($percent);
				$collectionf->save();						
			}
			if($seller_id){
				$ordercount = 0;
				$feedbackcount = 0;
				$feedcountid = 0;
				$collectionfeed=Mage::getModel('marketplace/feedbackcount')->getCollection()
										->addFieldToFilter('sellerid',array('eq'=>$seller_id));
				foreach ($collectionfeed as $value) {
					$feedcountid = $value->getFeedcountid();
					$ordercount = $value->getOrdercount();
					$feedbackcount = $value->getFeedbackcount();
				}
				$collectionfeed=Mage::getModel('marketplace/feedbackcount')->load($feedcountid);
				$collectionfeed->setBuyerid($order->getCustomerId());
				$collectionfeed->setSellerid($seller_id);
				$collectionfeed->setOrdercount($ordercount+1);
				$collectionfeed->setFeedbackcount($feedbackcount);
				$collectionfeed->save();
			}
			$item->setCpprostatus(1)->save();	
		}
	}

	/**
     * @param string $order_id
     * @return vendor order price
     */
	public function getPricebyorder($order_id){
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$totalamount = 0;
	    $totaltax = 0;
	    $shippingamount = 0;		
	    $codcharges_total = 0;   	
		$seller_orderslist=Mage::getModel('marketplace/saleslist')->getCollection()
							 ->addFieldToFilter('mageproownerid',array('eq'=>$customerid))
							 ->addFieldToFilter('mageorderid',array('eq'=>$order_id))
							 ->setOrder('mageorderid','DESC');
		foreach($seller_orderslist as $seller_item){
			$shippingamount = $shippingamount + $seller_item->getShippingCharges();
			$totaltax = $totaltax + $seller_item->getTotaltax();
			$totalamount = $totalamount + $seller_item->getTotalamount();
			$codcharges_total = $codcharges_total + $seller_item->getCodCharges();
		}
		$is_mpshippingmanager = Mage::helper('core/data')->isModuleOutputEnabled('Webkul_Mpshippingmanager');
		if($is_mpshippingmanager){
	        if(Mage::getModel("mpshippingmanager/tracking")){
	        	$trackingsdata=Mage::getModel('mpshippingmanager/tracking')->getCollection()
					 ->addFieldToFilter('order_id',array('eq'=>$order_id))
					 ->addFieldToFilter('seller_id',array('eq'=>$customerid));
				foreach($trackingsdata as $tracking){
					$shippingamount=$tracking->getShippingCharges();
				}
	        }
	    }
	    return ($shippingamount+$totaltax+$totalamount+$codcharges_total);
	}
}
