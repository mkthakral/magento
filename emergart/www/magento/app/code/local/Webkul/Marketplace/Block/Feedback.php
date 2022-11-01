<?php
class Webkul_Marketplace_Block_Feedback extends Mage_Core_Block_Template
{	
	public function __construct(){		
		parent::__construct();
    	$userId=$this->getProfileDetail()->getMageuserid();
		$collection = Mage::getModel('marketplace/feedback')->getCollection()
						   ->addFieldToFilter('status',array('neq'=>0))
						   ->addFieldToFilter('proownerid',array('eq'=>$userId));
		$this->setCollection($collection);
	}
	
    protected function _prepareLayout() {
        parent::_prepareLayout(); 
        $pager = $this->getLayout()->createBlock('page/html_pager', 'custom.pager');
        $grid_per_page_values = explode(",",Mage::helper('marketplace')->getCatatlogGridPerPageValues());
        $arr_perpage = array();
        foreach ($grid_per_page_values as $value) {
        	$arr_perpage[$value] = $value;
        }
        $pager->setAvailableLimit($arr_perpage);
        $pager->setCollection($this->getCollection());
        $partner=$this->getProfileDetail();
		if($partner->getShoptitle()!='') {
			$shop_title = $partner->getShoptitle();
		}
		else {
			$shop_title = $partner->getProfileurl();
		}
		$this->getLayout()->getBlock('head')->setTitle(Mage::helper('marketplace')->__("%s's Feedback",$shop_title));
        $this->setChild('pager', $pager);
        $this->getCollection()->load();
        return $this;
    } 
	public function getPagerHtml() {
        return $this->getChildHtml('pager');
    }
    public function getCustomerpartner(){ 
        if (!$this->hasData('marketplace')) {
            $this->setData('marketplace', Mage::registry('marketplace'));
        }
		$id=$_GET["id"];
		return $id;   
    }
	
	public function getProfileDetail(){
		$profileurl = Mage::helper('marketplace')->getFeedbackUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ return $seller;}
		}
	}
	public function getFeed(){
		$profileurl = Mage::helper('marketplace')->getFeedbackUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ $id=$seller->getMageuserid();}
		}
		return Mage::getModel('marketplace/feedback')->getTotal($id);
	}
}