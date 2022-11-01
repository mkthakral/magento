<?php

class Webkul_Marketplace_Model_Saleslist extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('marketplace/saleslist');
    }
	
	public function getProductSalesDetailById($productId)
	{
		$data = array();
        if($productId > 0){
           $collection = Mage::getModel('marketplace/saleslist')->getCollection();
           $collection->addFieldToFilter('mageproid',array('eq'=>$productId));
			$i=0;
			foreach ($collection as $record) {
				$data[$i]=array(
							'magequantity'=>$record->getmagequantity(),
							'actualparterprocost'=>$record->getactualparterprocost()
						);
				$i++;
			}
			return $data;
		}
	}
	
	public function getCommsionCalculation($order)
	{
		$percent = Mage::helper('marketplace')->getConfigCommissionRate();
		$lastOrderId=$order->getId();
		/*
		* Calculate cod and shipping charges if applied
		*/
		$cod_charges= 0;
		$shipping_charges= 0;
		$seller_order = Mage::getModel('marketplace/order')->getCollection()
							->addFieldToFilter('order_id',$lastOrderId);
		foreach($seller_order as $info){
			$cod_charges = $info->getCodCharges();
			$shipping_charges = $info->getShippingCharges();
		}

		$ordercollection=Mage::getModel('marketplace/saleslist')->getCollection()
										->addFieldToFilter('mageorderid',array('eq'=>$lastOrderId))
										->addFieldToFilter('cpprostatus',array('eq'=>0));
		foreach($ordercollection as $item){			
			$tax_amount = $item['totaltax'];
			if(!Mage::helper('marketplace')->getConfigTaxMange()){
				$tax_amount = 0;
			}
			$actparterprocost = $item->getActualparterprocost()+$tax_amount+$cod_charges+$shipping_charges;
			$totalamount = $item->getTotalamount()+$tax_amount+$cod_charges+$shipping_charges;
			$cod_charges= 0;
			$shipping_charges= 0;
			$seller_id = $item->getMageproownerid();
							
			$collectionverifyread = Mage::getModel('marketplace/saleperpartner')->getCollection();
			$collectionverifyread->addFieldToFilter('mageuserid',array('eq'=>$seller_id));
			if(count($collectionverifyread)>=1){
				foreach($collectionverifyread as $verifyrow){
					$totalsale=$verifyrow->getTotalsale()+$totalamount;
					$totalremain=$verifyrow->getAmountremain()+$actparterprocost;
					$verifyrow->setTotalsale($totalsale);
					$verifyrow->setAmountremain($totalremain);
					$verifyrow->save();
				}
			}
			else{
				$percent = Mage::helper('marketplace')->getConfigCommissionRate();			
				$collectionf=Mage::getModel('marketplace/saleperpartner');
				$collectionf->setMageuserid($seller_id);
				$collectionf->setTotalsale($totalamount);
				$collectionf->setAmountremain($actparterprocost);
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

	public function paysellerpayment($order,$sellerid,$trid)
	{
		$lastOrderId=$order->getId();		
		$actparterprocost = 0;
		$totalamount = 0;
		/*
		* Calculate cod and shipping charges if applied
		*/
		$cod_charges= 0;
		$shipping_charges= 0;
		$seller_order = Mage::getModel('marketplace/order')->getCollection()
							->addFieldToFilter('order_id',$lastOrderId);
		foreach($seller_order as $info){
			$cod_charges = $info->getCodCharges();
			$shipping_charges = $info->getShippingCharges();
		}
		$collection = Mage::getModel('marketplace/saleslist')->getCollection()
								->addFieldToFilter('cpprostatus',array('eq'=>1))
								->addFieldToFilter('paidstatus',array('eq'=>0))
								->addFieldToFilter('mageproownerid',$sellerid)
								->addFieldToFilter('mageorderid',array('eq'=>$lastOrderId));
		foreach ($collection as $row) {
			$tax_amount = $row['totaltax'];
			$vendor_tax_amount = 0;
			if(Mage::helper('marketplace')->getConfigTaxMange()){
				$vendor_tax_amount = $tax_amount;
			}
			$actparterprocost = $actparterprocost+$vendor_tax_amount+$row->getActualparterprocost()+$cod_charges+$shipping_charges;
			$totalamount = $totalamount + $row->getTotalamount()+$tax_amount+$cod_charges+$shipping_charges;
			$cod_charges= 0;
			$shipping_charges= 0;
			$seller_id = $row->getMageproownerid();
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

			if($trid){
				$unique_id = Mage::getModel('sales/order_payment_transaction')->load($trid)->getTxnId();
			}else{
				$unique_id = $this->checktransid();
			}
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
				if($order->getPayment()){
		  			$paymentCode = $order->getPayment()->getMethod();
		  			$payment_type=Mage::getStoreConfig('payment/'.$paymentCode.'/title');
		  		}else{
		  			$payment_type='Manual';
		  		}
				$currdate = date('Y-m-d H:i:s');
				$seller_trans = Mage::getModel('marketplace/sellertransaction');
				$seller_trans->setTransactionid($unique_id);
				$seller_trans->setOnlinetrid($trid);
				$seller_trans->setTransactionamount($actparterprocost);
				$seller_trans->setType('Online');
				$seller_trans->setMethod($payment_type);
				$seller_trans->setSellerid($seller_id);
				$seller_trans->setCustomnote('None');
				$seller_trans->setCreatedAt($currdate);
				$transid = $seller_trans->save()->getTransid();
			}
			
			$collection = Mage::getModel('marketplace/saleslist')->getCollection()
							->addFieldToFilter('cpprostatus',array('eq'=>1))
							->addFieldToFilter('paidstatus',array('eq'=>0))
							->addFieldToFilter('mageorderid',array('eq'=>$lastOrderId))
							->addFieldToFilter('mageproownerid',$sellerid);
			foreach ($collection as $row) {
				$row->setPaidstatus(1);
				$row->setTransid($transid)->save();
				Mage::dispatchEvent('mp_pay_seller',array("transaction_id"=>$transid,"id"=>$lastOrderId,"seller_id"=>$sellerid));
			}
		}
		
		Mage::getSingleton('core/session')->unsetData('onlinesellertrids');
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
	
	public function checktransid()
	{
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
	
	public function getProductSalesCalculation($order)
	{
		/*
		* Marketplace Order details save before Observer
		*/
		Mage::dispatchEvent('mp_order_save_before',array("order"=>$order));

		/*
		* Get Global Commission Rate for Admin
		*/
		$percent = Mage::helper('marketplace')->getConfigCommissionRate();

		/*
		* Get Current Store Currency Rate
		*/
		$currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
		$baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();		
		$allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies(); 
		$rates = Mage::getModel('directory/currency')->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
		if(!$rates[$currentCurrencyCode]){
			$rates[$currentCurrencyCode] = 1;
		}

		$lastOrderId=$order->getId();

		/*
		* Marketplace Credit Management module Observer
		*/
		Mage::dispatchEvent('mp_discount_manager',array("order"=>$order));
		/*
		* Marketplace Credit discount data
		*/
		$discountDetails = array();
		$discountDetails = Mage::getSingleton('core/session')->getData('salelistdata');

		Mage::dispatchEvent('mp_advance_commission_rule',array("order"=>$order));
		$advanceCommissionRule = Mage::getSingleton('core/session')->getData('advancecommissionrule');

		$seller_pro_arr=array();
		foreach ($order->getAllItems() as $item){
			$item_data = $item->getData();
			$attrselection = unserialize($item_data['product_options']);
			$bundle_selection_attributes = array();
			if(isset($attrselection['bundle_selection_attributes'])){
				$bundle_selection_attributes = unserialize($attrselection['bundle_selection_attributes']);
			}else{
				$bundle_selection_attributes['option_id']=0;
			}
			if(!$bundle_selection_attributes['option_id']){			
				$temp=$item->getProductOptions();
			 	if (array_key_exists('seller_id', $temp['info_buyRequest'])) {
					$seller_id= $temp['info_buyRequest']['seller_id'];
				}
				else {
					$seller_id='';
				}				
				if($discountDetails[$item->getProductId()]) 
					$price = $discountDetails[$item->getProductId()]['price']/$rates[$currentCurrencyCode];
				else
					$price = $item->getPrice()/$rates[$currentCurrencyCode];
				if($seller_id==''){
					$collection_product = Mage::getModel('marketplace/product')->getCollection();
					$collection_product->addFieldToFilter('mageproductid',array('eq'=>$item->getProductId()));
					foreach($collection_product as $selid){
						$seller_id=$selid->getuserid();
					}
				}
			  	if($seller_id==''){$seller_id=0;}		
				$collection1 = Mage::getModel('marketplace/saleperpartner')->getCollection();
				$collection1->addFieldToFilter('mageuserid',array('eq'=>$seller_id));
				$taxamount=$item_data['tax_amount'];
				$qty=$item->getQtyOrdered();
				$totalamount=$qty*$price;
				
				if(count($collection1)!=0){
					foreach($collection1 as $rowdatasale) {
						$commision=($totalamount*$rowdatasale->getcommision())/100;
					}
			 	}
				else{
					$commision=($totalamount*$percent)/100;
				}	
						
				if(!Mage::helper('marketplace')->getUseCommissionRule()) {		
					$wholedata['id'] = $item->getProductId();
					Mage::dispatchEvent('mp_advance_commission', $wholedata);
					$advancecommission = Mage::getSingleton('core/session')->getData('commission');
					if($advancecommission!=''){
						$percent=$advancecommission;
						$commType = Mage::helper('marketplace')->getCommissionType();
						if($commType=='fixed')
						{
							$commision=$percent;
						}
						else
						{
							$commision=($totalamount*$advancecommission)/100;
						}	  
						if($commision>$totalamount){ $commission= $totalamount*Mage::helper('marketplace')->getConfigCommissionRate()/100; }
					}	
				} else {
					if(count($advanceCommissionRule)) {
						if($advanceCommissionRule[$item->getId()]['type'] == 'fixed') {
							$commision = $advanceCommissionRule[$item->getId()]['amount'];
						} else {
							$commision = ($totalamount * $advanceCommissionRule[$item->getId()]['amount']) / 100;
						}	
					}
				}		
						
				$actparterprocost=$totalamount-$commision;
				$collectionsave=Mage::getModel('marketplace/saleslist');
				$collectionsave->setMageproid($item->getProductId());
				$collectionsave->setOrderItemId($item->getItemId());
				$collectionsave->setParentItemId($item->getParentItemId());
				$collectionsave->setMageorderid($lastOrderId);
				$collectionsave->setMagerealorderid($order->getIncrementId());
				$collectionsave->setMagequantity($qty);
				$collectionsave->setMageproownerid($seller_id);
				$collectionsave->setCpprostatus(0);
				$collectionsave->setMagebuyerid(Mage::getSingleton('customer/session')->getCustomer()->getId());
				$collectionsave->setMageproprice($price);
				$collectionsave->setMageproname($item->getName());
				if($totalamount!=0){
				$collectionsave->setTotalamount($totalamount);
				}
				else{
				$collectionsave->setTotalamount($price);
				}
				$collectionsave->setTotaltax($taxamount);
				$collectionsave->setTotalcommision($commision);
				$collectionsave->setactualparterprocost($actparterprocost);
				$collectionsave->setCleared_at(date('Y-m-d H:i:s'));
				$collectionsave->save();
				$qty='';
				if($price!=0.0000){
					if(!isset($seller_pro_arr[$seller_id])){
	                    $seller_pro_arr[$seller_id]=array();
	                }
					array_push($seller_pro_arr[$seller_id], $item->getProductId());
				}
			}
		}
		foreach($seller_pro_arr as $key=>$value){
			$product_ids=implode(',',$value);			
			$data=array(
						'order_id'=>$lastOrderId,
						'item_ids'=>$product_ids,
						'seller_id'=>$key
					);
			$shippingAll=Mage::getSingleton('core/session')->getData('shippinginfo');
			if(!count($shippingAll) && $key==0){
				$ShippingCharges = $order->getShippingAmount();
				$data=array(
						'order_id'=>$lastOrderId,
						'item_ids'=>$product_ids,
						'seller_id'=>$key,
						'shipping_charges'=>$ShippingCharges
					);
			}
			$collection=Mage::getModel('marketplace/order');
			$collection->setData($data);
			$collection->save();
		}

		/*
		* Marketplace Order details save after Observer
		*/
		Mage::dispatchEvent('mp_order_save_after',array("order"=>$order));
	}
	
	public function getSalesdetail($mageproid)
	{
		$data = array(
				'quantitysoldconfirmed'=>0,
				'quantitysoldpending'=>0,
				'amountearned'=>0,
				'clearedat'=>0,
				'quantitysold'=>0,
				);
		$sum=0;
		$arr=array();
		$quantity = Mage::getModel('marketplace/saleslist')->getCollection()
						->addFieldToFilter('mageproid',array('eq'=>$mageproid));

	   foreach($quantity as $rec){
		 $status=$rec->getCpprostatus();
		 $data['quantitysold']=$data['quantitysold']+$rec->getMagequantity();
		 if($status==1){
			$data['quantitysoldconfirmed']=$data['quantitysoldconfirmed']+$rec->getMagequantity();
		 }else{
			$data['quantitysoldpending']=$data['quantitysoldpending']+$rec->getMagequantity();
		 }
	   }
		
		$amountearned = Mage::getModel('marketplace/saleslist')->getCollection()
			->addFieldToFilter('cpprostatus',array('eq'=>1));
		$amountearned->getSelect()->where('mageproid ='.$mageproid);
		foreach($amountearned as $rec) {
		$data['amountearned']=$data['amountearned']+$rec->getactualparterprocost();
		$arr[]=$rec->getClearedAt();
		}
		$data['clearedat']=$arr;
		return $data;
	}

	public function createdAt($mageproid)
	{
		$arr=array();
		$collection = Mage::getModel('catalog/product')->getCollection();
		$collection->addFieldToFilter('entity_id',array('eq' => $mageproid));
		foreach($collection as $rec) {
		$arr[]=$rec->getCreatedAt();
		}
		return $arr;
	}

	public function getDateDetail()
	{ 
		$session = Mage::getSingleton('customer/session'); 
		$cidvar = $session->getId();
		$collection = Mage::getModel('marketplace/saleslist')->getCollection()
									->addFieldToFilter('mageproownerid',array('eq'=>$cidvar))
									->addFieldToFilter('mageorderid',array('neq'=>0));
	    $collection1 = Mage::getModel('marketplace/saleslist')->getCollection()
									->addFieldToFilter('mageproownerid',array('eq'=>$cidvar))
									->addFieldToFilter('mageorderid',array('neq'=>0));
	    $collection2= Mage::getModel('marketplace/saleslist')->getCollection()
									->addFieldToFilter('mageproownerid',array('eq'=>$cidvar))
									->addFieldToFilter('mageorderid',array('neq'=>0));
	    $collection3 = Mage::getModel('marketplace/saleslist')->getCollection()
									->addFieldToFilter('mageproownerid',array('eq'=>$cidvar))
									->addFieldToFilter('mageorderid',array('neq'=>0));
		$first_day_of_week = date('Y-m-d', strtotime('Last Monday', time()));
		$last_day_of_week = date('Y-m-d', strtotime('Next Sunday', time()));
	    $month=$collection1->addFieldToFilter('cleared_at', array('datetime' => true,'from' =>  date('Y-m').'-01 00:00:00','to' =>  date('Y-m').'-31 23:59:59'));
	    $week=$collection2->addFieldToFilter('cleared_at', array('datetime' => true,'from' =>  $first_day_of_week.' 00:00:00','to' =>  $last_day_of_week.' 23:59:59'));
	    $day=$collection3->addFieldToFilter('cleared_at', array('datetime' => true,'from' =>  date('Y-m-d').' 00:00:00','to' =>  date('Y-m-d').' 23:59:59'));
	    $sale=0;

		$data1['year']=$sale;
		$sale1=0;
		foreach($day as $record1) {
			$sale1=$sale1+$record1->getActualparterprocost();
		}
		$data1['day']=$sale1;
		$sale2=0;
		foreach($month as $record2) {
			$sale2=$sale2+$record2->getActualparterprocost();
		}
		$data1['month']=$sale2;
		$sale3=0;
		foreach($week as $record3) {
			$sale3=$sale3+$record3->getActualparterprocost();
		}
		$data1['week']=$sale3;
	    $temp=0;
		foreach ($collection as $record) {
			$temp = $temp+$record->getActualparterprocost();
		}
		$data1['totalamount']=$temp;
		return $data1;
	}

	public function getMonthlysale()
	{
		$customerid = Mage::getSingleton('customer/session')->getId();
		$data=array();	
		$curryear = date('Y');
		for($i=1;$i<=12;$i++){
			$date1=$curryear."-".$i."-01 00:00:00";
			$date2=$curryear."-".$i."-31 23:59:59";
			$collection = Mage::getModel('marketplace/saleslist')->getCollection();
			$collection=$collection->addFieldToFilter('mageproownerid',array('eq'=>$customerid));
			$collection=$collection->addFieldToFilter('cleared_at', array('datetime' =>true,'from' =>  $date1,'to' =>  $date2));
		    $sum=array();
		    $temp=0;
			foreach ($collection as $record) {
				$temp = $temp+$record->getactualparterprocost();
			}
			$baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
			$currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
			$price = Mage::helper('directory')->currencyConvert($temp, $baseCurrencyCode, $currentCurrencyCode);
			$data[$i]=$price;
		}
		return json_encode($data);
	}

	public function getOrderdetails()
	{
		$customerid = Mage::getSingleton('customer/session')->getId();
		$collection = Mage::getModel('marketplace/saleslist')->getCollection();
		$collection->addFieldToFilter('mageproownerid',array('eq'=>$customerid))->setOrder('autoid','DESC');
		$userorder=array();
		$gropoid=array();
		$groporderid=array();
		$productname=array();
		$i=0; 
		foreach ($collection as $record) {
			$i++;
			if($i<=5){
				if(count($gropoid) && $record->getmagerealorderid()==$gropoid[$i-1]){
					$i--;
					$productid=$productid.",".$record->getmageproid();
					$productname=$productname.",".$record->getmageproname()." X ".$record->getmagequantity();
					$pprice=$pprice+$record->getactualparterprocost();
					$userorder[$i]=array('mageproid'=>$productid,
											'mageorderid'=>$record->getmageorderid(),
											'magerealorderid'=>$record->getmagerealorderid(),
											'mageproname'=>$productname,
											'actualparterprocost'=>$pprice,
											'cleared_at'=>$record->getcleared_at()
											);			
				}
				else{
					$productname=$record->getmageproname()." X ".$record->getmagequantity();
					$productid=$record->getmageproid();
					$pprice=$record->getactualparterprocost();
					$groporderid[$i]=$record->getmageorderid();
					$gropoid[$i]=$record->getmagerealorderid();
					$userorder[$i]=array('mageproid'=>$record->getmageproid(),
										'mageorderid'=>$record->getmageorderid(),
										'magerealorderid'=>$record->getmagerealorderid(),
										'mageproname'=>$productname,
										'actualparterprocost'=>$pprice,
										'cleared_at'=>$record->getcleared_at()
										);			
				}	
			}
		}
		return $userorder;	
	}

	public function getPaymentDetailById()
	{
		$customerid = Mage::getSingleton('customer/session')->getId();
		$collection = Mage::getModel('marketplace/userprofile')->getCollection();
		$collection->addFieldToFilter('mageuserid',array('eq'=>$customerid));
		foreach($collection as $row){
			$paymentsource=$row->getPaymentsource();
		}
		return $paymentsource;
	}

	public function getpronamebyorder($mageorderid)
	{
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$name='';
		$_collection = Mage::getModel('marketplace/saleslist')->getCollection();
		$_collection->addFieldToFilter('mageorderid',$mageorderid);	
		$_collection->addFieldToFilter('mageproownerid',$customerid);	
		foreach($_collection as $res){
			$products = Mage::getModel('catalog/product')->load($res['mageproid']);
			$name = $name."<p style='float:left;'><a href='".Mage::getUrl($products->getUrlPath())."' target='blank'>".$res['mageproname']."</a> X ".intval($res['magequantity'])."&nbsp;</p>";
		}	
		return $name;		
	}

	public function getPricebyorder($mageorderid)
	{
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$_collection = Mage::getModel('marketplace/saleslist')->getCollection();
		$_collection->getSelect()
					->where('mageproownerid ='.$customerid)
					->columns('SUM(actualparterprocost) AS qty')
					->group('mageorderid');		
		foreach($_collection as $coll){
			if($coll->getMageorderid() == $mageorderid){
				return $coll->getQty();
			}
		}
	}
}
