<?php

require_once 'Mage/Customer/controllers/AccountController.php';
class Webkul_Marketplace_MarketplaceaccountController extends Mage_Customer_AccountController
{	
    public function indexAction()
    {		
		$this->loadLayout();     
		$this->renderLayout();
    }
	
	private function portfolioImageCount() 
	{
		$userid = Mage::getSingleton('customer/session')->getCustomer()->getId();
		$marketplace_collection_product = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('userid',array('eq'=>$userid));
		$collection_product = array();
		foreach($marketplace_collection_product as $key) {
			$mageproductid = $key->getData("mageproductid");
			$product = Mage::getModel('catalog/product')->load($mageproductid);
			if($product->getData('is_portfolio') == true)
			$collection_product [] = $product;
		}
		return count($collection_product);
	}
    
    public function newAction()
    { 
    	$userid = Mage::getSingleton('customer/session')->getCustomer()->getId();
		$subscription = Mage::getModel('customer/customer')->stripeApi($userid); // check user user subscription form stripe api
		if($subscription['code'] == 503) {
			if($this->portfolioImageCount() >= 12) {
				$this->_redirect('marketplace/marketplaceaccount/myproductslist/');
			}
		}
		try{
			$this->loadLayout(array('default','marketplace_account_simpleproduct'));
			$set=$this->getRequest()->getParam('set');
			$type=$this->getRequest()->getParam('type');
			if(isset($set) && isset($type)){
				$allowedsets=explode(',',Mage::helper('marketplace')->getAllowedAttributesetIds());
				$allowedtypes=explode(',',Mage::helper('marketplace')->getAllowedProductType());
				if(!in_array($type,$allowedtypes) || !in_array($set,$allowedsets)){
					Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('Product Type Invalid Or Not Allowed'));
				    $this->_redirect('marketplace/marketplaceaccount/new/');
				}
				Mage::getSingleton('core/session')->setAttributeSet($set);
				switch($type){
					case "simple":
						$this->loadLayout(array('default','marketplace_account_simpleproduct'));
						$this->getLayout()->getBlock('head')->setTitle(Mage::helper('marketplace')->__('MarketPlace Product Type: Simple Product'));
						break;
					case "downloadable":
						$this->loadLayout(array('default','marketplace_account_downloadableproduct'));
						$this->getLayout()->getBlock('head')->setTitle(Mage::helper('marketplace')->__('MarketPlace Product Type: Downloabable Product'));
						break;
					case "virtual":
						$this->loadLayout(array('default','marketplace_account_virtualproduct'));
						$this->getLayout()->getBlock('head')->setTitle(Mage::helper('marketplace')->__('MarketPlace Product Type: Virtual Product'));
						break;
					case "configurable":
						$this->loadLayout(array('default','marketplace_account_configurableproduct'));
						$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('MarketPlace Product Type: Configurable Product'));
						break;
				}
				Mage::dispatchEvent('mp_bundalproduct',array('layout'=>$this,'type'=>$type));
				Mage::dispatchEvent('mp_groupedproduct',array('layout'=>$this,'type'=>$type));
				
				$this->_initLayoutMessages('catalog/session');
				$this->renderLayout();
			}else{
			  //$this->loadLayout(array('default','marketplace_marketplaceaccount_newproduct'));     
			  $this->renderLayout();
			}
		}catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__($e->getMessage()));
            $this->loadLayout(array('default','marketplace_marketplaceaccount_newproduct'));     
		    $this->renderLayout();
        }
	}

    public function categorytreeAction()
    {
    	try{
			$data = $this->getRequest()->getParams();
			$category_model = Mage::getModel("catalog/category");
			$category = $category_model->load($data["CID"]);
			$children = $category->getChildren();
			$all = explode(",",$children);$result_tree = "";$ml = $data["ML"]+20;$count = 1;$total = count($all);
			$plus = 0;
			
			foreach($all as $each){
				$count++;
				$_category = $category_model->load($each);
				if(count($category_model->getResource()->getAllChildren($category_model->load($each)))-1 > 0){
					$result[$plus]['counting']=1;  			
				}else{
					$result[$plus]['counting']=0;
				}
				$result[$plus]['id']= $_category['entity_id'];
				$result[$plus]['name']= $_category->getName();
				$categories = array();
				$data_cats = '';
				if(isset($data["CATS"])){
					$categories = explode(",",$data["CATS"]);
					$data_cats = $data["CATS"];
				}
				if($data_cats && in_array($_category["entity_id"],$categories)){
					$result[$plus]['check']= 1;
				}else{
					$result[$plus]['check']= 0;
				}
				$plus++;
			}
			$this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody(json_encode($result));
		}catch (Exception $e) {
            $this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody('');
        }
	}
	
	/**
     * Add Seller's Simple Type product
     *
     */
	public function simpleproductAction()
	{
		if($this->getRequest()->isPost()){
			try {
				if (!$this->_validateFormKey()) {
	             	$this->_redirect('marketplace/marketplaceaccount/new/');
	            }
				$validated_obj = $this->validate();
				$validated_arr = $validated_obj->getData();
				if($validated_arr['error']){
					Mage::getSingleton('core/session')->addError($validated_arr['message']);
	             	$this->_redirect('marketplace/marketplaceaccount/new/');
					return false;
				}
				list($data, $errors) = $this->validatePost();
				$wholedata=$this->getRequest()->getParams();
				if(empty($errors)){		
					Mage::getModel('marketplace/product')->saveSimpleNewProduct($wholedata);
					
					if($_REQUEST['is_portfolio'] == 1) {
						// for alert director manage submission on product update
						$userid = Mage::getSingleton('customer/session')->getCustomer()->getId();
						$resource = Mage::getSingleton('core/resource');
						$writeAdapter = $resource->getConnection('core_write');
						$query = "UPDATE `submitted_portfolio` SET `updated_portfolio` = 1, updated_portfolio_counter = updated_portfolio_counter + 1  WHERE `submitted_portfolio`.`submitted_user_id` = $userid;";
						$writeAdapter->query($query);
					}
					$status=Mage::helper('marketplace')->getIsProductApproval();
					if($status==1){
						$vendorId = Mage::getSingleton('customer/session')->getCustomer()->getId();
						$customer = Mage::getModel('customer/customer')->load($vendorId);
						$cfname=$customer->getFirstname()." ".$customer->getLastname();
						$cmail=$customer->getEmail();
						$catagory_model = Mage::getModel('catalog/category');
						$categoriesy = $catagory_model->load($wholedata['category'][0]);
						$categoryname=$categoriesy->getName();
						$emailTemp = Mage::getModel('core/email_template')->loadDefault('approveproduct');
						$emailTempVariables = array();
						$adminname = Mage::helper('marketplace')->__('Admin');
						$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
						$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
						$emailTempVariables['myvar1'] = $wholedata['name'];
						$emailTempVariables['myvar2'] =$categoryname;
						$emailTempVariables['myvar3'] = $adminname;
						$emailTempVariables['myvar4'] = Mage::helper('marketplace')->__('I would like to inform you that recently I have added a new product in the store.');
						$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
						$emailTemp->setSenderName($cfname);
						$emailTemp->setSenderEmail($cmail);
						$emailTemp->send($adminEmail,$adminname,$emailTempVariables);
					}
				}else{
					foreach ($errors as $message) {
						Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__($message));
					}
				}
				if (empty($errors)){
					// Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Your portfolio has been successfully saved'));
				}
			} catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__($e->getMessage()));
	        }
		}
		$this->_redirect('marketplace/marketplaceaccount/myproductslist/');
	}

	/**
     * Validate product
     *
     * @return $response
     */
    public function validate()
    {
        $response = new Varien_Object();
        $response->setError(false);

        try {
            $productData = $this->getRequest()->getPost(); 

            $productData['stock_data']['use_config_manage_stock'] = 0;
            /* @var $product Mage_Catalog_Model_Product */
            $product = Mage::getModel('catalog/product');            
            if ($storeId = Mage::app()->getStore()->getStoreId()) {
                $product->setStoreId($storeId);
            }
            if ($setId = $this->getRequest()->getParam('attribute_set_id')) {
                $product->setAttributeSetId($setId);
            }
            if ($typeId = $this->getRequest()->getParam('type_id')) {
                $product->setTypeId($typeId);
            }

            if ($productId = $this->getRequest()->getParam('productid')) {
            	$product->setData('_edit_mode', true);
                $product->load($productId);
            }

            $dateFields = array();
            $attributes = $product->getAttributes();
            foreach ($attributes as $attrKey => $attribute) {
                if ($attribute->getBackend()->getType() == 'datetime') {
                    if (array_key_exists($attrKey, $productData) && $productData[$attrKey] != ''){
                        $dateFields[] = $attrKey;
                    }
                }
            }
            $productData = $this->_filterDates($productData, $dateFields);
            $product->addData($productData);

            /* set restrictions for date ranges */
            $resource = $product->getResource();
            $resource->getAttribute('special_from_date')
                ->setMaxValue($product->getSpecialToDate());
            $resource->getAttribute('news_from_date')
                ->setMaxValue($product->getNewsToDate());
            $resource->getAttribute('custom_design_from')
                ->setMaxValue($product->getCustomDesignTo());

            $product->validate();
        }
        catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $response->setError(true);
            $response->setAttribute($e->getAttributeCode());
            $response->setMessage($e->getMessage());
        } catch (Mage_Core_Exception $e) {
            $response->setError(true);
            $response->setMessage($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError(Mage::helper('marketplace')->__($e->getMessage()));
            $this->_initLayoutMessages('core/session');
            $response->setError(true);
            $response->setMessage($e->getMessage());
        }
        return $response;
    }

    /**
     * Add Seller's Simple Type product
     *
     */
	public function virtualproductAction() 
	{
		if($this->getRequest()->isPost()){
			try {
				if (!$this->_validateFormKey()) {
	             	$this->_redirect('marketplace/marketplaceaccount/new/');
	            }
				$validated_obj = $this->validate();
				$validated_arr = $validated_obj->getData();
				if($validated_arr['error']){
					Mage::getSingleton('core/session')->addError($validated_arr['message']);
	             	$this->_redirect('marketplace/marketplaceaccount/new/');
					return false;
				}
				list($data, $errors) = $this->validatePost();
				$wholedata=$this->getRequest()->getParams();
				if(empty($errors)){		
					Mage::getModel('marketplace/product')->saveVirtualNewProduct($wholedata);
					$status=Mage::helper('marketplace')->getIsProductApproval();
					if($status==1){
						$vendorId = Mage::getSingleton('customer/session')->getCustomer()->getId();
					    $customer = Mage::getModel('customer/customer')->load($vendorId);
						$cfname=$customer->getFirstname()." ".$customer->getLastname();
						$cmail=$customer->getEmail();
						$catagory_model = Mage::getModel('catalog/category');
						$categoriesy = $catagory_model->load($wholedata['category'][0]);
						$categoryname=$categoriesy->getName();
						$emailTemp = Mage::getModel('core/email_template')->loadDefault('approveproduct');
						$emailTempVariables = array();
						$adminname = Mage::helper('marketplace')->__('Admin');
						$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
						$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
						$emailTempVariables['myvar1'] = $wholedata['name'];
						$emailTempVariables['myvar2'] =$categoryname;
						$emailTempVariables['myvar3'] = $adminname;
						$emailTempVariables['myvar4'] = Mage::helper('marketplace')->__('I would like to inform you that recently i have added a new product in the store.');
						$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
						$emailTemp->setSenderName($cfname);
						$emailTemp->setSenderEmail($cmail);
						$emailTemp->send($adminEmail,$adminname,$emailTempVariables);
					}
				}else{
					foreach ($errors as $message) {$this->_getSession()->addError($message);}
					
				}
				if (empty($errors)){
					Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Your portfolio has been successfully saved'));
				}
			} catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	        }
		}
		$this->_redirect('marketplace/marketplaceaccount/new/');
	}

	public function downloadableproductAction() 
	{
		if($this->getRequest()->isPost()){ 
			try {
				if (!$this->_validateFormKey()) {
	             	$this->_redirect('marketplace/marketplaceaccount/new/');
	            }
				$validated_obj = $this->validate();
				$validated_arr = $validated_obj->getData();
				if($validated_arr['error']){
					Mage::getSingleton('core/session')->addError($validated_arr['message']);
	             	$this->_redirect('marketplace/marketplaceaccount/new/');
					return false;
				}
				list($data, $errors) = $this->validatePost();
				$wholedata=$this->getRequest()->getParams();
				if(empty($errors)){		
					Mage::getModel('marketplace/product')->saveDownloadableNewProduct($wholedata);
					$status=Mage::helper('marketplace')->getIsProductApproval();
					if($status==1){
						$vendorId = Mage::getSingleton('customer/session')->getCustomer()->getId();
					    $customer = Mage::getModel('customer/customer')->load($vendorId);
						$cfname=$customer->getFirstname()." ".$customer->getLastname();
						$cmail=$customer->getEmail();
						$catagory_model = Mage::getModel('catalog/category');
						$categoriesy = $catagory_model->load($wholedata['category'][0]);
						$categoryname=$categoriesy->getName();
						$emailTemp = Mage::getModel('core/email_template')->loadDefault('approveproduct');
						$emailTempVariables = array();
						$adminname = Mage::helper('marketplace')->__('Admin');
						$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
						$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
						$emailTempVariables['myvar1'] = $wholedata['name'];
						$emailTempVariables['myvar2'] =$categoryname;
						$emailTempVariables['myvar3'] = $adminname;
						$emailTempVariables['myvar4'] = Mage::helper('marketplace')->__('I would like to inform you that recently i have added a new product in the store.');
						$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
						$emailTemp->setSenderName($cfname);
						$emailTemp->setSenderEmail($cmail);
						$emailTemp->send($adminEmail,$adminname,$emailTempVariables);
					}
				}else{
					foreach ($errors as $message) {$this->_getSession()->addError($message);}
					
				}
				if (empty($errors)){
					Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Your portfolio has been successfully saved'));
				}
			} catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	        }
		}
		$this->_redirect('marketplace/marketplaceaccount/new/');
	}

	public function configurableproductAction() 
	{
		$this->_redirect('marketplace/marketplaceaccount/addconfigurableproduct');
	}
	
	public function addconfigurableproductAction()
	{
		return $this->_redirect('marketplace/marketplaceaccount/new/');
	}

	public function newattributeAction()
	{
		$this->loadLayout( array('default','marketplace_account_newattribute'));
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
		$this->getLayout()->getBlock('head')->setTitle(Mage::helper('marketplace')->__('Manage Attribute'));
    	$this->renderLayout();
	}
	
	public function createattributeAction() 
	{
		try{
			if($this->getRequest()->isPost()){
				if (!$this->_validateFormKey()) {
	              	return $this->_redirect('marketplace/marketplaceaccount/newattribute/');
	         	}
				
				$wholedata=$this->getRequest()->getParams();
				$attributes = Mage::getModel('catalog/product')->getAttributes();

			    foreach($attributes as $a){
			            $allattrcodes = $a->getEntityType()->getAttributeCodes();
			    }
			    if(in_array($wholedata['attribute_code'], $allattrcodes)){
			    	Mage::getSingleton('core/session')->addError(Mage::helper('marketplace')->__('Attribute Code already exists'));
					$this->_redirect('marketplace/marketplaceaccount/newattribute/');
			    }else{
					list($data, $errors) = $this->validatePost();
					if(array_key_exists('attroptions', $wholedata)){
						foreach( $wholedata['attroptions'] as $c){
							$data1['.'.$c['admin'].'.'] = array($c['admin'],$c['store']);	
						}
					}else{
						$data1=array();
					}
					
					$_attribute_data = array(
										'attribute_code' => $wholedata['attribute_code'],
										'is_global' => '1',
										'frontend_input' => $wholedata['frontend_input'],
										'default_value_text' => '',
										'default_value_yesno' => '0',
										'default_value_date' => '',
										'default_value_textarea' => '',
										'is_unique' => '0',
										'is_required' => '0',
										'apply_to' => '0',
										'is_configurable' => '1',
										'is_searchable' => '0',
										'is_visible_in_advanced_search' => '1',
										'is_comparable' => '0',
										'is_used_for_price_rules' => '0',
										'is_wysiwyg_enabled' => '0',
										'is_html_allowed_on_front' => '1',
										'is_visible_on_front' => '0',
										'used_in_product_listing' => '0',
										'used_for_sort_by' => '0',
										'frontend_label' => $wholedata['attribute_label']
									);
					$model = Mage::getModel('catalog/resource_eav_attribute');
					if (!isset($_attribute_data['is_configurable'])) {
						$_attribute_data['is_configurable'] = 0;
					}
					if (!isset($_attribute_data['is_filterable'])) {
						$_attribute_data['is_filterable'] = 0;
					}
					if (!isset($_attribute_data['is_filterable_in_search'])) {
						$_attribute_data['is_filterable_in_search'] = 0;
					}
					if (is_null($model->getIsUserDefined()) || $model->getIsUserDefined() != 0) {
						$_attribute_data['backend_type'] = $model->getBackendTypeByInput($_attribute_data['frontend_input']);
					}
					$defaultValueField = $model->getDefaultValueByInput($_attribute_data['frontend_input']);
					if ($defaultValueField) {
						$_attribute_data['default_value'] = $this->getRequest()->getParam($defaultValueField);
					}
					$model->addData($_attribute_data);
					$data['option']['value'] = $data1;
					if($wholedata['frontend_input'] == 'select' || $wholedata['frontend_input'] == 'multiselect')
						$model->addData($data);
					$model->setAttributeSetId($wholedata['attribute_set_id']);
					$model->setAttributeGroupId($wholedata['AttributeGroupId']);
					$entityTypeID = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
					$model->setEntityTypeId($entityTypeID);
					$model->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
					$model->setIsUserDefined(1);
					$model->save();
					Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Attribute Created Successfully'));
					$this->_redirect('marketplace/marketplaceaccount/newattribute/');
				}
			}
		} catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->_redirect('marketplace/marketplaceaccount/newattribute/');
        }
	}

	public function quickcreateAction() 
	{
		$wholedata=$this->getRequest()->getParams();
		$id = $wholedata['mainid'];
        try {
			if (!$this->_validateFormKey()) {
             	$this->_redirect('marketplace/marketplaceaccount/myproductslist/');
            }
			$validated_obj = $this->validate();
			$validated_arr = $validated_obj->getData();
			if($validated_arr['error']){
				Mage::getSingleton('core/session')->addError($validated_arr['message']);
             	$this->_redirect('marketplace/marketplaceaccount/new/');
				return false;
			}			
		    Mage::getModel('marketplace/product')->quickcreate($wholedata);			
			Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Associate Product created Successfully'));
		} catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
        }
		$this->_redirect('marketplace/marketplaceaccount/configurableassociate',array('id'=>$id));
	}

	public function assignassociateAction() 
	{
		$wholedata=$this->getRequest()->getParams();
		$id = $wholedata['mainid'];
		try{
		    Mage::getModel('marketplace/product')->editassociate($wholedata);
		    Mage::getModel('marketplace/product')->saveassociate($wholedata);
		    
			Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Product has been assigned successfully'));
		} catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
        }
		$this->_redirect('marketplace/marketplaceaccount/configurableassociate',array('id'=>$id));
	}

	public function configproductAction() 
	{
		if($this->getRequest()->isPost()){
			try {
				if (!$this->_validateFormKey()) {
	             	$this->_redirect('marketplace/marketplaceaccount/new/');
					return false;
	            }
				$validated_obj = $this->validate();
				$validated_arr = $validated_obj->getData();
				if($validated_arr['error']){
					Mage::getSingleton('core/session')->addError($validated_arr['message']);
	             	$this->_redirect('marketplace/marketplaceaccount/new/');
					return false;
				}
			
				list($data, $errors) = $this->validatePost();
				$wholedata=$this->getRequest()->getParams();
				if(empty($errors)){	
					$id =  Mage::getModel('marketplace/product')->saveConfigNewProduct($wholedata);
					$status=Mage::helper('marketplace')->getIsProductApproval();
					if($status==1){
						$vendorId = Mage::getSingleton('customer/session')->getCustomer()->getId();
						$customer = Mage::getModel('customer/customer')->load($vendorId);
						$cfname=$customer->getFirstname()." ".$customer->getLastname();
						$cmail=$customer->getEmail();
						$catagory_model = Mage::getModel('catalog/category');
						$categoriesy = $catagory_model->load($wholedata['category'][0]);
						$categoryname=$categoriesy->getName();
						$emailTemp = Mage::getModel('core/email_template')->loadDefault('approveproduct');
						$emailTempVariables = array();
						$adminname = Mage::helper('marketplace')->__('Admin');
						$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
						$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
						$emailTempVariables['myvar1'] = $wholedata['name'];
						$emailTempVariables['myvar2'] =$categoryname;
						$emailTempVariables['myvar3'] =$adminname;
						$emailTempVariables['myvar4'] = Mage::helper('marketplace')->__('I would like to inform you that recently i have added a new product in the store.');
						$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
						$emailTemp->setSenderName($cfname);
						$emailTemp->setSenderEmail($cmail);
						$emailTemp->send($adminEmail,$adminname,$emailTempVariables);
					}
					Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Product has been Created Successfully'));
					$this->_redirect('marketplace/marketplaceaccount/editapprovedconfigurable',array('id'=>$id));
					return false;
				}else{
					foreach ($errors as $message) {$this->_getSession()->addError($message);}
					
				}
			}catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	        }
			$this->_redirect('marketplace/marketplaceaccount/new/');
		}
		else{
			$this->_redirect('marketplace/marketplaceaccount/new/');
		}
	}
	
	public function configurableassociateAction()
	{
		$this->loadLayout( array('default','marketplace_account_configurableassociate'));
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
		$this->getLayout()->getBlock('head')->setTitle($this->__('Add Associated Product'));
    	$this->renderLayout();
	}
	
	public function myproductslistAction()
	{
		$this->loadLayout( array('default','marketplace_account_productlist'));
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
		$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('Portfolio'));
    	$this->renderLayout();
	}

	public function becomepartnerAction()
	{
		try{
			if($this->getRequest()->isPost()){ 
				if (!$this->_validateFormKey()) {
	              	return $this->_redirect('marketplace/marketplaceaccount/becomepartner/');
	            }
				
				$wholedata=$this->getRequest()->getParams();
				Mage::getModel('marketplace/product')->saveBecomePartnerStatus($wholedata);
				Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Your request to become seller was successfully send to admin'));
				$this->_redirect('marketplace/marketplaceaccount/becomepartner/');
			}
			else{
				$this->loadLayout( array('default','marketplace_account_becomepartner'));
				$this->_initLayoutMessages('customer/session');
				$this->_initLayoutMessages('catalog/session');
				$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('Seller Request Panel'));
				$this->renderLayout();
			}
		} catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->_redirect('marketplace/marketplaceaccount/becomepartner/');
        }
	}
	
	public function editapprovedsimpleAction() 
	{
		if($this->getRequest()->isPost()){
			$id= $this->getRequest()->getParam('productid');
			try {
				if (!$this->_validateFormKey()) {
	             	$this->_redirect('marketplace/marketplaceaccount/editapprovedsimple',array('id'=>$id));
					return false;
	            }
				$validated_obj = $this->validate();
				$validated_arr = $validated_obj->getData();
				if($validated_arr['error']){
					Mage::getSingleton('core/session')->addError($validated_arr['message']);
	             	$this->_redirect('marketplace/marketplaceaccount/editapprovedsimple',array('id'=>$id));
	             	return false;
				}	

				list($data, $errors) = $this->validatePost();				
				$customerid=Mage::getSingleton('customer/session')->getCustomerId();
				$collection_product = Mage::getModel('marketplace/product')->getCollection()
										->addFieldToFilter('mageproductid',array('eq'=>$id))
										->addFieldToFilter('userid',array('eq'=>$customerid));				
	            if(count($collection_product))
	            {
					if(empty($errors)){
						Mage::getModel('marketplace/product')->editProduct($id,$this->getRequest()->getParams());
						if($_REQUEST['is_portfolio'] == 0) {
							$r_poduct = Mage::getModel('catalog/product')->load($id);
							$r_poduct->setData('is_portfolio', 0);
							$r_poduct->getResource()->saveAttribute($r_poduct, 'is_portfolio');
						} elseif($_REQUEST['is_portfolio'] == 1) {
							// for alert director manage submission on product update
							$userid = Mage::getSingleton('customer/session')->getCustomer()->getId();
							$resource = Mage::getSingleton('core/resource');
							$writeAdapter = $resource->getConnection('core_write');
							$query = "UPDATE `submitted_portfolio` SET `updated_portfolio` = 1, updated_portfolio_counter = updated_portfolio_counter + 1  WHERE `submitted_portfolio`.`submitted_user_id` = $userid;";
							$writeAdapter->query($query);
						}
						Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Saved!'));
						$this->_redirect('marketplace/marketplaceaccount/myproductslist/');
					}else{
						foreach ($errors as $message) {Mage::getSingleton('core/session')->addError($message);}						
					}
			    }
			}catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	        }
			$this->_redirect('marketplace/marketplaceaccount/editapprovedsimple',array('id'=>$id));
		}
		else{
			$urlid=$this->getRequest()->getParam('id');
			try{
				$loadpro =Mage::getModel('catalog/product')->load($urlid);
				if($loadpro ->getTypeId()!='simple'){
					$type_id = $loadpro ->getTypeId();
					if($type_id=='virtual')
						$this->_redirect('marketplace/marketplaceaccount/editapprovedvirtual',array('id'=>$urlid));
					if($type_id=='downloadable')
						$this->_redirect('marketplace/marketplaceaccount/editapproveddownloadable',array('id'=>$urlid));	
					if($type_id=='configurable')
						$this->_redirect('marketplace/marketplaceaccount/editapprovedconfigurable',array('id'=>$urlid));
				}
			}catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	        }	        
			$this->loadLayout( array('default','marketplace_account_simpleproductedit'));
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('catalog/session');
			$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('Edit Work'));
			$this->renderLayout();
		}
	}

	public function editapprovedvirtualAction() 
	{
		if($this->getRequest()->isPost()){
			$id= $this->getRequest()->getParam('productid');
			try {
				if (!$this->_validateFormKey()) {
	             	$this->_redirect('marketplace/marketplaceaccount/editapprovedvirtual',array('id'=>$id));
					return false;
	            }
				$validated_obj = $this->validate();
				$validated_arr = $validated_obj->getData();
				if($validated_arr['error']){
					Mage::getSingleton('core/session')->addError($validated_arr['message']);
	             	$this->_redirect('marketplace/marketplaceaccount/editapprovedvirtual',array('id'=>$id));
					return false;
				}
				list($data, $errors) = $this->validatePost();			
				$customerid=Mage::getSingleton('customer/session')->getCustomerId();
				$collection_product = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('mageproductid',array('eq'=>$id))->addFieldToFilter('userid',array('eq'=>$customerid));
				if(count($collection_product)){
					if(empty($errors)){     
						Mage::getSingleton('core/session')->setEditProductId($id);
						Mage::getModel('marketplace/product')->editVirtualProduct($id,$this->getRequest()->getParams());
						Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Your portfolio has been successfully saved'));
						$this->_redirect('marketplace/marketplaceaccount/myproductslist/');
					}else{
						foreach ($errors as $message) {Mage::getSingleton('core/session')->addError($message);}
						
						$this->_redirect('marketplace/marketplaceaccount/editapprovedvirtual',array('id'=>$id));
					}
				}
			}catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	        }
			$this->_redirect('marketplace/marketplaceaccount/editapprovedsimple',array('id'=>$id));	
		}else{
			$urlid=$this->getRequest()->getParam('id');
			$loadpro =Mage::getModel('catalog/product')->load($urlid);
			if($loadpro ->getTypeId()!='virtual'){
				$type_id = $loadpro ->getTypeId();
				if($type_id=='simple')
					$this->_redirect('marketplace/marketplaceaccount/editapprovedsimple',array('id'=>$urlid));
				if($type_id=='downloadable')
					$this->_redirect('marketplace/marketplaceaccount/editapproveddownloadable',array('id'=>$urlid));	
				if($type_id=='configurable')
					$this->_redirect('marketplace/marketplaceaccount/editapprovedconfigurable',array('id'=>$urlid));
			}
			$this->loadLayout( array('default','marketplace_account_virtualproductedit'));
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('catalog/session');
			$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('MarketPlace: Edit Virtual Magento Product'));
			$this->renderLayout();
        }
    }

	public function editapproveddownloadableAction() 
	{
		if($this->getRequest()->isPost()){
			$id= $this->getRequest()->getParam('productid');
			try {
				if (!$this->_validateFormKey()) {
	             	$this->_redirect('marketplace/marketplaceaccount/editapproveddownloadable',array('id'=>$id));
					return false;
	            }
				$validated_obj = $this->validate();
				$validated_arr = $validated_obj->getData();
				if($validated_arr['error']){
					Mage::getSingleton('core/session')->addError($validated_arr['message']);
	             	$this->_redirect('marketplace/marketplaceaccount/editapproveddownloadable',array('id'=>$id));
					return false;
				}
				list($data, $errors) = $this->validatePost();
				$customerid=Mage::getSingleton('customer/session')->getCustomerId();
				$collection_product = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('mageproductid',array('eq'=>$id))->addFieldToFilter('userid',array('eq'=>$customerid));
				if(count($collection_product)){
					if(empty($errors)){     
						Mage::getSingleton('core/session')->setEditProductId($id);
						Mage::getModel('marketplace/product')->editDownloadableProduct($id,$this->getRequest()->getParams());
						Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Your portfolio has been successfully saved'));
						$this->_redirect('marketplace/marketplaceaccount/myproductslist/');
					}else{
						foreach ($errors as $message) {Mage::getSingleton('core/session')->addError($message);}
						
						$this->_redirect('marketplace/marketplaceaccount/editapproveddownloadable',array('id'=>$id));
					}
				}
			} catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	        }  
	        $this->_redirect('marketplace/marketplaceaccount/editapproveddownloadable',array('id'=>$id));   
		}else{
			$urlid=$this->getRequest()->getParam('id');
			$loadpro =Mage::getModel('catalog/product')->load($urlid);
			if($loadpro ->getTypeId()!='downloadable'){
				$type_id = $loadpro ->getTypeId();
				if($type_id=='simple')
					$this->_redirect('marketplace/marketplaceaccount/editapprovedsimple',array('id'=>$urlid));
				if($type_id=='virtual')
					$this->_redirect('marketplace/marketplaceaccount/editapprovedvirtual',array('id'=>$urlid));
				if($type_id=='configurable')
					$this->_redirect('marketplace/marketplaceaccount/editapprovedconfigurable',array('id'=>$urlid));
			}
			$this->loadLayout( array('default','marketplace_account_downloadableproductedit'));
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('catalog/session');
			$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('MarketPlace: Edit Downloadable Magento Product'));
			$this->renderLayout();
		}
	}

	public function editapprovedconfigurableAction() 
	{
		if($this->getRequest()->isPost()){
			$id= $this->getRequest()->getParam('productid');
			try {
				if (!$this->_validateFormKey()) {
	             	$this->_redirect('marketplace/marketplaceaccount/editapprovedconfigurable',array('id'=>$id));
					return false;
	            }
				$validated_obj = $this->validate();
				$validated_arr = $validated_obj->getData();
				if($validated_arr['error']){
					Mage::getSingleton('core/session')->addError($validated_arr['message']);
	             	$this->_redirect('marketplace/marketplaceaccount/editapprovedconfigurable',array('id'=>$id));
					return false;
				}
				list($data, $errors) = $this->validatePost();
				$id= $this->getRequest()->getParam('productid');
				$customerid=Mage::getSingleton('customer/session')->getCustomerId();
				$collection_product = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('mageproductid',array('eq'=>$id))->addFieldToFilter('userid',array('eq'=>$customerid));
				if(count($collection_product)){
					if(empty($errors)){	
						Mage::getSingleton('core/session')->setEditProductId($id);
						Mage::getModel('marketplace/product')->editProduct($id,$this->getRequest()->getParams());
						Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('Your portfolio has been successfully saved'));
						$this->_redirect('marketplace/marketplaceaccount/myproductslist/');
					}else{
						foreach ($errors as $message) {Mage::getSingleton('core/session')->addError($message);}
						
						$this->_redirect('marketplace/marketplaceaccount/editapprovedconfigurable',array('id'=>$id));
					}
				}
			} catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	        }
			$this->_redirect('marketplace/marketplaceaccount/editapprovedconfigurable',array('id'=>$id));
						
		}else{
			$urlid=$this->getRequest()->getParam('id');
			$loadpro =Mage::getModel('catalog/product')->load($urlid);
			if($loadpro ->getTypeId()!='configurable'){
				$type_id = $loadpro ->getTypeId();
				if($type_id=='simple')
					$this->_redirect('marketplace/marketplaceaccount/editapprovedsimple',array('id'=>$urlid));
				if($type_id=='virtual')
					$this->_redirect('marketplace/marketplaceaccount/editapprovedvirtual',array('id'=>$urlid));
				if($type_id=='downloadable')
					$this->_redirect('marketplace/marketplaceaccount/editapproveddownloadable',array('id'=>$urlid));
			}
			$this->loadLayout( array('default','marketplace_account_configurableproductedit'));
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('catalog/session');
			$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('MarketPlace: Edit Configurable Magento Product'));
			$this->renderLayout();
		}
	}
	
	public function deleteAction()
	{
		try{
			$urlapp=$_SERVER['REQUEST_URI'];
			$record=Mage::getModel('marketplace/product')->deleteProduct($urlapp);
			if($record==1){
				Mage::getSingleton('core/session')->addError( Mage::helper('marketplace')->__('YOU ARE NOT AUTHORIZE TO DELETE THIS portfolio..'));	
			}else{
				Mage::getSingleton('core/session')->addSuccess( Mage::helper('marketplace')->__('Deleted!'));
			} 
		} catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
        } 
		$this->_redirect('marketplace/marketplaceaccount/myproductslist/');
	}

	public function massdeletesellerproAction()
	{
		try{
			if($this->getRequest()->isPost()){
				if(!$this->_validateFormKey()){
					 $this->_redirect('marketplace/marketplaceaccount/myproductslist/');
				}
				$ids= $this->getRequest()->getParam('product_mass_delete');
				$customerid=Mage::getSingleton('customer/session')->getCustomerId();
				$unauth_ids = array();
				Mage::register("isSecureArea", 1);
				Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
				foreach ($ids as $id){		
					$data['id']=$id;			
					Mage::dispatchEvent('mp_delete_product', $data);		
				    $collection_product = Mage::getModel('marketplace/product')->getCollection()
				    							->addFieldToFilter('mageproductid',array('eq'=>$id))
					    						->addFieldToFilter('userid',array('eq'=>$customerid));
					if(count($collection_product)) {					
						Mage::getModel('catalog/product')->load($id)->delete();
						$collection=Mage::getModel('marketplace/product')->getCollection()
										->addFieldToFilter('mageproductid',array('eq'=>$id));
						foreach($collection as $row){
							$row->delete();
						}
					}else{
						array_push($unauth_ids, $id);
					}
				}
			}
			if(count($unauth_ids)){
				Mage::getSingleton('core/session')->addError( Mage::helper('marketplace')->__('You are not authorized to delete products with id %s',implode(",", $unauth_ids)));	
			}else{
				Mage::getSingleton('core/session')->addSuccess( Mage::helper('marketplace')->__('Products has been sucessfully deleted from your account'));
			}  
		} catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
        }
		$this->_redirect('marketplace/marketplaceaccount/myproductslist/');
	}
	
	public function mydashboardAction()
	{
		$this->loadLayout( array('default','marketplace_account_dashboard'));
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
		$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('My Dashboard'));
    	$this->renderLayout();
	}
	
	public function verifyskuAction()
	{
		try{
			$sku=$this->getRequest()->getParam('sku');
			$id = Mage::getModel('catalog/product')->getIdBySku($sku);
			if ($id){ $avl=0; }
			else{ $avl=1; } 
			$this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody(json_encode(array("avl"=>$avl)));
		} catch (Exception $e) {
            $this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody('');
        }
	}

	public function deleteimageAction()
	{
		try{			
			$storeId=Mage::app()->getStore()->getStoreId();
			Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
			$newimage = '';
			$data= $this->getRequest()->getParams();
			$_product = Mage::getModel('catalog/product')->load($data['pid'])->getMediaGalleryImages();
			$main = explode('/',$data['file']);
			foreach($_product as $_image) { 
				$arr = explode('/',$_image['path']);
				if(array_pop($arr) != array_pop($main)){
					$newimage = $_image['file'];
					$id = $_image['value_id'];
					break;
				}		
			}
			$mediaApi = Mage::getModel("catalog/product_attribute_media_api");
			$mediaApi->remove($data['pid'], $data['file']);
			if($newimage){
				$objprod=Mage::getModel('catalog/product')->load($data['pid']);
				$objprod->setSmallImage($newimage);
				$objprod->setImage($newimage);
				$objprod->setThumbnail($newimage);
				$objprod->save();
			}
			Mage::app()->setCurrentStore($storeId);
		} catch (Exception $e) {
            $this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody('');
        }
	}
	
	private function validatePost()
	{
		$errors = array();
		$data = array();
		foreach( $this->getRequest()->getParams() as $code => $value){
			switch ($code) :
				case 'name':
					if(trim($value) == '' ){$errors[] = Mage::helper('marketplace')->__('Name has to be completed');} 
					else{$data[$code] = $value;}
					break;
				case 'description':
					if(trim($value) == '' ){$errors[] = Mage::helper('marketplace')->__('Description has to be completed');} 
					else{$data[$code] = $value;}
					break;
				case 'short_description':
					if(trim($value) == ''){$errors[] = Mage::helper('marketplace')->__('Short description has to be completed');} 
					else{$data[$code] = $value;}
					break;
				case 'price':
					if(!preg_match("/^([0-9])+?[0-9.]*$/",$value)){
						$errors[] = Mage::helper('marketplace')->__('Price should contain only decimal numbers');
					}else{$data[$code] = $value;}
					break;
				case 'weight':
					if(!preg_match("/^([0-9])+?[0-9.]*$/",$value)){
						$errors[] = Mage::helper('marketplace')->__('Weight should contain only decimal numbers');
					}else{$data[$code] = $value;}
					break;
				case 'stock':
					if(!preg_match("/^([0-9])+?[0-9.]*$/",$value)){
						$errors[] = Mage::helper('marketplace')->__('Product stock should contain only an integer number');
					}else{$data[$code] = $value;}
					break;
				case 'sku_type':
					if(trim($value) == '' ){$errors[] = Mage::helper('marketplace')->__('Sku Type has to be selected');} 
					else{$data[$code] = $value;}
					break;
				case 'price_type':
					if(trim($value) == '' ){$errors[] = Mage::helper('marketplace')->__('Price Type has to be selected');} 
					else{$data[$code] = $value;}
					break;
				case 'weight_type':
					if(trim($value) == ''){$errors[] = Mage::helper('marketplace')->__('Weight Type has to be selected');} 
					else{$data[$code] = $value;}
					break;
				case 'bundle_options':
					if(trim($value) == ''){$errors[] = Mage::helper('marketplace')->__('Default Title has to be completed');} 
					else{$data[$code] = $value;}
					break;	
			endswitch;
		}
		return array($data, $errors);
	}

	public function paymentAction()
	{
		try{
			$wholedata=$this->getRequest()->getParams();
			$customerid=Mage::getSingleton('customer/session')->getCustomerId();
			$collection = Mage::getModel('marketplace/userprofile')->getCollection();
			$collection->addFieldToFilter('mageuserid',array('eq'=>$customerid));
			foreach($collection as $row){
				$id=$row->getAutoid();
			}
			$collectionload = Mage::getModel('marketplace/userprofile')->load($id);
			$collectionload->setpaymentsource($wholedata['paymentsource']);
			$collectionload->save();
			Mage::getSingleton('core/session')->addSuccess( Mage::helper('marketplace')->__('Your Payment Information Is Sucessfully Saved.'));
		} catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
        }
		$this->_redirect('marketplace/marketplaceaccount/editProfile');
	}
	
	public function askquestionAction()
	{
		try{
			$customerid=Mage::getSingleton('customer/session')->getCustomerId();
			$seller = Mage::getModel('customer/customer')->load($customerid);
			$email = $seller->getEmail();
			$name = $seller->getFirstname()." ".$seller->getLastname();
			$adminname = Mage::helper('marketplace')->__('Admin');
			$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
			$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
			$emailTemp = Mage::getModel('core/email_template')->loadDefault('queryadminemail');
			$emailTempVariables = array();
			$emailTempVariables['myvar1'] = $_POST['subject'];
			$emailTempVariables['myvar2'] =$name;
			$emailTempVariables['myvar3'] = $_POST['ask'];
			$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
			$emailTemp->setSenderName($name);
			$emailTemp->setSenderEmail($email);
			$emailTemp->send($adminEmail,Mage::helper('marketplace')->__('Admin'),$emailTempVariables);
		} catch (Exception $e) {
            $this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody('');
        }
	}

	public function deleteprofileimageAction()
	{
		try{
			$collection = Mage::getModel('marketplace/userprofile')->getCollection();
			$collection->addFieldToFilter('mageuserid',array('eq'=>$this->_getSession()->getCustomerId()));
			foreach($collection as  $value){ 
				$data = $value; 
				$id = $value->getAutoid(); 
			}
			Mage::getModel('marketplace/userprofile')->load($id)->setBannerpic('')->save();
			$this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody("true");
		} catch (Exception $e) {
           	$this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody('false');
        }
	}

	public function deletelogoimageAction()
	{
		try{
			$collection = Mage::getModel('marketplace/userprofile')->getCollection();
			$collection->addFieldToFilter('mageuserid',array('eq'=>$this->_getSession()->getCustomerId()));
			foreach($collection as  $value){ 
				$data = $value; 
				$id = $value->getAutoid(); 
			}
			Mage::getModel('marketplace/userprofile')->load($id)->setLogopic('')->save();
			$this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody("true");
		} catch (Exception $e) {
           	$this->getResponse()->setHeader('Content-type', 'text/html');
			$this->getResponse()->setBody('false');
        }
	}

	public function editprofileAction()
	{
		if($this->getRequest()->isPost()){
			try{
				if (!$this->_validateFormKey()) {
					return $this->_redirect('marketplace/marketplaceaccount/editProfile');
				}
				list($data, $errors) = $this->validateprofiledata();
				$fields = $this->getRequest()->getParams();				
				$loid=$this->_getSession()->getCustomerId();
				$img1='';
				$img2='';
	            if(empty($errors)){	
	           		$auto_id = '';	
					$write = Mage::getSingleton('core/resource')->getConnection('core_write');
					$collection = Mage::getModel('marketplace/userprofile')->getCollection();
					$collection->addFieldToFilter('mageuserid',array('eq'=>$this->_getSession()->getCustomerId()));
					foreach($collection as  $value){
						$auto_id = $value->getAutoid();
					}
					if(!isset($fields['tw_active'])){
						$fields['tw_active']=0;
					}
					if(!isset($fields['fb_active'])){
						$fields['fb_active']=0;
					}
					if(!isset($fields['gplus_active'])){
						$fields['gplus_active']=0;
					}
					if(!isset($fields['youtube_active'])){
						$fields['youtube_active']=0;
					}
					if(!isset($fields['vimeo_active'])){
						$fields['vimeo_active']=0;
					}
					if(!isset($fields['instagram_active'])){
						$fields['instagram_active']=0;
					}
					if(!isset($fields['pinterest_active'])){
						$fields['pinterest_active']=0;
					}
					if(!isset($fields['moleskine_active'])){
						$fields['moleskine_active']=0;
					}
							
					$value = Mage::getModel('marketplace/userprofile')->load($auto_id);					
					$value->addData($fields);
					$value->save();
					if($fields['compdesi']){
						$fields['compdesi'] = str_replace('script', '', $fields['compdesi']);
					}
					$value->setcompdesi($fields['compdesi']);

					if(isset($fields['returnpolicy'])){
						$fields['returnpolicy'] = str_replace('script', '', $fields['returnpolicy']);
						$value->setReturnpolicy($fields['returnpolicy']);
					}				

					if(isset($fields['shippingpolicy'])){
						$fields['shippingpolicy'] = str_replace('script', '', $fields['shippingpolicy']);
						$value->setShippingpolicy($fields['shippingpolicy']);
					}				

					$value->setMetaDescription($fields['meta_description']);
					if(strlen($_FILES['bannerpic']['name'])>0){
						$extension = pathinfo($_FILES["bannerpic"]["name"], PATHINFO_EXTENSION);
						$temp = explode(".",$_FILES["bannerpic"]["name"]);
	                    $img1 = $temp[0].rand(1,99999).$loid.'.'.$extension;
						$value->setbannerpic($img1);
					}
					if(strlen($_FILES['logopic']['name'])>0){
						$extension = pathinfo($_FILES["logopic"]["name"], PATHINFO_EXTENSION);
						$temp1 = explode(".",$_FILES["logopic"]["name"]);
	                    $img2 = $temp1[0].rand(1,99999).$loid.'.'.$extension;
						$value->setlogopic($img2);
					}
					if (array_key_exists('countrypic', $fields)) {
						$value->setcountrypic($fields['countrypic']);
					}
					$value->save();
					$target =Mage::getBaseDir().'/media/avatar/';
					$targetb = $target.$img1; 
					
					move_uploaded_file($_FILES['bannerpic']['tmp_name'],$targetb);
					$targetl = $target.$img2; 
					move_uploaded_file($_FILES['logopic']['tmp_name'],$targetl);
		           try{
						if(!empty($errors)){
			                foreach ($errors as $message){$this->_getSession()->addError($message);}
			            }else{Mage::getSingleton('core/session')->addSuccess( Mage::helper('marketplace')->__('Profile information was successfully saved'));}
		                $this->_redirect('marketplace/marketplaceaccount/editProfile');
		                return;
		            }catch (Mage_Core_Exception $e){
		                $this->_getSession()->addError($e->getMessage());
		            }catch (Exception $e){
		                $this->_getSession()->addException($e,  Mage::helper('marketplace')->__('Cannot save the customer.'));
		            }
					$this->_redirect('customer/*/*');
				}else{
					foreach ($errors as $message) {Mage::getSingleton('core/session')->addError($message);}
					
					$this->_redirect('marketplace/marketplaceaccount/editProfile');
				}
			} catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	            $this->_redirect('marketplace/marketplaceaccount/editProfile');
	        }
        }
		else{
			$this->loadLayout( array('default','marketplace_account_editaccount'));
			$this->_initLayoutMessages('customer/session');
			$this->_initLayoutMessages('catalog/session');
			$this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('Profile Information'));
			$this->renderLayout();
		}  
    }

	public function rewriteurlAction()
	{
		if($this->getRequest()->isPost()){
			try{
				if (!$this->_validateFormKey()) {
					return $this->_redirect('marketplace/marketplaceaccount/editProfile');
				}
				$profileurl = '';
				$fields = $this->getRequest()->getParams();
				$collection = Mage::getModel('marketplace/userprofile')->getCollection();
				$collection->addFieldToFilter('mageuserid',array('eq'=>$this->_getSession()->getCustomerId()));
				foreach($collection as  $value){
					$profileurl = $value->getProfileurl();
				}
				if($fields['profile_request_url']){
					$source_url = 'marketplace/seller/profile/'.$profileurl;
					/*
					* Check if already rexist in url rewrite model
					*/
					$url_id = '';
					$profile_request_url = '';
					$url_coll = Mage::getModel('core/url_rewrite')->getCollection()
									->addFieldToFilter('target_path',array('eq'=>$source_url))
									->addFieldToFilter('store_id',array('eq'=>Mage::app()->getStore()->getStoreId()));
					foreach ($url_coll as $value) {
						$url_id = $value->getId();
						$profile_request_url = $value->getRequestPath();
					}
					if($profile_request_url != $fields['profile_request_url']){
						$id_path= rand(1,100000);
						Mage::getModel('core/url_rewrite')->load($url_id)
							->setStoreId(Mage::app()->getStore()->getStoreId())
							->setIsSystem(0)
							->setIdPath($id_path)
							->setTargetPath($source_url)
							->setRequestPath($fields['profile_request_url'])
							->save();
					}
				}
				if($fields['collection_request_url']){
					$source_url = 'marketplace/seller/collection/'.$profileurl;
					/*
					* Check if already rexist in url rewrite model
					*/
					$url_id = '';
					$collection_request_url = '';
					$url_coll = Mage::getModel('core/url_rewrite')->getCollection()
									->addFieldToFilter('target_path',array('eq'=>$source_url))
									->addFieldToFilter('store_id',array('eq'=>Mage::app()->getStore()->getStoreId()));
					foreach ($url_coll as $value) {
						$url_id = $value->getId();
						$collection_request_url = $value->getRequestPath();
					}
					if($collection_request_url != $fields['collection_request_url']){
						$id_path= rand(1,100000);
						Mage::getModel('core/url_rewrite')->load($url_id)
							->setStoreId(Mage::app()->getStore()->getStoreId())
							->setIsSystem(0)
							->setIdPath($id_path)
							->setTargetPath($source_url)
							->setRequestPath($fields['collection_request_url'])
							->save();
					}
				}
				if($fields['review_request_url']){
					$source_url = 'marketplace/seller/feedback/'.$profileurl;
					/*
					* Check if already rexist in url rewrite model
					*/
					$url_id = '';
					$review_request_url = '';
					$url_coll = Mage::getModel('core/url_rewrite')->getCollection()
									->addFieldToFilter('target_path',array('eq'=>$source_url))
									->addFieldToFilter('store_id',array('eq'=>Mage::app()->getStore()->getStoreId()));
					foreach ($url_coll as $value) {
						$url_id = $value->getId();
						$review_request_url = $value->getRequestPath();
					}
					if($review_request_url != $fields['review_request_url']){
						$id_path= rand(1,100000);
						Mage::getModel('core/url_rewrite')->load($url_id)
							->setStoreId(Mage::app()->getStore()->getStoreId())
							->setIsSystem(0)
							->setIdPath($id_path)
							->setTargetPath($source_url)
							->setRequestPath($fields['review_request_url'])
							->save();
					}
				}
				if($fields['location_request_url']){
					$source_url = 'marketplace/seller/location/'.$profileurl;
					/*
					* Check if already rexist in url rewrite model
					*/
					$url_id = '';
					$location_request_url = '';
					$url_coll = Mage::getModel('core/url_rewrite')->getCollection()
									->addFieldToFilter('target_path',array('eq'=>$source_url))
									->addFieldToFilter('store_id',array('eq'=>Mage::app()->getStore()->getStoreId()));
					foreach ($url_coll as $value) {
						$url_id = $value->getId();
						$location_request_url = $value->getRequestPath();
					}
					if($location_request_url != $fields['location_request_url']){
						$id_path= rand(1,100000);
						Mage::getModel('core/url_rewrite')->load($url_id)
							->setStoreId(Mage::app()->getStore()->getStoreId())
							->setIsSystem(0)
							->setIdPath($id_path)
							->setTargetPath($source_url)
							->setRequestPath($fields['location_request_url'])
							->save();
					}
				}
				Mage::getSingleton('core/session')->addSuccess(Mage::helper('marketplace')->__('The URL Rewrite has been saved.'));
			} catch (Exception $e) {
	            Mage::getSingleton('core/session')->addError($e->getMessage());
	        }
        }
        $this->_redirect('marketplace/marketplaceaccount/editProfile');
    }

    private function validateprofiledata()
    {
		$errors = array();
		$data = array();
		foreach( $this->getRequest()->getParams() as $code => $value){
			switch ($code) :
				case 'twitterid':
					if(trim($value) != '' && preg_match('/[\'^£$%&*()}{@#~?><>, |=_+¬-]/', $value)){$errors[] = Mage::helper('marketplace')->__('Twitterid cannot contain space and special charecters');} 
					else{$data[$code] = $value;}
					break;
				case 'facebookid':
					if(trim($value) != '' &&  preg_match('/[\'^£$%&*()}{@#~?><>, |=_+¬-]/', $value)){$errors[] = Mage::helper('marketplace')->__('Facebookid cannot contain space and special charecters');} 
					else{$data[$code] = $value;}
					break;
				case 'backgroundth':
					if(trim($value) != '' && strlen($value)!=6 && substr($value, 0, 1) != "#"){$errors[] = Mage::helper('marketplace')->__('Invalid Background Color');} 
					else{$data[$code] = $value;}
					break;
			endswitch;
		}
		return array($data, $errors);
	}
	
	public function mytransactionAction()
	{
	   $this->loadLayout( array('default','marketplace_transaction_info'));
	   $this->_initLayoutMessages('customer/session');
       $this->_initLayoutMessages('catalog/session');
	   $this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('Transactions'));
	   $this->renderLayout();  
	}

	public function viewtransdetailsAction()
	{
	   $this->loadLayout( array('default','marketplace_marketplaceaccount_viewtransdetails'));
	   $this->_initLayoutMessages('customer/session');
       $this->_initLayoutMessages('catalog/session');
	   $this->getLayout()->getBlock('head')->setTitle( Mage::helper('marketplace')->__('Transaction Details'));
	   $this->renderLayout();  
	}

	public function downloadtranscsvAction()
	{
		try{
			$filter_data_to = '';
	        $filter_data_frm = '';
	        $transid = '';
	        $from = null;
	        $to = null;
	    	$id = Mage::getSingleton('customer/session')->getId();
	        if(isset($_GET['transid'])){
	            $transid = $_GET['transid'] != ""?$_GET['transid']:"";
	        }
	        if(isset($_GET['from_date'])){
	            $filter_data_frm = $_GET['from_date'] != ""?$_GET['from_date']:"";
	        }
	        if(isset($_GET['to_date'])){
	            $filter_data_to = $_GET['to_date'] != ""?$_GET['to_date']:"";
	        }
	        if($filter_data_to){
	            $todate = date_create($filter_data_to);
	            $to = date_format($todate, 'Y-m-d 23:59:59');
	        }
	        if($filter_data_frm){
	            $fromdate = date_create($filter_data_frm);
	            $from = date_format($fromdate, 'Y-m-d H:i:s');
	        }
	        $collection = Mage::getModel('marketplace/sellertransaction')->getCollection();
	        $collection->addFieldToFilter('sellerid',array('eq'=>$id));
	        if($transid){
	            $collection->addFieldToFilter('transactionid', array('eq' => $transid));
	        }
	        if($from || $to){
	            $collection->addFieldToFilter('created_at', array('datetime' => true,'from' => $from,'to' =>  $to));
	        }
	        $collection->setOrder('transid');

	        $data = array();
	        foreach ($collection as $transactioncoll) {
	        	$data1 =array();
	        	$data1['Date'] = Mage::helper('core')->formatDate($transactioncoll->getCreatedAt(), 'medium', false);
	        	$data1['Transaction Id'] = $transactioncoll->getTransactionid();
	        	if($transactioncoll->getCustomnote()) {
					$data1['Comment Message'] = $transactioncoll->getCustomnote(); 
				}else {
			 		$data1['Comment Message'] = Mage::helper('marketplace')->__('None');
				}
	        	$data1['Transaction Amount'] = Mage::helper('core')->currency($transactioncoll->getTransactionamount(), true, false);
				$data[] = $data1;
	        }

		    header('Content-Type: text/csv');
		    header('Content-Disposition: attachment; filename=transactionlist.csv');
		    header('Pragma: no-cache');
		    header("Expires: 0");

		    $outstream = fopen("php://output", "w");    
		    fputcsv($outstream, array_keys($data[0]));

		    foreach($data as $result)
		    {
		        fputcsv($outstream, $result);
		    }

		    fclose($outstream);
		} catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            $this->_redirect('marketplace/marketplaceaccount/mytransaction');
        }
	}
	
	public function deletelinkAction()
	{
		$data= $this->getRequest()->getParams();
		if(isset($data['id'])){
			$_product = Mage::getModel('downloadable/link')->load($data['id'])->delete();
		}
	}
	
	public function deletesampleAction()
	{
		$data= $this->getRequest()->getParams();
		if(isset($data['id'])){
			$_product = Mage::getModel('downloadable/sample')->load($data['id'])->delete();
		}
	}
}
