<?php
class Webkul_Marketplace_Block_Collection  extends Mage_Catalog_Block_Product_Abstract
{
    public function __construct(){      
        parent::__construct();
        if(array_key_exists('c', $_GET)){   
            $cate = Mage::getModel('catalog/category')->load($_GET["c"]);
            Mage::register('current_category', $cate);
        }   
        $partner=$this->getProfileDetail();
        $productname=$this->getRequest()->getParam('name');
        $querydata = Mage::getModel('marketplace/product')->getCollection()
                ->addFieldToFilter('userid', array('eq' => $partner->getmageuserid()))
                ->addFieldToFilter('status', array('neq' => 2))
                ->setOrder('mageproductid');
        $rowdata=array();       
        foreach ($querydata as  $value) {
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
        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection->addAttributeToSelect('*');
        
        if(array_key_exists('c', $_GET)){
            $collection->addCategoryFilter($cate);
        }
        $collection->addAttributeToFilter('entity_id', array('in' => $rowdata));
        if((Mage::helper('core')->isModuleEnabled('Webkul_Webkulsearch')) && ($productname!='')){
            $collection->addFieldToFilter('name', array('like' => '%'.$productname.'%'));   
        }
        $this->setCollection($collection);  
    }

    protected function _prepareLayout() {
        parent::_prepareLayout();       
        $toolbar = $this->getToolbarBlock();
        $collection = $this->getCollection();
        
        if ($orders = $this->getAvailableOrders()) {
           $toolbar->setAvailableOrders($orders);
        }
        if ($sort = $this->getSortBy()) {
            $toolbar->setDefaultOrder($sort);
        }
        if ($dir = $this->getDefaultDirection()) {
            $toolbar->setDefaultDirection($dir);
        }
        $toolbar->setCollection($collection);
 
        $this->setChild('toolbar', $toolbar);

        $this->getCollection()->load(); 

        $partner=$this->getProfileDetail();     
        if($partner->getShoptitle()!='') {
            $shop_title = $partner->getShoptitle();
        }
        else {
            $shop_title = $partner->getProfileurl();
        }
        $this->getLayout()->getBlock('head')->setTitle(Mage::helper('marketplace')->__("%s's Collection",$shop_title));

        $this->getLayout()->getBlock('head')->setKeywords($partner->getMetaKeyword());      
        $this->getLayout()->getBlock('head')->setDescription($partner->getMetaDescription());

        $helper=Mage::helper('marketplace');
        if ($breadcrumbs = $this->getLayout()->getBlock('breadcrumbs')) {
            $breadcrumbs->addCrumb('home',array(
                'label'=>$helper->__('Home'), 
                'title'=>$helper->__('Go to Home Page'), 
                'link'=>Mage::getBaseUrl()
            ));
            $title = array();
            $path  = $this->getBreadcrumbPath();

            foreach ($path as $name => $breadcrumb) {
                $breadcrumbs->addCrumb($name, $breadcrumb);
                $title[] = $breadcrumb['label'];
            }
        }
        return $this;
    }
    
    public function getProfileDetail(){
        $profileurl = Mage::helper('marketplace')->getCollectionUrl();
        if($profileurl){
            $data=Mage::getModel('marketplace/userprofile')->getCollection()
                        ->addFieldToFilter('profileurl',array('eq'=>$profileurl));
            foreach($data as $seller){ return $seller;}
        }
    }
    /**
     * Breadcrumb Path cache
     *
     * @var string
     */
    protected $_categoryPath;
    /**
     * Return current category path or get it from current category
     * and creating array of categories paths for breadcrumbs
     *
     * @return string
     */
    public function getBreadcrumbPath()
    {
        if (!$this->_categoryPath) {

            $path = array();
            if ($category = $this->getCategory()) {

                $pathInStore = $category->getPathInStore();

                $pathIds = array_reverse(explode(',', $pathInStore));

                $categories = $category->getParentCategories();

                $profile_url = $this->getProfileDetail()->getprofileurl();

                $currentUrl = $this->helper('core/url')->getCurrentUrl();

                $helper = Mage::helper('marketplace');

                // add category path breadcrumb
                foreach ($pathIds as $categoryId) {
                    if (isset($categories[$categoryId]) && $categories[$categoryId]->getName()) {
                        $link_url = $helper->getRewriteUrl('marketplace/seller/collection/'.$profile_url)."/?c=".$categoryId;
                        $arr_link = explode($link_url,$currentUrl);

                        if(!$arr_link[0]){
                            $path_link_url = '';
                        }else{
                            $path_link_url = $link_url;
                        }                                              
                        $path['category'.$categoryId] = array(
                            'label' => $categories[$categoryId]->getName(),
                            'link' => $path_link_url
                        );
                    }
                }
            }
            $this->_categoryPath = $path;
        }
        return $this->_categoryPath;
    }

    /**
     * Return current category object
     *
     * @return Mage_Catalog_Model_Category|null
     */
    public function getCategory()
    {
        return Mage::registry('current_category');
    }    
    public function getDefaultDirection(){
        return 'asc';
    }
    public function getAvailableOrders(){
        $helper=Mage::helper('marketplace');
        return array('price'=>$helper->__('Price'),'name'=>$helper->__('Name'));
    }
    public function getSortBy(){
        return 'collection_id';
    }
    public function getToolbarBlock(){
       $block = $this->getLayout()->createBlock('marketplace/toolbar', microtime());
        return $block;
    }
    public function getMode()
    {
        return $this->getChild('toolbar')->getCurrentMode();
    }
 
    public function getToolbarHtml()   {
        return $this->getChildHtml('toolbar');
    }
    public function getColumnCount() {
        return 4;
    } 
}
