<?php

class Webkul_Marketplace_Model_Feedback extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('marketplace/feedback');
    }
	public function getTotal($partnerId){
		$data=array();
		if($partnerId > 0){
			$collection = Mage::getModel('marketplace/feedback')->getCollection();
			$collection->addFieldToFilter('proownerid',array('eq'=>$partnerId));
			$collection->addFieldToFilter('status',array('neq'=>0));
			$price=0;
			$value=0;
			$quality=0;
			$totalfeed=0;
			$feed_count = 0;
			$collection_count = 1;
			foreach($collection as $record) {
				$price+=$record->getfeedprice();
				$value+=$record->getfeedvalue();
				$quality+=$record->getfeedquality();
			}
			if(count($collection)!=0)
			{
				$feed_count = count($collection);
				$collection_count = count($collection);
				$totalfeed=ceil(($price+$value+$quality)/(3*$collection_count));
		    }
		    
			$data=array('price'=>$price/$collection_count,'value'=>$value/$collection_count,'quality'=>$quality/$collection_count,'totalfeed'=>$totalfeed, 'feedcount'=>$feed_count);
			return $data;
		}
	}
	public function saveFeedbackdetail($wholedata)
	{	
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$mail = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
		$wholedata['userid']=$customerid;
		$wholedata['useremail']=$mail; 
		$wholedata['createdat']=date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
		$feedbackcount = 0;
		$collectionfeed=Mage::getModel('marketplace/feedbackcount')->getCollection()
							->addFieldToFilter('buyerid',array('eq'=>$customerid))
							->addFieldToFilter('sellerid',array('eq'=>$wholedata['proownerid']));
		foreach ($collectionfeed as $value) {
			$feedcountid = $value->getFeedcountid();
			$ordercount = $value->getOrdercount();
			$feedbackcount = $value->getFeedbackcount();
			$value->setFeedbackcount($feedbackcount+1);
			$value->save();
		}
		$collection=Mage::getModel('marketplace/feedback');
		$collection->setData($wholedata);
		$collection->save();
		return 0;
	}
}
