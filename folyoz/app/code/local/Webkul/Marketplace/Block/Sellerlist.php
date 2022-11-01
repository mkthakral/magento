<?php

class Webkul_Marketplace_Block_Sellerlist extends Mage_Core_Block_Template
{
    public function __construct(){      
        parent::__construct();

        $seller_arr = array();
        $seller_product_coll = Mage::getModel('marketplace/product')->getCollection()
                                ->addFieldToFilter('status',array('eq'=>1))
                                ->addFieldToSelect('userid')
                                ->distinct(true);
        foreach ($seller_product_coll as $value) {
            array_push($seller_arr, $value['userid']);
        }
        $collection = Mage::getModel('marketplace/userprofile')->getCollection()
                                ->addFieldToFilter('wantpartner',array('eq'=>1))
                                ->addFieldToFilter('mageuserid',array('in'=>$seller_arr))
                                ->setOrder('autoid','DESC');
        if(isset($_GET['shop']) && $_GET['shop']){
            $collection->addFieldToFilter('shoptitle',array('like'=>$_GET['shop'].'%'));
        }
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
        $this->setChild('pager', $pager);
        $this->getCollection()->load();
        return $this;
    }  
    
    public function getPagerHtml() {
        return $this->getChildHtml('pager');
    }
}
