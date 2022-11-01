<?php

class Mate_Customer_Model_Customer extends Mage_Customer_Model_Customer
{
	public function stripeApi($id)
	{
		if($id) {
			$customer = Mage::getModel('customer/customer')->load($id);
			$cust_email = '';
			if ($customer->getId()) { // TRUE if customer with given ID exists  
			    $cust_email = $customer->getEmail();
			}
	
			$resource = Mage::getSingleton('core/resource');
			$readAdapter= $resource->getConnection('core_read');		
			$table = $resource->getTableName('cryozonic_stripesubscriptions_customers');
			$query = "SELECT stripe_id as sid FROM ".$table." WHERE customer_email = '".$cust_email."'"." and customer_id=".$id;
			//Mage::log('Query: '.$query, null, 'stripe.log', true);
			$result = $readAdapter->fetchAll($query);
			if(!empty($result)) {
				$url = "https://api.stripe.com/v1/customers/".$result[0]['sid'];
                //Mage::log('Customer Id: '.$result[0]['sid'], null, 'stripe.log', true);
				//Test Key
                //$apiKey = 'Bearer sk_test_WFxAGuLlWZicjON1afPvVCIC'; // should match with Server key
                //Live Key
                $apiKey = 'Bearer sk_live_rUfs0Q8c7EcKUTabZsOo76CB';
				$headers = array(
					 'Authorization: '.$apiKey
				);
				
				// Send request to Server
				$ch = curl_init($url);
				// To save response in a variable from server, set headers;
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                //To disable SSL in Local
                //    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                //    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                //Curl Debugging
                    //curl_setopt($ch, CURLOPT_VERBOSE, true);
                    //curl_setopt_array($ch,[CURLOPT_STDERR => fopen('./curl.log', 'w+'),]);
				//Enable SSL Cert in Life
                curl_setopt($ch, CURLOPT_CAINFO, '/etc/letsencrypt/live/folyoz.com/cert.pem');
				// Get response
				$data = curl_exec($ch);
				$info = curl_getinfo($ch);
                $result = curl_exec($ch);
				$data = json_decode($data);
				$active = $data->subscriptions->data[0]->id;
                //Mage::log('URL: '.$url, null, 'stripe.log', true);
                //Mage::log('URL: '.var_dump($info), null, 'stripe.log', true);
                //Mage::log('Data: '.$data, null, 'stripe.log', true);
                //Mage::log('Subscriptions: '.$data->subscriptions, null, 'stripe.log', true);
                
				if(!empty($active)) {
					$response = array("status"=>"active","code"=>"200");
					//Mage::log('This customer has subscription ON:'.$id, null, 'stripe.log', true);
				} else {
					$response = array("status"=>"","code"=>"503");
					//Mage::log('This customer has subscription OFF 1:'.$id, null, 'stripe.log', true);
				}

				return  $response;
			} else {
				$response = array("status"=>"","code"=>"503");
				//Mage::log('This customer has subscription OFF 2:'.$id, null, 'stripe.log', true);
				return  $response;
			}
			
		} else {
				$response = array("status"=>"","code"=>"503");
				//Mage::log('This customer has subscription OFF 3:'.$id, null, 'stripe.log', true);
				return  $response;
		}

	}
	
	public function checkRecurring($id) 
	{
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
				$result = $this->array_flatten($result);
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
	public function array_flatten($array) { 
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

	public function extendedProductCollection()
	{
		$collection_product = Mage::getModel('marketplace/product')->getCollection();
		$pro_id = array();
		$user = array();
		$maxallow = Mage::getStoreConfig('marketplace/marketplace_inventory/max_allowed_portfolio_product_images');
		foreach($collection_product as $key)
		{
			$subscription = $this->checkRecurring($key->getData('userid')); // check user user subscription form stripe api
			if($subscription['status'] == 1) {
				$pro_id[] = $key->getData('mageproductid');
 			} else {
				
				$p = Mage::getModel('catalog/product')->load($key->getData('mageproductid'));
				$counts = array_count_values($user);
				if($p->getData('is_portfolio') == true and $counts[$key->getData('userid')] <= $maxallow) {
					$user[] = $key->getData('userid');
					$pro_id[] = $key->getData('mageproductid');
				}
			} 
		}
		return $pro_id;
	}
	
}

?>