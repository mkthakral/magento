<?php
class Webkul_Marketplace_Adminhtml_OrderController extends Mage_Adminhtml_Controller_Action
{
	protected function _initAction() {		
		$this->_title($this->__("Manage Seller's Order"));
		$this->loadLayout()
			->_setActiveMenu('marketplace/marketplace_order')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}
	public function gridAction(){
        $this->loadLayout();
        $this->getResponse()->setBody($this->getLayout()->createBlock("marketplace/adminhtml_order_grid")->toHtml()); 
    }
    public function masspayAction(){    	
		$wholedata=$this->getRequest()->getParams();		
		$actparterprocost = 0;
		$totalamount = 0;
		$seller_id = $wholedata['sellerid'];
		if (!$this->_validateFormKey()) {
         	$this->_redirect('marketplace/adminhtml_order/index',array('id'=>$seller_id));
        }
		$wksellerorderids = explode(',',$wholedata['wksellerorderids']);
		$orderinfo = '';
		$style='style="font-size:11px;padding:3px 9px;border-bottom:1px dotted #cccccc";';
		foreach($wksellerorderids as $key){
			$collection = Mage::getModel('marketplace/saleslist')->getCollection()
									->addFieldToFilter('autoid',array('eq'=>$key))
									->addFieldToFilter('cpprostatus',array('eq'=>1))
									->addFieldToFilter('paidstatus',array('eq'=>0))
									->addFieldToFilter('mageorderid',array('neq'=>0));
			foreach ($collection as $row) {
				$tax_amount = $row['totaltax'];
				$vendor_tax_amount = 0;
				if(Mage::helper('marketplace')->getConfigTaxMange()){
					$vendor_tax_amount = $tax_amount;
				}
				$cod_charges = 0;
				$shipping_charges = 0;
				$cod_charges = $row->getCodCharges();
				$shipping_charges = $row->getShippingCharges();
				$actparterprocost = $actparterprocost+$row->getActualparterprocost()+$vendor_tax_amount+$cod_charges+$shipping_charges;
				$totalamount = $totalamount + $row->getTotalamount()+$tax_amount+$cod_charges+$shipping_charges;
				$seller_id = $row->getMageproownerid();
				$orderinfo = $orderinfo."<tr>
								<td valign='top' align='left' ".$style.">".$row['magerealorderid']."</td>
								<td valign='top' align='left' ".$style.">".$row['mageproname']."</td>
								<td valign='top' align='left' ".$style.">".$row['magequantity']."</td>
								<td valign='top' align='left' ".$style.">".Mage::app()->getStore()->formatPrice($row['mageproprice'])."</td>
								<td valign='top' align='left' ".$style.">".Mage::app()->getStore()->formatPrice($row['totalcommision'])."</td>
								<td valign='top' align='left' ".$style.">".Mage::app()->getStore()->formatPrice($row['actualparterprocost'])."</td>
					 		</tr>";
			}
		}
		if($actparterprocost){		
			$collectionverifyread = Mage::getModel('marketplace/saleperpartner')->getCollection();
			$collectionverifyread->addFieldToFilter('mageuserid',array('eq'=>$seller_id));
			if(count($collectionverifyread)>=1){
				foreach($collectionverifyread as $verifyrow){
					if($verifyrow->getAmountremain() >= $actparterprocost){
						$totalremain=$verifyrow->getAmountremain()-$actparterprocost;
					}
					else{
						$totalremain=0;
					}
					$verifyrow->setAmountremain($totalremain);
					$verifyrow->save();
					$totalremain;
					$amountpaid=$verifyrow->getAmountrecived();
					$totalrecived=$actparterprocost+$amountpaid;
					$verifyrow->setAmountpaid($actparterprocost);
					$verifyrow->setAmountrecived($totalrecived);
					$verifyrow->setAmountremain($totalremain);
					$verifyrow->save();
				}
			}
			else{
				$percent = Mage::helper('marketplace')->getConfigCommissionRate();			
				$collectionf=Mage::getModel('marketplace/saleperpartner');
				$collectionf->setMageuserid($seller_id);
				$collectionf->setTotalsale($totalamount);
				$collectionf->setAmountpaid($actparterprocost);
				$collectionf->setAmountrecived($actparterprocost);
				$collectionf->setAmountremain(0);
				$collectionf->setCommision($percent);
				$collectionf->save();						
			}

			$unique_id = $this->checktransid();
			if($unique_id!=''){
				$seller_trans = Mage::getModel('marketplace/sellertransaction')->getCollection()
	                    ->addFieldToFilter('transactionid',array('eq'=>$unique_id));            
	            if(count($seller_trans)){
					foreach ($seller_trans as $value) {
						$id =$value->getId();
						if($id){
							Mage::getModel('marketplace/sellertransaction')->load($id)->delete();
						}
			    	}
				}
				$currdate = date('Y-m-d H:i:s');
				$seller_trans = Mage::getModel('marketplace/sellertransaction');
				$seller_trans->setTransactionid($unique_id);
				$seller_trans->setTransactionamount($actparterprocost);
				$seller_trans->setType('Manual');
				$seller_trans->setMethod('Manual');
				$seller_trans->setSellerid($seller_id);
				$seller_trans->setCustomnote($wholedata['customnote']);
				$seller_trans->setCreatedAt($currdate);
				$transid = $seller_trans->save()->getTransid();
			}

			foreach($wksellerorderids as $key){
				$collection = Mage::getModel('marketplace/saleslist')->getCollection()
										->addFieldToFilter('autoid',array('eq'=>$key))
										->addFieldToFilter('cpprostatus',array('eq'=>1))
										->addFieldToFilter('paidstatus',array('eq'=>0))
										->addFieldToFilter('mageorderid',array('neq'=>0));
				foreach ($collection as $row) {
					$row->setPaidstatus(1);
					$row->setTransid($transid)->save();
					$data['id']=$row->getMageorderid();
					$data['seller_id']=$row->getMageproownerid();
					Mage::dispatchEvent('mp_pay_seller', $data);
				}
			}

			$seller = Mage::getModel('customer/customer')->load($seller_id);	
			$emailTemp = Mage::getModel('core/email_template')->loadDefault('sellertransactionmail');
			
			$emailTempVariables = array();				
			$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
			$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
			$adminUsername = 'Admin';
			$emailTempVariables['myvar1'] = $seller->getName();
			$emailTempVariables['myvar2'] = $transid;
			$emailTempVariables['myvar3'] = $currdate;
			$emailTempVariables['myvar4'] = $actparterprocost;
			$emailTempVariables['myvar5'] = $orderinfo;
			$emailTempVariables['myvar6'] = $wholedata['customnote'];			
			$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);			
			$emailTemp->setSenderName($adminUsername);
			$emailTemp->setSenderEmail($adminEmail);
			$emailTemp->send($seller->getEmail(),$seller->getName(),$emailTempVariables);

			$this->_getSession()->addSuccess(Mage::helper('marketplace')->__('Payment has been successfully done for this seller'));
		}
		$this->_redirect('marketplace/adminhtml_order/index',array('id'=>$seller_id));
	}

	public function paysellerAction(){
		$wholedata=$this->getRequest()->getParams();		
		$actparterprocost = 0;
		$totalamount = 0;
		$seller_id = $wholedata['sellerid'];
		if (!$this->_validateFormKey()) {
         	$this->_redirect('marketplace/adminhtml_order/index',array('id'=>$seller_id));
        }
		$orderinfo = '';
		$style='style="font-size:11px;padding:3px 9px;border-bottom:1px dotted #cccccc";';
		$collection = Mage::getModel('marketplace/saleslist')->getCollection()
								->addFieldToFilter('autoid',array('eq'=>$wholedata['autoorderid']))
								->addFieldToFilter('cpprostatus',array('eq'=>1))
								->addFieldToFilter('paidstatus',array('eq'=>0))
								->addFieldToFilter('mageorderid',array('neq'=>0));
		foreach ($collection as $row) {
			$tax_amount = $row['totaltax'];
			$vendor_tax_amount = 0;
			if(Mage::helper('marketplace')->getConfigTaxMange()){
				$vendor_tax_amount = $tax_amount;
			}
			$cod_charges = 0;
			$shipping_charges = 0;
			$cod_charges = $row->getCodCharges();
			$shipping_charges = $row->getShippingCharges();
			$actparterprocost = $actparterprocost+$row->getActualparterprocost()+$vendor_tax_amount+$cod_charges+$shipping_charges;
			$totalamount = $totalamount + $row->getTotalamount()+$tax_amount+$cod_charges+$shipping_charges;
			$seller_id = $row->getMageproownerid();
			$orderinfo = $orderinfo."<tr>
							<td valign='top' align='left' ".$style.">".$row['magerealorderid']."</td>
							<td valign='top' align='left' ".$style.">".$row['mageproname']."</td>
							<td valign='top' align='left' ".$style.">".$row['magequantity']."</td>
							<td valign='top' align='left' ".$style.">".Mage::app()->getStore()->formatPrice($row['mageproprice'])."</td>
							<td valign='top' align='left' ".$style.">".Mage::app()->getStore()->formatPrice($row['totalcommision'])."</td>
							<td valign='top' align='left' ".$style.">".Mage::app()->getStore()->formatPrice($row['actualparterprocost'])."</td>
				 		</tr>";
		}
		if($actparterprocost){		
			$collectionverifyread = Mage::getModel('marketplace/saleperpartner')->getCollection();
			$collectionverifyread->addFieldToFilter('mageuserid',array('eq'=>$seller_id));
			if(count($collectionverifyread)>=1){
				foreach($collectionverifyread as $verifyrow){
					if($verifyrow->getAmountremain() >= $actparterprocost){
						$totalremain=$verifyrow->getAmountremain()-$actparterprocost;
					}
					else{
						$totalremain=0;
					}
					$verifyrow->setAmountremain($totalremain);
					$verifyrow->save();
					$totalremain;
					$amountpaid=$verifyrow->getAmountrecived();
					$totalrecived=$actparterprocost+$amountpaid;
					$verifyrow->setAmountpaid($actparterprocost);
					$verifyrow->setAmountrecived($totalrecived);
					$verifyrow->setAmountremain($totalremain);
					$verifyrow->save();
				}
			}
			else{
				$percent = Mage::helper('marketplace')->getConfigCommissionRate();			
				$collectionf=Mage::getModel('marketplace/saleperpartner');
				$collectionf->setMageuserid($seller_id);
				$collectionf->setTotalsale($totalamount);
				$collectionf->setAmountpaid($actparterprocost);
				$collectionf->setAmountrecived($actparterprocost);
				$collectionf->setAmountremain(0);
				$collectionf->setCommision($percent);
				$collectionf->save();						
			}

			$unique_id = $this->checktransid();
			if($unique_id!=''){
				$seller_trans = Mage::getModel('marketplace/sellertransaction')->getCollection()
	                    ->addFieldToFilter('transactionid',array('eq'=>$unique_id));            
	            if(count($seller_trans)){
					foreach ($seller_trans as $value) {
						$id =$value->getId();
						if($id){
							Mage::getModel('marketplace/sellertransaction')->load($id)->delete();
						}
			    	}
				}
				$currdate = date('Y-m-d H:i:s');
				$seller_trans = Mage::getModel('marketplace/sellertransaction');
				$seller_trans->setTransactionid($unique_id);
				$seller_trans->setTransactionamount($actparterprocost);
				$seller_trans->setType('Manual');
				$seller_trans->setMethod('Manual');
				$seller_trans->setSellerid($seller_id);
				$seller_trans->setCustomnote($wholedata['seller_pay_reason']);
				$seller_trans->setCreatedAt($currdate);
				$transid = $seller_trans->save()->getTransid();
			}

			
			$collection = Mage::getModel('marketplace/saleslist')->getCollection()
							->addFieldToFilter('autoid',array('eq'=>$wholedata['autoorderid']))
							->addFieldToFilter('cpprostatus',array('eq'=>1))
							->addFieldToFilter('paidstatus',array('eq'=>0))
							->addFieldToFilter('mageorderid',array('neq'=>0));
			foreach ($collection as $row) {
				$row->setPaidstatus(1);
				$row->setTransid($transid)->save();
				$data['id']=$row->getMageorderid();
				$data['seller_id']=$row->getMageproownerid();
				Mage::dispatchEvent('mp_pay_seller', $data);
			}

			$seller = Mage::getModel('customer/customer')->load($seller_id);	
			$emailTemp = Mage::getModel('core/email_template')->loadDefault('sellertransactionmail');
			
			$emailTempVariables = array();				
			$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
			$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
			$adminUsername = Mage::helper('marketplace')->__('Admin');
			$emailTempVariables['myvar1'] = $seller->getName();
			$emailTempVariables['myvar2'] = $transid;
			$emailTempVariables['myvar3'] = $currdate;
			$emailTempVariables['myvar4'] = $actparterprocost;
			$emailTempVariables['myvar5'] = $orderinfo;
			$emailTempVariables['myvar6'] = $wholedata['seller_pay_reason'];		
			$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);			
			$emailTemp->setSenderName($adminUsername);
			$emailTemp->setSenderEmail($adminEmail);
			$emailTemp->send($seller->getEmail(),$seller->getName(),$emailTempVariables);

			$this->_getSession()->addSuccess(Mage::helper('marketplace')->__('Payment has been successfully done for this seller'));
		}
		$this->_redirect('marketplace/adminhtml_order/index',array('id'=>$seller_id));
	}

	public function randString($length, $charset='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')
	{
	    $str = 'tr-';
	    $count = strlen($charset);
	    while ($length--) {
	        $str .= $charset[mt_rand(0, $count-1)];
	    }

	    return $str;
	}

	public function checktransid(){
		$unique_id=$this->randString(11);
		$collection = Mage::getModel('marketplace/sellertransaction')->getCollection()
                    ->addFieldToFilter('transactionid',array('eq'=>$unique_id));
        $i=0;
    	foreach ($collection as $value) {
    			$i++;
    	}   
    	if($i!=0){
            $this->checktransid();
        }else{
        	return $unique_id;
        }		
	}
}