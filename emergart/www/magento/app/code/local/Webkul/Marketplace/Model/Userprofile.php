<?php
class Webkul_Marketplace_Model_Userprofile extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('marketplace/userprofile');
    }
	
	public function getRegisterDetail($customer)
	{
        $data=Mage::getSingleton('core/app')->getRequest();
		$wholedata=$data->getParams();
		if($wholedata['wantpartner']==1){
			$customerAddress = array();
			foreach ($customer->getAddresses() as $address)
			{
			   $customerAddress = $address->toArray();
			}
			$status=Mage::helper('marketplace')->getIsPartnerApproval()? 0:1;
			$assinstatus=Mage::helper('marketplace')->getIsPartnerApproval()? "Pending":"Seller";
			$customerid=$customer->getId();
			$collection=Mage::getModel('marketplace/userprofile');
			$collection->setwantpartner($status);
			$collection->setpartnerstatus($assinstatus);
			$collection->setmageuserid($customerid);
			if(isset($customerAddress['telephone'])){
				$collection->setContactnumber($customerAddress['telephone']);
			}
			$collection->setProfileurl($wholedata['profileurl']);
			$collection->save();
		}
	}
	
	public function massispartner($data){
		$wholedata=$data->getParams();
		foreach($wholedata['customer'] as $key){
			$sellerid = $key;
			$collection = Mage::getModel('marketplace/userprofile')->getCollection()->addFieldToFilter('mageuserid',array('eq'=>$key));
			foreach ($collection as $row) {
					$auto=$row->getautoid();
					$collection1 = Mage::getModel('marketplace/userprofile')->load($auto);
					$collection1->setwantpartner(1);
					$collection1->setpartnerstatus('Seller');
					$collection1->save();
			}
			$users = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('userid',array('eq'=>$sellerid));
			foreach ($users as $value) {
				$allStores = Mage::app()->getStores();
				foreach ($allStores as $_eachStoreId => $val)
				{
					Mage::getModel('catalog/product_status')->updateProductStatus($value->getMageproductid(),Mage::app()->getStore($_eachStoreId)->getId(), Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
				}
				$value->setStatus(1);
				$value->save();
			}
			$customer = Mage::getModel('customer/customer')->load($key);	
			$emailTemp = Mage::helper('marketplace')->getPartnerapproveTemplate();
			
			$emailTempVariables = array();				
			$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
			$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
			$adminUsername = Mage::helper('marketplace')->__('Admin');
			$emailTempVariables['myvar1'] = $customer->getName();
			$emailTempVariables['myvar2'] = Mage::helper('customer')->getLoginUrl();
			
			$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
			
			$emailTemp->setSenderName($adminUsername);
			$emailTemp->setSenderEmail($adminEmail);
			$emailTemp->send($customer->getEmail(),$adminUsername,$emailTempVariables);	
			Mage::dispatchEvent('mp_approve_seller',array('seller'=>$customer)); 
		}		
	}

	public function massisnotpartner($data){ 
		$wholedata=$data->getParams();
		foreach($wholedata['customer'] as $key){
			$sellerid = $key;
			$collection = Mage::getModel('marketplace/userprofile')->getCollection();
			$collection->getSelect()->where('mageuserid ='.$key);
			foreach ($collection as $row) {
					$auto=$row->getautoid();
					$collection1 = Mage::getModel('marketplace/userprofile')->load($auto);
					$collection1->setwantpartner(0);
					$collection1->setpartnerstatus('Default User');
					$collection1->save();
			}
			$users = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('userid',array('eq'=>$sellerid));
			foreach ($users as $value) {
				$id = $value->getMageproductid();
				$magentoProductModel = Mage::getModel('catalog/product')->load($id);
				$allStores = Mage::app()->getStores();
				foreach ($allStores as $_eachStoreId => $val)
				{
					Mage::getModel('catalog/product_status')->updateProductStatus($value->getMageproductid(),Mage::app()->getStore($_eachStoreId)->getId(), Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
				}
				$magentoProductModel->setStatus(2)->save();
				$value->setStatus(2);
				$value->save();
			}
			$customer = Mage::getModel('customer/customer')->load($key);	
			$emailTemp = Mage::helper('marketplace')->getPartnerdisapproveTemplate();
			$emailTempVariables = array();				
			$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
			$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
			$adminUsername = Mage::helper('marketplace')->__('Admin');
			$emailTempVariables['myvar1'] = $customer->getName();
			$emailTempVariables['myvar2'] = Mage::helper('customer')->getLoginUrl();
			
			$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
			
			$emailTemp->setSenderName($adminUsername);
			$emailTemp->setSenderEmail($adminEmail);
			$emailTemp->send($customer->getEmail(),$adminUsername,$emailTempVariables);			
			Mage::dispatchEvent('mp_disapprove_seller',array('seller'=>$customer));
		}		
	}

	public function denypartner($data){ 
		$wholedata=$data->getParams();
		$sellerid = $wholedata['sellerid'];
		$collection = Mage::getModel('marketplace/userprofile')->getCollection()
							->addFieldToFilter('mageuserid',array('eq'=>$sellerid));
		foreach ($collection as $row) {
				$auto=$row->getautoid();
				$collection1 = Mage::getModel('marketplace/userprofile')->load($auto);
				$collection1->setwantpartner(0);
				$collection1->setpartnerstatus('Default User');
				$collection1->save();
		}
		$users = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('userid',array('eq'=>$sellerid));
		foreach ($users as $value) {
			$id = $value->getMageproductid();
			$magentoProductModel = Mage::getModel('catalog/product')->load($id);
			$allStores = Mage::app()->getStores();
			foreach ($allStores as $_eachStoreId => $val)
			{
				Mage::getModel('catalog/product_status')->updateProductStatus($value->getMageproductid(),Mage::app()->getStore($_eachStoreId)->getId(), Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
			}
			$magentoProductModel->setStatus(2)->save();
			$value->setStatus(2);
			$value->save();
		}
		$customer = Mage::getModel('customer/customer')->load($sellerid);	
		$emailTemp = Mage::helper('marketplace')->getPartnerdenyTemplate();
		$emailTempVariables = array();				
		$admin_storemail = Mage::helper('marketplace')->getAdminEmailId();
		$adminEmail=$admin_storemail? $admin_storemail:Mage::helper('marketplace')->getDefaultTransEmailId();
		$adminUsername = Mage::helper('marketplace')->__('Admin');
		$emailTempVariables['myvar1'] = $customer->getName();
		$emailTempVariables['myvar2'] = $wholedata['seller_deny_reason'];
		
		$processedTemplate = $emailTemp->getProcessedTemplate($emailTempVariables);
		
		$emailTemp->setSenderName($adminUsername);
		$emailTemp->setSenderEmail($adminEmail);
		$emailTemp->send($customer->getEmail(),$customer->getName(),$emailTempVariables);
		return $customer->getName();
	}
	
	public function getPartnerProfileById($partnerId) {
        $data = array();
        if ($partnerId != '') {
            $collection = Mage::getModel('marketplace/userprofile')->getCollection();
            $collection->addFieldToFilter('mageuserid',array('eq'=>$partnerId));
			$user = Mage::getModel('customer/customer')->load($partnerId);
			$name=explode(' ',$user->getName());
			foreach ($collection as $record) {
				$data = $record;
				$bannerpic=$record->getbannerpic();
				$logopic=$record->getlogopic();
				$countrylogopic=$record->getcountrypic();
				if(strlen($bannerpic)<=0){$bannerpic='banner-image.png';}
				if(strlen($logopic)<=0){$logopic='noimage.png';}
				if(strlen($countrylogopic)<=0){$countrylogopic='';}
			}			
			$data['firstname'] = $name[0];
			$data['lastname'] = $name[1];
			$data['email'] = $user->getEmail();
			$data['bannerpic'] = $bannerpic;
			$data['logopic'] = $logopic;
			$data['countrypic'] = $countrylogopic;
			return $data;
		}
    } 
	
	public function isPartner(){
		$partnerId=Mage::getSingleton('customer/session')->getCustomerId();
		if($partnerId=='')
			$partnerId=Mage::registry('current_customer')->getId();
		if ($partnerId != '') {
			$data=0;
			$collection = Mage::getModel('marketplace/userprofile')->getCollection();
            $collection->addFieldToFilter('mageuserid',array('eq'=>$partnerId));
			foreach ($collection as $record) {
				$data=$record->getwantpartner();
			}
			return $data;
		}
	}
	public function isRightSeller($productid){
		$customerid=Mage::getSingleton('customer/session')->getCustomerId();
		$data=0;
		$product=Mage::getModel('marketplace/product')->getCollection()
													 ->addFieldToFilter('userid',array('eq'=>$customerid))
												     ->addFieldToFilter('mageproductid',array('eq'=>$productid));
		foreach ($product as $record){
				$data=1;
		}
		return $data;											 
	}
	public function getpaymentmode(){
		$partnerId=Mage::registry('current_customer')->getId();
		$collection = Mage::getModel('marketplace/userprofile')->getCollection();
        $collection->addFieldToFilter('mageuserid',array('eq'=>$partnerId));
		foreach ($collection as $record) {
			$data=$record->getPaymentsource();
		}
		return $data;
	}
	
	public function assignProduct($customer,$sid){
		$productids=explode(',',$sid);
		foreach($productids as $proid){
			$userid='';
			$product = Mage::getModel('catalog/product')->load($proid);
			if($product->getname()){   
				$collection = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('mageproductid',array('eq'=>$proid));
				foreach($collection as $coll){
				   $userid = $coll['userid'];
				}
				if($userid){
					Mage::getSingleton('adminhtml/session')->addError(Mage::helper('marketplace')->__('The product is already assigned to other seller.'));
				}
				else{
					$collection1 = Mage::getModel('marketplace/product');
					$collection1->setMageproductid($proid);
					$collection1->setUserid($customer->getId());
					$collection1->setStatus($product->getStatus());
					$collection1->setAdminassign(1);
					$collection1->setWebsiteIds(array(Mage::app()->getStore()->getStoreId()));
					$collection1->save();

					Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('marketplace')->__('Products has been successfully assigned to seller.'));
				}
			} else {
				Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('marketplace')->__("Product with id %s doesn't exist.",$proid));
			} 
		}
	}

	public function unassignProduct($customer,$sid){
		$productids=explode(',',$sid);
		foreach($productids as $proid){
			$userid='';
			$product = Mage::getModel('catalog/product')->load($proid);
			if($product->getname()){   
				$collection = Mage::getModel('marketplace/product')->getCollection()->addFieldToFilter('mageproductid',array('eq'=>$proid));
				foreach($collection as $coll){
					$coll->delete();
				}
				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('marketplace')->__('Products has been successfully unassigned to seller.'));
			} else {
				Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('marketplace')->__("Product with id %s doesn't exist.",$proid));
			} 
		}
	}
	
	public function isCustomerProducttemp($magentoProductId){
		$collection = Mage::getModel('marketplace/product')->getCollection();
		$collection->addFieldToFilter('mageproductid',array('eq'=>$magentoProductId));
		$userid='';
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
