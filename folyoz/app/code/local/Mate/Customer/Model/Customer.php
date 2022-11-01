<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Mage
 * @package     Mage_Customer
 * @copyright  Copyright (c) 2006-2017 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer model
 *
 * @category    Mage
 * @package     Mage_Customer
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mate_Customer_Model_Customer extends Mage_Customer_Model_Customer
{

	public function stripeApi($id)
	{
		if($id) {
			
			$resource = Mage::getSingleton('core/resource');
			$readAdapter= $resource->getConnection('core_read');		
			$table = $resource->getTableName('cryozonic_stripesubscriptions_customers');
			$query = "SELECT stripe_id as sid FROM ".$table." WHERE customer_id = ".$id;
			$result = $readAdapter->fetchAll($query);
			if(!empty($result)) {
				$url = "https://api.stripe.com/v1/customers/".$result[0]['sid'];
				$apiKey = 'Bearer sk_test_WFxAGuLlWZicjON1afPvVCIC'; // should match with Server key
				$headers = array(
					 'Authorization: '.$apiKey
				);
				
				// Send request to Server
				$ch = curl_init($url);
				// To save response in a variable from server, set headers;
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				// Get response
				$data = curl_exec($ch);
				$result = curl_exec($ch);
				$data = json_decode($data);
				$active = $data->subscriptions->data[0]->id;
				if(!empty($active)) {
					$response = array("status"=>"active","code"=>"200");
				} else {
					$response = array("status"=>"","code"=>"503");
				}

				return  $response;
			} else {
				$response = array("status"=>"","code"=>"503");
				return  $response;
			}
			
		} else {
				$response = array("status"=>"","code"=>"503");
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