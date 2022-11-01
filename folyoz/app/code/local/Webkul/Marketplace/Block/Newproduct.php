<?php
class Webkul_Marketplace_Block_Newproduct extends Mage_Customer_Block_Account_Dashboard
{
	public function _prepareLayout()
    {
		return parent::_prepareLayout();
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

	public function getAllowedProductTypes(){
		$alloweds=explode(',',Mage::helper('marketplace')->getAllowedProductType());
		$helper = Mage::helper('marketplace');
		$data =  array('simple'=>$helper->__('Simple'),
						'downloadable'=>$helper->__('Downloadable'),
					    'virtual'=>$helper->__('Virtual'),
						'configurable'=>$helper->__('Configurable'),
						'grouped'=>$helper->__('Grouped Product'),
						'bundle'=>$helper->__('Bundle Product')
			);
		$allowedproducts=array();
		foreach($alloweds as $allowed){
			array_push($allowedproducts,array('value'=>$allowed, 'label'=>$data[$allowed]));
		}
		return $allowedproducts;
	}
}
