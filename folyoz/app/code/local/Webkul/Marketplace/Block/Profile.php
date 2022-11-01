<?php
class Webkul_Marketplace_Block_Profile extends Mage_Core_Block_Template
{
	public function _prepareLayout(){
		$partner=$this->getProfileDetail();
		if($partner->getShoptitle()!='') {
            $shop_title = $partner->getShoptitle();
        }
        else {
            $shop_title = $partner->getProfileurl();
        }
        $this->getLayout()->getBlock('head')->setTitle(Mage::helper('marketplace')->__("%s Shop",$shop_title));
		$this->getLayout()->getBlock('head')->setKeywords($partner->getMetaKeyword());	
		$this->getLayout()->getBlock('head')->setDescription($partner->getMetaDescription());
		return parent::_prepareLayout();
    }
    
	public function getProfileDetail(){
		$profileurl = Mage::helper('marketplace')->getProfileUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ return $seller;}
		}
	}
	
	public function getFeed(){
		$id='';
		$profileurl = Mage::helper('marketplace')->getProfileUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ 
				$id=$seller->getMageuserid();
			}
		}
		if($id){
			return Mage::getModel('marketplace/feedback')->getTotal($id);
		}
	}
	
	public function getBestsellProducts(){		
		$products=array();
		$id='';
		$profileurl = Mage::helper('marketplace')->getProfileUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ 
				$id=$seller->getmageuserid();
			}
			if($id){
				$data=Mage::getModel('marketplace/product')->getCollection()
										->addFieldToFilter('userid',array('eq'=>$id))
										->addFieldToFilter('status',array('neq'=>2));
				$data ->getSelect()->group('mageproductid');
				$data->setOrder('mageproductid', 'DESC');
				$i=0;
			   	foreach($data as $data1){
			   		$stock_item_details = Mage::getModel('cataloginventory/stock_item')->loadByProduct($data1->getMageproductid());
		            $stock_availability = $stock_item_details->getIsInStock();

		            $product = Mage::getModel('catalog/product')->load($data1->getMageproductid());

		            if($stock_availability && $product->isVisibleInCatalog() && $product->isVisibleInSiteVisibility()){
		            	$i++;
		            	if($i<=6){
		                	array_push($products,$data1->getMageproductid());
		            	}
		            }					
				}

			}	
		}
		return $products;
	}
}