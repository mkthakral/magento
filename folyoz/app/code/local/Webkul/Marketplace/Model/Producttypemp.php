<?php

class Webkul_Marketplace_Model_Producttypemp
{
   	public function toOptionArray(){
   		$helper = Mage::helper('marketplace');
        $data =  array(array('value'=>'simple', 'label'=>$helper->__('Simple')),
						array('value'=>'downloadable', 'label'=>$helper->__('Downloadable')),
						array('value'=>'virtual', 'label'=>$helper->__('Virual')),
						array('value'=>'configurable', 'label'=>$helper->__('Configurable'))
		);
		if(Mage::helper('core')->isModuleEnabled('Webkul_Mpbundleproduct')){
			array_push($data,array('value'=>'bundle', 'label'=>$helper->__('Bundle Product')));
		}
		if(Mage::helper('core')->isModuleEnabled('Webkul_Mpgroupproduct')){
			array_push($data,array('value'=>'grouped', 'label'=>$helper->__('Grouped Product')));
		}
		return  $data;
    }
}
