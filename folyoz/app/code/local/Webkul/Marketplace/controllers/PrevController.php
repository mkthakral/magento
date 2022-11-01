<?php
class Webkul_Marketplace_PrevController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
		$id = $this->getRequest()->getParam('id');
		$products = Mage::getModel('catalog/product')->load($id)->toArray();
		$products['url']=Mage::getModel("catalog/product")->load($id)->getImageUrl();
		$products['downlink']=array();
		$links = Mage::getModel('downloadable/link')->getCollection()->addFieldTofilter('product_id',array('eq'=>$id));
		foreach($links as $link){
			$url=Mage::helper("adminhtml")->getUrl("adminhtml/downloadable_product_edit/link")."id/".$link->getId();
			$key="63176c0d831ce5655c620e0e0e1fa1be";
			$key = Mage::getSingleton('adminhtml/url')
             ->getSecretKey("downloadable_product_edit","link");
			$url=Mage::getBaseUrl()."admin/downloadable_product_edit/link/id/".$link->getId()."/key/".$key;
			$products['downlink'][]=$url;
		}
        $this->getResponse()->setHeader('Content-type', 'text/html');
		$this->getResponse()->setBody(json_encode($products));
    }
}