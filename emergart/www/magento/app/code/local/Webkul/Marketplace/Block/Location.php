<?php
class Webkul_Marketplace_Block_Location extends Mage_Core_Block_Template
{
	public function _prepareLayout(){
		$partner=$this->getProfileDetail();
		if($partner->getShoptitle()!='') {
			$shop_title = $partner->getShoptitle();
		}
		else {
			$shop_title = $partner->getProfileurl();
		}
		$this->getLayout()->getBlock('head')->setTitle(Mage::helper('marketplace')->__("%s's Location",$shop_title));
		$this->getLayout()->getBlock('head')->setKeywords($partner->getMetaKeyword());	
		$this->getLayout()->getBlock('head')->setDescription($partner->getMetaDescription());
		return parent::_prepareLayout();
    }
    
	public function getProfileDetail(){
		$profileurl = Mage::helper('marketplace')->getLocationUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ return $seller;}
		}
	}
}