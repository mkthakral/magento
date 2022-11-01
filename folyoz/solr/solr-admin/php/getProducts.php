<?php
	require_once('../../../app/Mage.php');
    Mage::app();

        $productCollection = Mage::getModel('marketplace/product')->getCollection();
		$qualifiedProducts = array();
		$user = array();
		$maxallow = Mage::getStoreConfig('marketplace/marketplace_inventory/max_allowed_portfolio_product_images');
        
        foreach($productCollection as $product)
		{
            //check user user subscription form stripe api
            $subscription = checkRecurring($product->getData('userid')); 
            if($subscription['status'] == 1) {
				$qualifiedProducts[] = $product->getData('mageproductid');
 			} else {
				$productMage = Mage::getModel('catalog/product')->load($product->getData('mageproductid'));
                $counts = array_count_values($user);
                //echo print_r($counts);
				if($productMage->getData('is_portfolio') == true and $counts[$product->getData('userid')] <= $maxallow) {
                    $user[] = $product->getData('userid');
                    //echo print_r("user: " . $product->getData('userid')."<br/>");
					$qualifiedProducts[] = $product->getData('mageproductid');
				}
			} 
        }
        
      //echo print_r($qualifiedProducts);
	  foreach($qualifiedProducts as $productId){
		  $product = Mage::getModel('catalog/product')->load($productId);
		  $productName = $product->getName();
		  echo "$productId,$productName<br>";
	  }
	 //echo ''.implode(',', $qualifiedProducts).'';

     function checkRecurring($id) {
		if(!empty($id)) {	
			$customer = Mage::getModel('customer/customer')->load($id);
			if(empty($customer->getId()) or $customer->getGroupId() != 1) {
				
				return array('status' => 0, 'meaning' => 'Invalid');
			} else {
				$resource = Mage::getSingleton('core/resource');
				$readAdapter= $resource->getConnection('core_read');		
				$table = $resource->getTableName('sales_recurring_profile');
				$query = "SELECT state FROM ".$table." WHERE customer_id = ".$id;
				$result = $readAdapter->fetchAll($query);
				$result = array_flatten($result);
				if(empty($result)) {		
					return array('status' => 2, 'meaning' => 'Inactive Subscription');
				} elseif (in_array('active',$result)) {
					return array('status' => 1, 'meaning' => 'Active Subscription');
				} elseif (in_array('canceled',$result)){
					return array('status' => 3, 'meaning' => 'Subscription Expired');
				} else {
					return array('status' => 4, 'meaning' => 'Error');
				}
			}	
			
		} else {
			return array('status' => 4, 'meaning' => 'Error');
		}
    }
    

    // two dimensional to one dimensional array
	function array_flatten($array) { 
		if (!is_array($array)) { 
			return FALSE; 
		} 
		$result = array(); 
		foreach ($array as $value) { 
			if (is_array($value)) { 
			  $result[] = $value['state']; 
			}
		}
		return $result; 
	}
    
?>