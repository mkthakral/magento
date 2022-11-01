<?php
class Webkul_Marketplace_Block_Sellercategory extends Mage_Core_Block_Template
{
	public function _prepareLayout()
    {
		return parent::_prepareLayout();
    }
	
	public function getCategoryList(){
		$sellerid=$this->getProfileDetail()->getmageuserid();
		$querydata = Mage::getModel('marketplace/product')->getCollection()
                ->addFieldToFilter('userid', array('eq' => $sellerid))
                ->addFieldToFilter('status', array('neq' => 2))
                ->addFieldToSelect('mageproductid')
                ->setOrder('mageproductid');
        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection->addAttributeToSelect('*');
        
        $collection->addAttributeToFilter('entity_id', array('in' => $querydata->getData()));
        $collection-> addAttributeToFilter('visibility', array('in' => array(4) )); 
        $collection->addStoreFilter();
        //Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($collection);
        Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
        //Mage::getSingleton("catalog/product_visibility")->addVisibleInSearchFilterToCollection($collection);

        $collectionConfigurable = Mage::getResourceModel('catalog/product_collection')
        						->addAttributeToFilter('type_id', array('eq' => 'configurable'))
        						->addAttributeToFilter('entity_id', array('in' => $querydata->getData()));

		$outOfStockConfis = array();
		foreach ($collectionConfigurable as $_configurableproduct) {
		    $product = Mage::getModel('catalog/product')->load($_configurableproduct->getId());
		    if (!$product->getData('is_salable')) {
		       $outOfStockConfis[] = $product->getId();
		    }
		}
		if(count($outOfStockConfis)){
			$collection->addAttributeToFilter('entity_id',array('nin' => $outOfStockConfis));
		}

		$collectionBundle = Mage::getResourceModel('catalog/product_collection')
        						->addAttributeToFilter('type_id', array('eq' => 'bundle'))
        						->addAttributeToFilter('entity_id', array('in' => $querydata->getData()));
		$outOfStockConfis = array();
		foreach ($collectionBundle as $_bundleproduct) {
		    $product = Mage::getModel('catalog/product')->load($_bundleproduct->getId());
		    if (!$product->getData('is_salable')) {
		       $outOfStockConfis[] = $product->getId();
		    }
		}
		if(count($outOfStockConfis)){
			$collection->addAttributeToFilter('entity_id',array('nin' => $outOfStockConfis));
		}

		$collectionGrouped = Mage::getResourceModel('catalog/product_collection')
        						->addAttributeToFilter('type_id', array('eq' => 'bundle'))
        						->addAttributeToFilter('entity_id', array('in' => $querydata->getData()));
		$outOfStockConfis = array();
		foreach ($collectionGrouped as $_groupedproduct) {
		    $product = Mage::getModel('catalog/product')->load($_groupedproduct->getId());
		    if (!$product->getData('is_salable')) {
		       $outOfStockConfis[] = $product->getId();
		    }
		}
		if(count($outOfStockConfis)){
			$collection->addAttributeToFilter('entity_id',array('nin' => $outOfStockConfis));
		}
		
		$products=Mage::getModel('marketplace/product')->getCollection()
							->addFieldToFilter('mageproductid',array('in'=>$collection->getData()))
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