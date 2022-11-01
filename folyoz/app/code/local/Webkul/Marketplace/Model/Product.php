<?php

class Webkul_Marketplace_Model_Product extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('marketplace/product');
    }

    /**
     * Check is product configurable
     *
     * @return bool
     */
    public function isConfigurable($type_id)
    {
        return $type_id == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE;
    }

    public function saveProductData($id,$wholedata){
    	$wholedata['use_config_manage_stock'] = 0;

    	if($id){
    		$wholedata['id'] = $id;
			Mage::dispatchEvent('mp_customattribute_deletetierpricedata', $wholedata);
		}

		/**
         * Initialize product categories
         */

		$cats=array();
		if(isset($wholedata['category'])){
			foreach($wholedata['category'] as $keycat){
				array_push($cats,$keycat);
			}
		}
		if(isset($wholedata['status']) && $wholedata['status'] && $id){
			$status=$wholedata['status']; 
		}
		else{		
			$status=Mage::helper('marketplace')->getIsProductApproval()? 2:1;
		}

		$storeId=Mage::app()->getStore()->getStoreId();

		$baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
		$currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
		$allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies(); 
		$rates = Mage::getModel('directory/currency')->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
		if(!$rates[$currentCurrencyCode]){
			$rates[$currentCurrencyCode] = 1;
		}

		Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

		$magentoProductModel = Mage::getModel('catalog/product')->load($id);

		if($id){
			$magentoProductModel->setStoreId($storeId);
			$wholedata['attribute_set_id'] = $magentoProductModel->getAttributeSetId();
			$wholedata['type_id'] = $magentoProductModel->getTypeId();
			foreach($wholedata as $key=>$val)
			{
				$magentoProductModel->setData($key,$val);
			}
			//To stop Magento regenerating url-key for store, set following,
    		$magentoProductModel->setUrlKey(false);
			$saved=$magentoProductModel->save();
		}else{
			$magentoProductModel->setData($wholedata);
			$magentoProductModel->save();
		}	

		if(isset($wholedata['special_price']) && $wholedata['special_price']){
			$special_price = $wholedata['special_price']/$rates[$currentCurrencyCode];
			$magentoProductModel->setSpecialPrice($special_price);
		}
		if(isset($wholedata['price']) && $wholedata['price']){
			$price = $wholedata['price']/$rates[$currentCurrencyCode];
			$magentoProductModel->setPrice($price);
		}
		$magentoProductModel->setStoresIds(array($storeId));
		$magentoProductModel->setWebsiteIds(array(Mage::getModel('core/store')->load( $storeId )->getWebsiteId()));
		if($this->isConfigurable($wholedata['type_id'])){
			$attr = array();
			if(isset($wholedata['supperattr']) && $wholedata['supperattr']){
				$attr=explode(',',$wholedata['supperattr']);
			}

			$attributeId = 0;

			if(isset($attr[0]) && $attr[0]){
				$attributeId = Mage::getResourceModel('eav/entity_attribute')->getIdByCode('catalog_product',$attr[0]);
			}
			 
			if($attributeId){
				$magentoProductModel->getTypeInstance()->setUsedProductAttributeIds(array($attributeId));
			}
			if (array_key_exists('asso_pro', $wholedata)) {
				$asspro = $wholedata['asso_pro'];
				$data[$asspro] = array();
			}
			$i = 0;	 
			$configurable_attributes_data = '';
			foreach($attr as $attrCode){
				if($attrCode){
			        $super_attribute= Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product',$attrCode);
			        $configurableAtt = Mage::getModel('catalog/product_type_configurable_attribute')->setProductAttribute($super_attribute);
			 		$configurable_attributes_data[] = array(
						'id'             => $configurableAtt->getId(),
						'label'          => $configurableAtt->getLabel(),
						'use_default'    => "0",
						'position'       => $super_attribute->getPosition(),
						'values'         => $configurableAtt->getPrices() ? $configProduct->getPrices() : array(),
						'attribute_id'   => $super_attribute->getId(),
						'attribute_code' => $super_attribute->getAttributeCode(),
						'frontend_label' => $super_attribute->getFrontend()->getLabel(),
						"store_label"	 => $super_attribute->getFrontend()->getLabel(),
			        );
			 		$i++;
			 	}
		    }
		    /**
	         * Initialize data for configurable product
	         */
	        if ($configurable_attributes_data) {
	            $magentoProductModel->setConfigurableAttributesData($configurable_attributes_data);
	        }
	        $affect_configurable_product_attributes = 1;
	        $magentoProductModel->setCanSaveConfigurableAttributes($affect_configurable_product_attributes);
		}
		$magentoProductModel->setCategoryIds($cats);
		$magentoProductModel->setStatus($status);
		$saved=$magentoProductModel->save();
		$lastId = $saved->getId();
		if($wholedata['type_id']=='downloadable'){
			if(!isset($wholedata['stock'])){
				$wholedata['stock'] = 10000; 
			}
		}
		if(!isset($wholedata['is_in_stock'])){
			$wholedata['is_in_stock'] = 0;
		}
		if(!isset($wholedata['stock'])){
			$wholedata['stock'] = 0;
		}
		$this->_saveStock($lastId,$wholedata['stock'],$wholedata['is_in_stock'],$wholedata['use_config_manage_stock']); 
		$wholedata['id'] = $lastId;
		Mage::dispatchEvent('mp_customoption_setdata', $wholedata);
		$vendorId = Mage::getSingleton('customer/session')->getCustomer()->getId();
		$seller_product_id = 0;
		$seller_products = Mage::getModel('marketplace/product')->getCollection()
							->addFieldToFilter('mageproductid',array('eq'=>$lastId))
							->addFieldToFilter('userid',array('eq'=>$vendorId));
		foreach ($seller_products as $seller_product) {
			$seller_product_id = $seller_product->getIndexId();
		}
		$collection1=Mage::getModel('marketplace/product')->load($seller_product_id);
		$collection1->setMageproductid($lastId);
		$collection1->setUserid($vendorId);
		$collection1->setStatus($status);
		$collection1->save();
		if(!is_dir(Mage::getBaseDir().'/media/marketplace/')){
			mkdir(Mage::getBaseDir().'/media/marketplace/', 0755);
		}
		if(!is_dir(Mage::getBaseDir().'/media/marketplace/'.$lastId.'/')){
			mkdir(Mage::getBaseDir().'/media/marketplace/'.$lastId.'/', 0755);
		}
		$target =Mage::getBaseDir().'/media/marketplace/'.$lastId.'/';
		if(isset($_FILES['images']['name'][0]) && $_FILES['images']['name'][0]){
			$allow_extension = explode(',',Mage::helper('marketplace')->storeImageExtension());
			$images_arr = $_FILES['images'];
			$i = 0;
			foreach($images_arr['name'] as $image){
				if($images_arr['tmp_name'][$i] != ''){
					$splitname = explode('.', $image);
					$splitname[0] = str_replace('-', '', $splitname[0]);
					$image_name = preg_replace('/[^A-Za-z0-9\-]/', '', $splitname[0]);
					$target1 = $target.$image_name.".".$splitname[1];
					$file_extension = pathinfo($image, PATHINFO_EXTENSION);
					if(in_array(strtolower($file_extension),$allow_extension)){
						move_uploaded_file($images_arr['tmp_name'][$i],$target1);
					}
				}
				$i++;
			}
		}
		if(isset($wholedata['defaultimage'])){
			if($wholedata['defaultimage']){
				$splitname = explode('.', $wholedata['defaultimage']);
				if(isset($splitname[1]) && $splitname[1]){
					$splitname[0] = str_replace('-', '', $splitname[0]);
					$image_name = preg_replace('/[^A-Za-z0-9\-]/', '', $splitname[0]);
					$wholedata['defaultimage'] = $image_name.".".$splitname[1]; 
				}
			}
		}else{
			$wholedata['defaultimage'] = '';
		}

		$this->_addImages($lastId,$wholedata['defaultimage']);

		if($wholedata['type_id']=='downloadable'){
			if(isset($_FILES) && count($_FILES) > 0)	{
				/*sort sample array*/
				if(isset($wholedata['samples'])){
					if(count($wholedata['samples'])){
						$sample_arr = array();
						foreach ($wholedata['samples'] as $value) {
							array_push($sample_arr,$value);
						}
						$wholedata['samples'] = $sample_arr;
					}
				}

				/*sort link array*/
				if(isset($wholedata['link'])){
					if(count($wholedata['link'])){
						$link_arr = array();
						foreach ($wholedata['link'] as $value) {
							array_push($link_arr,$value);
						}
						$wholedata['link'] = $link_arr;
					}
				}

				$this_sample_path = Mage::getBaseDir()."/media/marketplace/".$lastId."/sample/";
				$this_download_path = Mage::getBaseDir()."/media/marketplace/".$lastId."/download/";	
				$this_mainsample_path = Mage::getBaseDir()."/media/marketplace/".$lastId."/mainsample/";			
				
				if (!is_dir($this_sample_path))
					mkdir($this_sample_path, 0755);

				if (!is_dir($this_download_path))
					mkdir($this_download_path, 0755);
					
				if (!is_dir($this_mainsample_path))
					mkdir($this_mainsample_path, 0755);	

		    	$allow_sample_extension = explode(',',Mage::helper('marketplace')->storeSampleExtension());
				$allow_link_extension = explode(',',Mage::helper('marketplace')->storeLinkExtension());

				foreach($_FILES["wk_samples"]["tmp_name"]  as $key => $value){
					if(isset($wholedata['samples'][$key]['type']) && $wholedata['samples'][$key]['type']=='file'){
						if($_FILES['wk_samples']['tmp_name'][$key] != '' ){
							$file_name = $_FILES['wk_samples']['name'][$key];
							$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
							if(in_array(strtolower($file_extension),$allow_sample_extension)){
								move_uploaded_file($value,$this_mainsample_path.$file_name);
								$wholedata['samples'][$key]['file']=$this_mainsample_path.$file_name;
							}
						}
					}
				}

				foreach($_FILES["linksample"]["tmp_name"]  as $key => $value){
					if(isset($wholedata['link'][$key]['sample']['type']) && $wholedata['link'][$key]['sample']['type']=='file'){
						if($_FILES['linksample']['tmp_name'][$key] != '' ){
							$file_name = $_FILES['linksample']['name'][$key];
							$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
							if(in_array(strtolower($file_extension),$allow_sample_extension)){
								move_uploaded_file($value,$this_sample_path.$file_name);
								$wholedata['link'][$key]['sample']['file']=$this_sample_path.$file_name;
							}
						}
					}
				}

				foreach($_FILES["wk_link"]["tmp_name"]  as $key => $value){
					if(isset($wholedata['link'][$key]['type']) && $wholedata['link'][$key]['type']=='file'){
						if($_FILES['wk_link']['tmp_name'][$key] != '' ){
							$file_name = $_FILES['wk_link']['name'][$key];
							$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
							if(in_array(strtolower($file_extension),$allow_link_extension)){
								move_uploaded_file($value,$this_download_path.$file_name);
								$wholedata['link'][$key]['file']=$this_download_path.$file_name;
							}
						}
					}
				}
			}
			$this->AddImages($lastId,$storeId,$rates,$currentCurrencyCode,$wholedata);
		}
		Mage::dispatchEvent('mp_customattribute_settierpricedata', $wholedata);

		return $lastId;
    }

	/* save products */

	public function saveSimpleNewProduct($wholedata)
	{
		$storeId=Mage::app()->getStore()->getStoreId();
		
		$lastId = $this->saveProductData('',$wholedata);

		Mage::app()->setCurrentStore($storeId);

		return $lastId;		
	}

	public function saveDownloadableNewProduct($wholedata)
	{
		$storeId=Mage::app()->getStore()->getStoreId();
		
		$lastId = $this->saveProductData('',$wholedata);

		Mage::app()->setCurrentStore($storeId);

		return $lastId;		
	}
	public function saveVirtualNewProduct($wholedata)
	{
		$storeId=Mage::app()->getStore()->getStoreId();
		
		$lastId = $this->saveProductData('',$wholedata);

		Mage::app()->setCurrentStore($storeId);

		return $lastId;		
	}

	public function saveConfigNewProduct($wholedata)
	{
		$storeId=Mage::app()->getStore()->getStoreId();		

		$lastId = $this->saveProductData('',$wholedata);

		$process = Mage::getModel('index/process')->load(1);
		$process->reindexEverything();

		Mage::app()->setCurrentStore($storeId);

		return $lastId;		
	}
	
	public function quickcreate($wholedata)
	{
		$baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
		$currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
		$allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies(); 
		$rates = Mage::getModel('directory/currency')->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
		if(!$rates[$currentCurrencyCode]){
			$rates[$currentCurrencyCode] = 1;
		}
		$price = 0;
		$product = Mage::getModel('catalog/product')->load($wholedata['mainid']);
		$childIds = Mage::getModel('catalog/product_type_configurable')->getChildrenIds($wholedata['mainid']);
		foreach($childIds[0] as $val)
		{
		 	$data[$val] = array();
		}

		$configproducts = Mage::getModel('catalog/product') ->load($wholedata['mainid']);

		$wholedata['type_id'] = 'simple';
		$wholedata['category'] = $product->getCategoryIds();
		$wholedata['description'] = $product->getDescription();
		$wholedata['short_description'] = $product->getShortDescription();
		$wholedata['attribute_set_id'] = $configproducts->getAttributeSetId();
		$wholedata['tax_class_id'] = 0;
		$wholedata['stock'] = $wholedata['qty'];
		if($wholedata['qty']){
			$wholedata['is_in_stock'] = 1;
		}else{
			$wholedata['is_in_stock'] = 0;
		}
		
		$storeId=Mage::app()->getStore()->getStoreId();

		$lastId = $this->saveProductData('',$wholedata);

		$data[$lastId] =  array();
		$product->setConfigurableProductsData($data);
		$product->setCanSaveConfigurableAttributes(true);
		$product->save();
		Mage::getModel('catalog/product')->load($lastId)->setStatus($product->getStatus())->save();
		$configattr = Mage::getModel('catalog/product_type_configurable')->getConfigurableAttributesAsArray($configproducts);
		foreach ($wholedata as $key => $value) {
			if(strpos($key,'|') !== false){
				$supattr = explode('|', $key);
				for($i=0;$i<count($configattr);$i++) {

					if($supattr[2]==$configattr[$i]['id']){
						for ($j=0;$j<count($configattr[$i]['values']);$j++) {
							if($configattr[$i]['values'][$j]['value_index']==$supattr[3]){
								$price = $value/$rates[$currentCurrencyCode];
								$configattr[$i]['values'][$j]['pricing_value'] =  $price;
								$configattr[$i]['values'][$j]['can_edit_price'] = 1;
                            	$configattr[$i]['values'][$j]['can_read_price'] = 1;
							}
						}
					}
				}
			}
		}
		$configproducts->setConfigurableAttributesData($configattr);
		$configproducts->save();

		$process = Mage::getModel('index/process')->load(1);
		$process->reindexEverything();
		
		Mage::app()->setCurrentStore($storeId);

		return 0;
	}

	public function editassociate($wholedata)
	{
		$price = 0;

		$storeId=Mage::app()->getStore()->getStoreId();

		$baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
		$currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
		$allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies(); 
		$rates = Mage::getModel('directory/currency')->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
		if(!$rates[$currentCurrencyCode]){
			$rates[$currentCurrencyCode] = 1;
		}

		Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

		$product = Mage::getModel('catalog/product')->load($wholedata['mainid']);
		$configproducts = Mage::getModel('catalog/product') ->load($wholedata['mainid']);
		$configattr = Mage::getModel('catalog/product_type_configurable')->getConfigurableAttributesAsArray($configproducts);		
		
		for($i=0;$i<count($configattr);$i++) {			
			for ($j=0;$j<count($configattr[$i]['values']);$j++) {
				$updated_price_value = $wholedata['assopro'][$configattr[$i]['attribute_code']][$configattr[$i]['values'][$j]['value_index']]['price'];
				if($updated_price_value){
					$updated_price_value = $updated_price_value/$rates[$currentCurrencyCode];
					$configattr[$i]['values'][$j]['pricing_value'] =  $updated_price_value;
					$configattr[$i]['values'][$j]['can_edit_price'] = 1;
                	$configattr[$i]['values'][$j]['can_read_price'] = 1;
				}
			}
		}
		$configproducts->setConfigurableAttributesData($configattr);
		$configproducts->save();
		foreach ($wholedata['prod'] as $key => $value) {
			$qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($key);
			if($value){
				$is_in_stock = 1;
			}else{
				$is_in_stock = 0;
			}
			$qtyStock->setProductId($key)->setStockId(1);
			$qtyStock->setData('is_in_stock', $is_in_stock); 
			$savedStock = $qtyStock->save();
			$qtyStock->load($savedStock->getId())->setQty($value)->save();
			$qtyStock->setProductId($key)->setStockId(1);
			$qtyStock->setData('is_in_stock', $is_in_stock); 
			$savedStock = $qtyStock->save();
		}
		$app = Mage::app('admin');
		umask(0);	
		Mage::app()->setCurrentStore($storeId);	
		return 0;
	}

	public function saveassociate($wholedata)
	{
		$storeId=Mage::app()->getStore()->getStoreId();

		$baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
		$currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
		$allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies(); 
		$rates = Mage::getModel('directory/currency')->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
		if(!$rates[$currentCurrencyCode]){
			$rates[$currentCurrencyCode] = 1;
		}

		Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

		$product = Mage::getModel('catalog/product')->load($wholedata['mainid']);
		$data = array();
	    
	    foreach ($wholedata['asso_pro'] as $key => $value) {
	    	if($value=="on"){
	    		$data[$key] = array();
	    	}
	    }
	    $product->setConfigurableProductsData($data);
		$product->save();
		// $app = Mage::app('admin');
		// umask(0);
		Mage::app()->setCurrentStore($storeId);
		$process = Mage::getModel('index/process')->load(1);
		$process->reindexEverything();
	}

	/* end save*/
	public function saveBecomePartnerStatus($wholedata)
	{
		$partnerId=Mage::getSingleton('customer/session')->getCustomerId(); 
		$customer=Mage::getModel('customer/customer')->load($partnerId);
		$status=Mage::helper('marketplace')->getIsPartnerApproval()? 0:1;
		$assinstatus=Mage::helper('marketplace')->getIsPartnerApproval()? "Pending":"Seller";	
		$collection=Mage::getModel('marketplace/userprofile');
		$collection->setMageuserid($partnerId);
		$collection->setPartnerstatus($assinstatus);
		$collection->setWantpartner($status);
		$collection->setProfileurl($wholedata['profileurl']);
		$saved=$collection->save();
		$lastId=$saved->getAutoid();
		if($lastId){
			$email = Mage::getModel('admin/user')->load(1)->getEmail();
			$admin = Mage::getSingleton('admin/session');
			$headers = 'From:Admin' . "\r\n" .
			'Reply-To: ' .$customer->getemail(). "\r\n" .
			'X-Mailer: PHP/' . phpversion();
			$content = 'A New User '.$customer->getemail().' request to become a partner in your Store';
			mail($email,'User Request For Seller',$content,$headers);
		}
	}
	
	/*edit products*/
	public function editProduct($id,$wholedata)
	{
		$storeId=Mage::app()->getStore()->getStoreId();

		$lastId = $this->saveProductData($id,$wholedata);

		if(Mage::helper('marketplace')->getIsProductEditApproval()){
			$loadpro = Mage::getModel('catalog/product')->load($id);
			$sellerid = Mage::getSingleton('customer/session')->getCustomer()->getId();
			$ids = array();
			if($this->isConfigurable($loadpro->getTypeId())){
        		$conf_pro = Mage::getModel('catalog/product_type_configurable')->setProduct($loadpro);
			    $conf_pro_opt = $conf_pro->getUsedProductCollection()->addAttributeToSelect('*')
			    			->addFilterByRequiredOptions();
			    foreach($conf_pro_opt as $conf_pro_opt_data){
			        array_push($ids, $conf_pro_opt_data->getId());
			    } 
			}else{
				array_push($ids, $id);
			}
			$collection = Mage::getModel('marketplace/product')->getCollection()
								->addFieldToFilter('mageproductid',array('in'=>$ids))
								->addFieldToFilter('userid',array('eq'=>$sellerid));
			foreach ($collection as $row) {
				$loadpro = Mage::getModel('catalog/product')->load($row['mageproductid']);
				$catarray=$loadpro->getCategoryIds();
				$categoryname='';
				$catagory_model = Mage::getModel('catalog/category');
				foreach($catarray as $keycat){
				$categoriesy = $catagory_model->load($keycat);
					if($categoryname ==''){
						$categoryname=$categoriesy->getName();
					}else{
						$categoryname=$categoryname.",".$categoriesy->getName();
					}
				}
				$allStores = Mage::app()->getStores();
				foreach ($allStores as $_eachStoreId => $val)
				{
					Mage::getModel('catalog/product_status')->updateProductStatus($id,Mage::app()->getStore($_eachStoreId)->getId(), Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
				}
				$loadpro->setStatus(2)->save();
				$row->setStatus(2);
				$row->save();
			}
			$customer = Mage::getModel('customer/customer')->load($sellerid);
			$emailTemp = Mage::getModel('core/email_template')->loadDefault('approveproduct');
			$emailTempVariables = array();
			$adminname = Mage::helper('marketplace')->__('Admin');
			$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
			$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
			$emailTempVariables['myvar1'] = $loadpro['name'];
			$emailTempVariables['myvar2'] = $categoryname;
			$emailTempVariables['myvar3'] = $adminname;
			$emailTempVariables['myvar4'] = Mage::helper('marketplace')->__('I would like to inform you that recently i have updated a product Please approve it soon.');
			$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
			$emailTemp->setSenderName($customer->getName());
			$emailTemp->setSenderEmail($customer->getEmail());
			$emailTemp->send($adminEmail,$adminname,$emailTempVariables);
		}else{
			if(isset($wholedata['status']) && $wholedata['status']){
				Mage::getModel('catalog/product_status')->updateProductStatus($id,$storeId,$wholedata['status']);
			}
		}

		Mage::app()->setCurrentStore($storeId);

		return $lastId;
	}

	public function editDownloadableProduct($id,$wholedata)
	{	
		$wholedata['id'] = $id;
		Mage::dispatchEvent('mp_customattribute_deletetierpricedata', $wholedata);

		$storeId=Mage::app()->getStore()->getStoreId();

		$lastId = $this->saveProductData($id,$wholedata);

		if(Mage::helper('marketplace')->getIsProductEditApproval()){
			$sellerid = Mage::getSingleton('customer/session')->getCustomer()->getId();
			$collection = Mage::getModel('marketplace/product')->getCollection()
								->addFieldToFilter('mageproductid',array('eq'=>$id))
								->addFieldToFilter('userid',array('eq'=>$sellerid));
			foreach ($collection as $row) {
				$loadpro = Mage::getModel('catalog/product')->load($id);
				$catarray=$loadpro->getCategoryIds();
				$categoryname='';
				$catagory_model = Mage::getModel('catalog/category');
				foreach($catarray as $keycat){
				$categoriesy = $catagory_model->load($keycat);
					if($categoryname ==''){
						$categoryname=$categoriesy->getName();
					}else{
						$categoryname=$categoryname.",".$categoriesy->getName();
					}
				}
				$allStores = Mage::app()->getStores();
				foreach ($allStores as $_eachStoreId => $val)
				{
					Mage::getModel('catalog/product_status')->updateProductStatus($id,Mage::app()->getStore($_eachStoreId)->getId(), Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
				}
				$loadpro->setStatus(2)->save();
				$row->setStatus(2);
				$row->save();
			}
			$customer = Mage::getModel('customer/customer')->load($sellerid);
			$emailTemp = Mage::getModel('core/email_template')->loadDefault('approveproduct');
			$emailTempVariables = array();
			$adminname = Mage::helper('marketplace')->__('Admin');
			$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
			$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
			$emailTempVariables['myvar1'] = $loadpro['name'];
			$emailTempVariables['myvar2'] = $categoryname;
			$emailTempVariables['myvar3'] = $adminname;
			$emailTempVariables['myvar4'] = Mage::helper('marketplace')->__('I would like to inform you that recently i have updated a product Please approve it soon.');
			$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
			$emailTemp->setSenderName($customer->getName());
			$emailTemp->setSenderEmail($customer->getEmail());
			$emailTemp->send($adminEmail,$adminname,$emailTempVariables);
		}else{
			Mage::getModel('catalog/product_status')->updateProductStatus($id,$storeId,$wholedata['status']);
		}

		if(is_dir(Mage::getBaseDir().'/media/marketplace/'.$id.'/')){
			foreach(new DirectoryIterator(Mage::getBaseDir().'/media/marketplace/'.$id.'/') as $fileInfo){
				if($fileInfo->isFile()){unlink($fileInfo->getPathname());}
			}
		}

		Mage::app()->setCurrentStore($storeId);
		
		return $lastId;
	}

	public function editVirtualProduct($id,$wholedata)
	{	
		$wholedata['id'] = $id;
		Mage::dispatchEvent('mp_customattribute_deletetierpricedata', $wholedata);

		$storeId=Mage::app()->getStore()->getStoreId();

		$lastId = $this->saveProductData($id,$wholedata);

		if(Mage::helper('marketplace')->getIsProductEditApproval()){
			$sellerid = Mage::getSingleton('customer/session')->getCustomer()->getId();
			$collection = Mage::getModel('marketplace/product')->getCollection()
								->addFieldToFilter('mageproductid',array('eq'=>$id))
								->addFieldToFilter('userid',array('eq'=>$sellerid));
			foreach ($collection as $row) {
				$loadpro = Mage::getModel('catalog/product')->load($id);
				$catarray=$loadpro->getCategoryIds();
				$categoryname='';
				$catagory_model = Mage::getModel('catalog/category');
				foreach($catarray as $keycat){
					$categoriesy = $catagory_model->load($keycat);
					if($categoryname ==''){
						$categoryname=$categoriesy->getName();
					}else{
						$categoryname=$categoryname.",".$categoriesy->getName();
					}
				}
				$allStores = Mage::app()->getStores();
				foreach ($allStores as $_eachStoreId => $val)
				{
					Mage::getModel('catalog/product_status')->updateProductStatus($id,Mage::app()->getStore($_eachStoreId)->getId(), Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
				}
				$loadpro->setStatus(2)->save();
				$row->setStatus(2);
				$row->save();
			}
			$customer = Mage::getModel('customer/customer')->load($sellerid);
			$emailTemp = Mage::getModel('core/email_template')->loadDefault('approveproduct');
			$emailTempVariables = array();
			$adminname = Mage::helper('marketplace')->__('Admin');
			$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
			$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
			$emailTempVariables['myvar1'] = $loadpro['name'];
			$emailTempVariables['myvar2'] = $categoryname;
			$emailTempVariables['myvar3'] = $adminname;
			$emailTempVariables['myvar4'] = Mage::helper('marketplace')->__('I would like to inform you that recently i have updated a product Please approve it soon.');
			$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
			$emailTemp->setSenderName($customer->getName());
			$emailTemp->setSenderEmail($customer->getEmail());
			$emailTemp->send($adminEmail,$adminname,$emailTempVariables);
		}else{
			Mage::getModel('catalog/product_status')->updateProductStatus($id,$storeId,$wholedata['status']);
		}

	    Mage::app()->setCurrentStore($storeId);
		
		return $lastId;
	}
	
	/* end edit*/


	public function deleteProduct($wholedata)
	{
		$id = explode('/id/',$wholedata );
		$data['id']=$id[1];
		Mage::dispatchEvent('mp_delete_product', $data);
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$storeId=Mage::app()->getStore()->getStoreId();
		Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
	    $collection_product = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('mageproductid',array('eq'=>$id[1]))->addFieldToFilter('userid',array('eq'=>$customerid));
		if(count($collection_product)) {
			Mage::register("isSecureArea", 1);
			Mage::getModel('catalog/product')->load($id[1])->delete();
			$collection=Mage::getModel('marketplace/product')->getCollection()
							->addFieldToFilter('mageproductid',array('eq'=>$id[1]));
			foreach($collection as $row){
				$row->delete();
			}
			Mage::app()->setCurrentStore($storeId);
			return 0;
		}
		else
		{
		return 1;
		}
	}

	public function massdeleteProduct($id)
	{
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$storeId=Mage::app()->getStore()->getStoreId();
		Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
	    $collection_product = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('mageproductid',array('eq'=>$id))->addFieldToFilter('userid',array('eq'=>$customerid));
		if(count($collection_product)) {
			Mage::register("isSecureArea", 1);
			Mage::getModel('catalog/product')->load($id)->delete();
			$collection=Mage::getModel('marketplace/product')->getCollection()
							->addFieldToFilter('mageproductid',array('eq'=>$id));
			foreach($collection as $row){
				$row->delete();
			}
			Mage::app()->setCurrentStore($storeId);
			return 0;
		}
		else
		{
		return 1;
		}
	}
	
	private function _saveStock($lastId,$stock,$isstock,$use_config)
	{
		if($use_config){
			$magentoProductModel = Mage::getModel('catalog/product')->load($lastId);
			$magentoProductModel->setStockData(array(
		        'use_config_manage_stock' => $use_config,
		        'is_in_stock' => $isstock,
		        'is_salable' => 1,
		    ));
			$magentoProductModel->save();
			$stockStatus = Mage::getModel('cataloginventory/stock_status');
			$stockStatus->assignProduct($magentoProductModel);
			$stockStatus->saveProductStatus($lastId, 1);
		}else{
			$stockItem = Mage::getModel('cataloginventory/stock_item');
			$stockItem->loadByProduct($lastId);
			if(!$stockItem->getId()){$stockItem->setProductId($lastId)->setStockId(1);}
			$stockItem->setProductId($lastId)->setStockId(1);
			$stockItem->setData('is_in_stock', $isstock); 
			$savedStock = $stockItem->save();
			$stockItem->load($savedStock->getId())->setQty($stock)->save();
			$stockItem->setData('is_in_stock', $isstock); 
			$savedStock = $stockItem->save();
		}
	}

	/**
     * Add image to media gallery for marketplace product
     *
     * @param string   $product_id      Product Id 
     * @param string   $defaultimage    base image for product
     */
	private function _addImages($product_id,$defaultimage)
	{
		$mediDir = Mage::getBaseDir('media');
		$imagesdir = $mediDir . '/marketplace/' . $product_id . '/';
		if(!file_exists($imagesdir)){return false;}
		foreach (new DirectoryIterator($imagesdir) as $fileInfo){
    		if($fileInfo->isDot() || $fileInfo->isDir()) continue;
    		if($fileInfo->isFile()){
    			$file_extension = pathinfo($fileInfo->getPathname(), PATHINFO_EXTENSION);
				$allow_extension = array('png','gif','jpg','jpeg');
				if(in_array($file_extension,$allow_extension)){
					$fileinfo=explode('@',$fileInfo->getPathname());
					$objprod=Mage::getModel('catalog/product')->load($product_id);
					$objprod->addImageToMediaGallery($fileInfo->getPathname(), array ('image','small_image','thumbnail'), true, false);
					$objprod->save();					
				}
    		}
		}
		/**
	     * Retrive media gallery images
	     *
	     * @return Varien_Data_Collection
	     */
		$newimage = '';
		$_product = Mage::getModel('catalog/product')->load($product_id)->getMediaGalleryImages();
		if (strpos($defaultimage, '.') !== FALSE){
			$defimage =  explode('.',$defaultimage);
			$defimage[0] = str_replace('-', '_', $defimage[0]);
			foreach ($_product as $value) {
				$image = explode($defimage[0],$value->getFile());
				if (strpos($value->getFile(), $defimage[0]) !== FALSE){
					$newimage = $value->getFile();
				}
			}
		}else{
			foreach ($_product as $value) {
				if($value->getValueId()==$defaultimage){
					$newimage = $value->getFile();
				}
			}
		}
		if($newimage){
			$objprod=Mage::getModel('catalog/product')->load($product_id);
			$objprod->setSmallImage($newimage);
			$objprod->setImage($newimage);
			$objprod->setThumbnail($newimage);
			$objprod->save();
		}
	}

	private function AddImages($id,$storeId,$rates,$currentCurrencyCode,$wholedata)		
	{
		$mediDir = Mage::getBaseDir("media");

		$download_dir = Mage::getBaseDir()."/media/downloadable/files/";
		$link_samples_path = Mage::getBaseDir()."/media/downloadable/files/link_samples/";
		$links_path = Mage::getBaseDir()."/media/downloadable/files/links/";	
		$samples_path = Mage::getBaseDir()."/media/downloadable/files/samples/";	
		if (!is_dir($download_dir))
			mkdir($download_dir, 0755);

		if (!is_dir($link_samples_path))
			mkdir($link_samples_path, 0755);

		if (!is_dir($links_path))
			mkdir($links_path, 0755);
			
		if (!is_dir($samples_path))
			mkdir($samples_path, 0755);	

		$sampledir = $mediDir."/marketplace/".$id."/sample/";
		$downloaddir = $mediDir."/marketplace/".$id."/download/";
		$mainsampledir = $mediDir."/marketplace/".$id."/mainsample/";

		$savelinkserver = Mage::getStoreConfig('marketplace/marketplace_options/savelinkserver',$storeId);

		foreach($wholedata['samples'] as $key=>$samples)	{
			$sampleModel = Mage::getModel('downloadable/sample')->load($samples['wk_sample_id']);
			$web_id=Mage::app()->getStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)->getWebsiteId().",".$storeId;
			$sampleModel->setWebsiteIds(array($web_id));
			$sampleModel->setTitle($samples['title']);
			if($samples['type']=='file')	{
				if (file_exists($samples['file'])) {
					$newfilename=time().$_FILES["wk_samples"]["name"][$key];
					Mage::dispatchEvent('wk_mp_downloadable_sample_add',array(
						'filename' 	=> $newfilename,
						'filepath' 	=> $samples['file'],
						'linkmodel' => $sampleModel,
						)
					);
					if($savelinkserver != 2){
						$newfile1=$samples_path.$newfilename;
						copy($samples['file'], $newfile1);											
						$sampleModel->setSampleFile("/".$newfilename);
						$sampleModel->setSampleType("file");
						unlink($samples['file']);
					}					
				}
			}else{
				if (isset($samples['url']) && $samples['url']) {							
					$sampleModel->setSampleUrl($samples['url']);
					$sampleModel->setSampleType("url");
				}
			}			
			$sampleModel->setProductId($id);
			$sampleModel->setStoreId($storeId);			
			$sampleModel->save();
		}
		foreach($wholedata['link'] as $key=>$links)	{
			if(!isset($links['price'])){
				$links['price'] = 0;
			}
			$links['price'] = $links['price']/$rates[$currentCurrencyCode];
			$linkModel = Mage::getModel('downloadable/link')->load($links['wk_link_id']);
			$web_id=Mage::app()->getStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)->getWebsiteId().",".$storeId;
			$linkModel->setWebsiteIds(array($web_id));
			$linkModel->setPrice($links['price']);
			$linkModel->setTitle($links['title']);
			if(isset($links['is_unlimited']) && $links['is_unlimited']!=1)
				$linkModel->setNumberOfDownloads($links['number_of_downloads']);
			else
				$linkModel->setNumberOfDownloads(0);
			if(isset($links['type']) && $links['type']=='file')	{
				if (file_exists($links['file'])) {
					$newfilename=time().$_FILES["wk_link"]["name"][$key];
					Mage::dispatchEvent('wk_mp_downloadable_link_add',array(
						'filename' 	=> $newfilename,
						'filepath' 	=> $links['file'],
						'linkmodel' => $linkModel,
						)
					);
					if($savelinkserver != 2){
						$newfile1=$links_path.$newfilename;
						copy($links['file'], $newfile1);											
						$linkModel->setLinkFile("/".$newfilename);
						$linkModel->setLinkType("file");
					}
					unlink($links['file']);
				}
			}else{
				if (isset($links['url']) && $links['url']) {							
					$linkModel->setLinkUrl($links['url']);
					$linkModel->setLinkType("url");
				}
			}
			if(isset($links['sample']['type']) && $links['sample']['type']=='file'){
				if (file_exists($links['sample']['file'])) {
					$newfilename=time().$_FILES["linksample"]["name"][$key];
					Mage::dispatchEvent('wk_mp_downloadable_samplelink_add',array(
						'filename' 	=> $newfilename,
						'filepath' 	=> $links['sample']['file'],
						'linkmodel' => $linkModel,
						)
					);
					if($savelinkserver != 2){
						$newfile1=$link_samples_path.$newfilename;
						copy($links['sample']['file'], $newfile1);											
						$linkModel->setSampleFile("/".$newfilename);
						$linkModel->setSampleType("file");
					}
					unlink($links['sample']['file']);
				}
			}else{
				if (isset($links['sample']['url']) && $links['sample']['url']) {	
					$linkModel->setSampleUrl($links['sample']['url']);
					$linkModel->setSampleType("url");
				}
			}
			$linkModel->setProductId($id);
			$linkModel->setStoreId($storeId);
			$linkModel->save();
		}
	}
	
	public function approveSimpleProduct($id)
	{
		$magentoProductModel = Mage::getModel('catalog/product')->load($id);
		$catarray=$magentoProductModel->getCategoryIds();
		$categoryname='';
		$catagory_model = Mage::getModel('catalog/category');
		foreach($catarray as $keycat){
		$categoriesy = $catagory_model->load($keycat);
			if($categoryname ==''){
				$categoryname=$categoriesy->getName();
			}else{
				$categoryname=$categoryname.",".$categoriesy->getName();
			}
		}
		$users = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('mageproductid',array('eq'=>$id));
		foreach ($users as $value) {
			$user = $value;
		}
		$allStores = Mage::app()->getStores();
		foreach ($allStores as $_eachStoreId => $val)
		{
			Mage::getModel('catalog/product_status')->updateProductStatus($id,Mage::app()->getStore($_eachStoreId)->getId(), Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
		}
		
		$magentoProductModel->setStatus(1);
		$saved=$magentoProductModel->save();
		$lastId = $saved->getId();
		$pro=Mage::getModel('marketplace/product')->load($user->getIndexId());
		$pro->setStatus(1);
		$pro->save();	
		$adminname=Mage::helper('marketplace')->__('Admin');
		$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
		$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
		$seller = Mage::getModel('customer/customer')->load($user->getUserid());
		$emailTemp = Mage::getModel('core/email_template')->loadDefault('whenproductapproved');
		$emailTempVariables = array();
		$emailTempVariables['myvar1'] = $magentoProductModel->getName();
		$emailTempVariables['myvar2'] =$magentoProductModel->getDescription();
		$emailTempVariables['myvar3'] =$magentoProductModel->getPrice();
		$emailTempVariables['myvar4'] =$categoryname;
		$emailTempVariables['myvar5'] =$seller->getname();
		$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
		$emailTemp->setSenderName($adminname);
		$emailTemp->setSenderEmail($adminEmail); 
		$emailTemp->send($seller->getemail(),$seller->getname(),$emailTempVariables);
		//For product approval
		Mage::dispatchEvent('mp_approve_product',array('product'=>$pro,'seller'=>$seller));
		return $lastId;
	}
	
	public function isCustomerProduct($magentoProductId)
	{
		$collection = Mage::getModel('marketplace/product')->getCollection();
		$collection->addFieldToFilter('mageproductid',array($magentoProductId));
		$userid='';
		$status='';
		foreach($collection as $record){
		$userid=$record->getuserid();
		}
		$collection1 = Mage::getModel('marketplace/userprofile')->getCollection()->addFieldToFilter('mageuserid',array('eq'=>$userid));
		foreach($collection1 as $record1){
		$status=$record1->getWantpartner();
		}
		if($status!=1){
			$userid='';
		}
		
		return array('productid'=>$magentoProductId,'userid'=>$userid);
	}	
}
