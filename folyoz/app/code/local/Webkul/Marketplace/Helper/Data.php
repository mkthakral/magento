<?php
class Webkul_Marketplace_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function getConfigCommissionRate()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/percent',$current_store);
	}

	public function getConfigTaxMange()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/taxmanage',$current_store);
	}

	public function getMarketplaceHeadLabel()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_landingpage/marketplacelabel',$current_store);
	}

	public function getMarketplacelabel1()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_landingpage/marketplacelabel1',$current_store);
	}

	public function getMarketplacelabel2()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_landingpage/marketplacelabel2',$current_store);
	}
	
	public function getMarketplacelabel3()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_landingpage/marketplacelabel3',$current_store);
	}
	
	public function getMarketplacelabel4()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_landingpage/marketplacelabel4',$current_store);
	}
	
	public function getDisplayBanner()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_landingpage/displaybanner',$current_store); 
	}
	
	public function getBannerImage()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."marketplace/banner/".Mage::getStoreConfig("marketplace/marketplace_landingpage/banner",$current_store); 
	}
	
	public function getBannerContent()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/bannercontent',$current_store);
	}

	public function getDisplayIcon()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/displayicons',$current_store);
	}

	public function getIconImage1()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."marketplace/icon/".Mage::getStoreConfig("marketplace/marketplace_landingpage/feature_icon1",$current_store);
	}

	public function getIconImageLabel1()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/feature_icon1_label',$current_store);
	}

	public function getIconImage2()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."marketplace/icon/".Mage::getStoreConfig("marketplace/marketplace_landingpage/feature_icon2",$current_store);
	}

	public function getIconImageLabel2()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/feature_icon2_label',$current_store);
	}

	public function getIconImage3()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."marketplace/icon/".Mage::getStoreConfig("marketplace/marketplace_landingpage/feature_icon3",$current_store);
	}

	public function getIconImageLabel3()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/feature_icon3_label',$current_store);
	}

	public function getIconImage4()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)."marketplace/icon/".Mage::getStoreConfig("marketplace/marketplace_landingpage/feature_icon4",$current_store);
	}

	public function getIconImageLabel4()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/feature_icon4_label',$current_store);
	}

	public function getMarketplacebutton()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/marketplacebutton',$current_store);
	}

	public function getMarketplaceprofile()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/marketplaceprofile',$current_store);
	}

	public function getSellerlisttopLabel()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/sellerlisttop',$current_store);
	}

	public function getSellerlistbottomLabel()
	{
		$current_store = Mage::app()->getStore();
		return  Mage::getStoreConfig('marketplace/marketplace_landingpage/sellerlistbottom',$current_store);
	}

	public function getProfileUrl()
	{
		$target_url = $this->getTargetUrlPath();
        if($target_url==''){
        	$target_url = Mage::helper('core/url')->getCurrentUrl();
        }
		$temp=explode('/profile',$target_url);
		if(!isset($temp[1])){
			$temp[1]='';
		}
		$temp=explode('/',$temp[1]);
		if($temp[1]!=''){
            $temp1 = explode('?', $temp[1]);
            return $temp1[0];
        }
        return false;
	}

	public function getCollectionUrl()
	{
		$target_url = $this->getTargetUrlPath();
        if($target_url==''){
        	$target_url = Mage::helper('core/url')->getCurrentUrl();
        }
		$temp=explode('/collection',$target_url);
		if(!isset($temp[1])){
			$temp[1]='';
		}
		$temp=explode('/',$temp[1]);
		if($temp[1]!=''){
            $temp1 = explode('?', $temp[1]);
            return $temp1[0];
        }
        return false;
	}

	public function getLocationUrl()
	{
		$target_url = $this->getTargetUrlPath();
        if($target_url==''){
        	$target_url = Mage::helper('core/url')->getCurrentUrl();
        }
		$temp=explode('/location',$target_url);
		if(!isset($temp[1])){
			$temp[1]='';
		}
		$temp=explode('/',$temp[1]);
		if($temp[1]!=''){
            $temp1 = explode('?', $temp[1]);
            return $temp1[0];
        }
        return false;
	}

	public function getFeedbackUrl()
	{
		$target_url = $this->getTargetUrlPath();
        if($target_url==''){
        	$target_url = Mage::helper('core/url')->getCurrentUrl();
        }
		$temp=explode('/feedback',$target_url);
		if(!isset($temp[1])){
			$temp[1]='';
		}
		$temp=explode('/',$temp[1]);
		if($temp[1]!=''){
            $temp1 = explode('?', $temp[1]);
            return $temp1[0];
        }
        return false;
	}

	public function getSelleRating($seller_id)
	{
		$feeds = Mage::getModel('marketplace/feedback')->getTotal($seller_id);
		$total_rating = ($feeds['price']+$feeds['value']+$feeds['quality'])/60;		
		return round($total_rating, 1, PHP_ROUND_HALF_UP);
	}

	public function getFeed($seller_id){
		return Mage::getModel('marketplace/feedback')->getTotal($seller_id);
	}

	public function getRewriteUrlPath($target_url){
		$request_path = '';
		$url_coll = Mage::getModel('core/url_rewrite')->getCollection()
						->addFieldToFilter('target_path',array('eq'=>$target_url))
						->addFieldToFilter('store_id',array('eq'=>Mage::app()->getStore()->getStoreId()));
		foreach ($url_coll as $value) {
			$request_path = $value->getRequestPath();
		}
		return $request_path;
	}

	public function getRewriteUrl($target_url){
		$request_url = Mage::getUrl().$target_url;
		$url_coll = Mage::getModel('core/url_rewrite')->getCollection()
						->addFieldToFilter('target_path',array('eq'=>$target_url))
						->addFieldToFilter('store_id',array('eq'=>Mage::app()->getStore()->getStoreId()));
		foreach ($url_coll as $value) {
			$request_url = Mage::getUrl().$value->getRequestPath();
		}
		return $request_url;
	}

	public function getTargetUrlPath(){
		$urls = explode(Mage::getBaseUrl(),Mage::helper('core/url')->getCurrentUrl());
		$target_url = '';
		$temp = explode('/?',$urls[1]);
		if(!isset($temp[1])){
			$temp[1]='';
		}
		if(!$temp[1]){
			$temp=explode('?',$temp[0]);
		}
		$request_path=$temp[0];	
		$url_coll = Mage::getModel('core/url_rewrite')->getCollection()
						->addFieldToFilter('request_path',array('eq'=>$request_path))
						->addFieldToFilter('store_id',array('eq'=>Mage::app()->getStore()->getStoreId()));
		foreach ($url_coll as $value) {
			$target_url = $value->getTargetPath();
		}
		return $target_url;
	}

	public function storeImageExtension(){
		return Mage::getStoreConfig('marketplace/marketplace_options/uploadimagetype',Mage::app()->getStore()->getStoreId());
	}

	public function storeSampleExtension(){
		return Mage::getStoreConfig('marketplace/marketplace_options/samplefiletype',Mage::app()->getStore()->getId());
	}

	public function storeLinkExtension(){
		return Mage::getStoreConfig('marketplace/marketplace_options/downloadfiletype',Mage::app()->getStore()->getId());
	}


	public function getAllowedImageExtension(){
		$allowed_images_arr = array();
		$uploadimagetype = explode(",",$this->storeImageExtension());
		foreach ($uploadimagetype as $value) {
			array_push($allowed_images_arr,'"'.trim($value).'"');
		}
		return $allowed_images = implode(',', $allowed_images_arr);
	}

	public function getAllowedSampleExtension(){
		$sample_allow_extension = explode(',',$this->storeSampleExtension());
		$sample_extensions = array();
		foreach ($sample_allow_extension as $value) {
			array_push($sample_extensions, '"'.trim($value).'"');					
		}
		return $supported_samples = implode(',', $sample_extensions);
	}

	public function getAllowedSampleExtensionNotify(){
		$sample_allow_extension = explode(',',$this->storeSampleExtension());
		$sample_extensions_notify = array();
		foreach ($sample_allow_extension as $value) {
			array_push($sample_extensions_notify, '*.'.trim($value));
		}
		return $supported_samples_notify = implode(',', $sample_extensions_notify);		 
	}

	public function getAllowedLinkExtension(){
		$link_allow_extension = explode(',',$this->storeLinkExtension());
		$link_extensions = array();
		foreach ($link_allow_extension as $value) {
			array_push($link_extensions, '"'.trim($value).'"');				
		}
		return $supported_links = implode(',', $link_extensions);
	}

	public function getAllowedLinkExtensionNotify(){
		$link_allow_extension = explode(',',$this->storeLinkExtension());
		$link_extensions_notify = array();
		foreach ($link_allow_extension as $value) {
			array_push($link_extensions_notify, '*.'.trim($value));					
		}
		return $supported_links_notify = implode(',', $link_extensions_notify);	
	}

	public function getSellerProCount($seller_id){
		$querydata = Mage::getModel('marketplace/product')->getCollection()
							->addFieldToFilter('userid', array('eq' => $seller_id))
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
		return count($rowdata);
	}

	public function getlowStockNotification()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_inventory/low_stock_notification',$current_store);
	}

	public function getlowStockQty()
	{
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_inventory/low_stock_amount',$current_store);
	}

	public function getDefaultTransEmailId(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('trans_email/ident_general/email',$current_store);
	}

	public function getAdminEmailId(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/adminemail',$current_store);
	}

	public function getIsProductApproval(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/product_approval',$current_store);
	}

	public function getIsProductEditApproval(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/product_edit_approval',$current_store);
	}

	public function getIsPartnerApproval(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/partner_approval',$current_store);
	}

	public function getAllowedAttributesetIds(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/attributesetid',$current_store);
	}

	public function getAllowedProductType(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/allow_for_seller',$current_store);
	}

	public function getUseCommissionRule(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('mpadvancecommission/mpadvancecommission_options/usecommissionrule',$current_store);
	}	

	public function getCommissionType(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('mpadvancecommission/mpadvancecommission_options/commissiontype',$current_store);		
	}

	public function getCatatlogGridPerPageValues(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('catalog/frontend/grid_per_page_values',$current_store);		
	}

	public function getAllowedCategoryIds(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/categoryids',$current_store);	
	}

	public function getProductHintStatus(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_hint_status',$current_store);	
	}

	public function getProductCategoryHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_category',$current_store);	
	}

	public function getProductNameHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_name',$current_store);	
	}

	public function getProductDescHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_des',$current_store);	
	}

	public function getProductShortDescHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_sdes',$current_store);	
	}

	public function getProductSkuHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_sku',$current_store);	
	}

	public function getProductPriceHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_price',$current_store);	
	}

	public function getProductSpecialPriceHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_sprice',$current_store);	
	}

	public function getProductSpecialFromDateHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_sdate',$current_store);	
	}

	public function getProductSpecialToDateHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_edate',$current_store);	
	}

	public function getProductStockHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_qty',$current_store);	
	}

	public function getProductStockAvailabilityHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_stock',$current_store);	
	}

	public function getProductTaxHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_tax',$current_store);	
	}

	public function getProductImageHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_image',$current_store);	
	}

	public function getProductWeightHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_weight',$current_store);	
	}

	public function getProductEnableStatusHint(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_products/product_enable',$current_store);	
	}

	public function getReviewStatus(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_review/review_status',$current_store);	
	}

	public function getIsOrderManage(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_options/ordermanage',$current_store);	
	}

	public function getProfileHintStatus(){
		$current_store = Mage::app()->getStore();
		return Mage::getStoreConfig('marketplace/marketplace_profile/profile_hint_status',$current_store);	
	}
}