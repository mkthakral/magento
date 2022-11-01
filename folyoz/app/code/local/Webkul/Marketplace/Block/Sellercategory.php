<?php
class Webkul_Marketplace_Block_Sellercategory extends Mage_Core_Block_Template
{
	public function _prepareLayout()
    {
		return parent::_prepareLayout();
    }
	
	public function getCategoryList(){
		$sellerid=$this->getProfileDetail()->getmageuserid();
		$products=Mage::getModel('marketplace/product')->getCollection()
							->addFieldToFilter('userid',array('eq'=>$sellerid))
							->addFieldToFilter('status', array('neq' => 2))
							->addFieldToSelect('mageproductid');
		$rowdata=array();		
		foreach ($products as  $value) {
            $stock_item_details = Mage::getModel('cataloginventory/stock_item')->loadByProduct($value->getMageproductid());
            $stock_availability = $stock_item_details->getIsInStock();
            
        	$stock_item_qty = $stock_item_details->getQty()*1;

            $product = Mage::getModel('catalog/product')->load($value->getMageproductid());

            if($product->getTypeId()=='configurable' && $product['has_options']){
            	$stock_item_qty = 1;
            }

            if($stock_availability && $product->isVisibleInCatalog() && $product->isVisibleInCatalog() && $product->isVisibleInSiteVisibility() && $stock_item_qty){
                $rowdata[] = $value->getMageproductid();
            }			
		}		
		
		$products=Mage::getModel('marketplace/product')->getCollection()
							->addFieldToFilter('mageproductid',array('in'=>$rowdata))
							->addFieldToSelect('mageproductid');

		$eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
        $pro_att_id = $eavAttribute->getIdByCode("catalog_category","name");

        $storeId = Mage::app()->getStore()->getStoreId();
        if(!isset($_GET["c"])){
			$_GET["c"] ='';
		}
       	if(!$_GET["c"]){
        	$parentid = Mage::app()->getStore($storeId)->getRootCategoryId();
        }else{
        	$parentid = $_GET["c"];
        }

		$prefix = Mage::getConfig()->getTablePrefix();
		$products->getSelect()
        ->join(array("ccp" => $prefix."catalog_category_product"),"ccp.product_id = main_table.mageproductid",array("category_id" => "category_id"))
        ->join(array("cce" => $prefix."catalog_category_entity"),"cce.entity_id = ccp.category_id",array("parent_id" => "parent_id"))->where("cce.parent_id = '".$parentid."'")
        ->columns('COUNT(*) AS countCategory')
        ->group('category_id')
        ->join(array("ce1" => $prefix."catalog_category_entity_varchar"),"ce1.entity_id = ccp.category_id",array("name" => "value"))->where("ce1.attribute_id = ".$pro_att_id)
        ->order('name');
        return $products;
	}
	
	public function getProfileDetail(){
		$profileurl = Mage::helper('marketplace')->getCollectionUrl();
		if($profileurl){
			$data=Mage::getModel('marketplace/userprofile')->getCollection()
						->addFieldToFilter('profileurl',array('eq'=>$profileurl));
			foreach($data as $seller){ return $seller;}
		}
	}    
}