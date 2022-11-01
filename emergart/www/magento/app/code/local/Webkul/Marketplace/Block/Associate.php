<?php
class Webkul_Marketplace_Block_Associate extends Mage_Customer_Block_Account_Dashboard
{
	protected $_productsCollection = null;
	public function __construct(){		
		parent::__construct();	
    	$userId=Mage::getSingleton('customer/session')->getCustomer()->getId();
    	$wholedata=$this->getRequest()->getParams();
		$childIds = Mage::getModel('catalog/product_type_configurable')->getChildrenIds($wholedata['id']);
		$products=array();
		foreach($childIds[0] as $key=>$val){
			array_push($products,$key);
		}
		$searchid=$this->getRequest()->getParam('searchid')!=""?$this->getRequest()->getParam('searchid'):"";
		$filter=$this->getRequest()->getParam('s')!=""?$this->getRequest()->getParam('s'):"";
		$proattrset=$this->getRequest()->getParam('proattrset')!=""?$this->getRequest()->getParam('proattrset'):"";

		$collection = Mage::getModel('catalog/product')->getCollection()
						   ->addAttributeToSelect('*')
						   ->addFieldToFilter('name',array('like'=>"%".$filter."%"));
						   $collection->addFieldToFilter('entity_id',array('in'=>$products));
	    if($searchid){
	   		$collection->addFieldToFilter('entity_id',array('eq'=>$searchid));
		}
		if($proattrset){
	   		$collection->addFieldToFilter('attribute_set_id', array('eq'=>$proattrset));
		}
	    $collection->setOrder('entity_id','AESC');
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

    public function getAllowedSets(){
		$entityTypeId = Mage::getModel('eav/entity')
                ->setType('catalog_product')
                ->getTypeId();
        $data=array();
        $allowed=explode(',',Mage::helper('marketplace')->getAllowedAttributesetIds());
        $attributeSetCollection = Mage::getModel('eav/entity_attribute_set')
                        ->getCollection()
                        ->addFieldToFilter('attribute_set_id',array('in'=>$allowed))
                        ->setEntityTypeFilter($entityTypeId);
        foreach($attributeSetCollection as $_attributeSet){
            array_push($data,array('value'=>$_attributeSet->getData('attribute_set_id'), 'label'=>$_attributeSet->getData('attribute_set_name')));
        }
        return $data;
	}
	
	public function getConfigurableProduct() {
		$id = $this->getRequest()->getParam('id');
		$products = Mage::getModel('catalog/product')->load($id);
		return $products;
	}
	public function getAttributeOptions($attributecode){
		$attributeId = Mage::getResourceModel('eav/entity_attribute')->getIdByCode('catalog_product',$attributecode);
		$attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
		return $attribute->getSource()->getAllOptions();
	}
}
