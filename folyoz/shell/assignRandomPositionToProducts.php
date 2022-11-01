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
 * @package     Mage_Shell
 * @copyright  Copyright (c) 2006-2016 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
require_once 'abstract.php';

/**
 * Mage_Product_Random_Listing Script
 *
 * @category    Mage
 * @package     Mage_Shell
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_Product_Random_Listing extends Mage_Shell_Abstract
{
   
	
	public $illustrationCategoryId = 3;
	public $photographyCategoryId = 4;
  
   /**
     * Run script
     *
     */
    public function run()
    {
	$this->updateProductPositions($this->illustrationCategoryId);
	$this->updateProductPositions($this->photographyCategoryId);
    }
	
    public function updateProductPositions($categoryId){
	 $category = Mage::getModel('catalog/category')
	             ->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
                     ->load($categoryId);
	 $products = $category->getProductsPosition();

         foreach($products as $productId => $position ){
	    $products[$productId] = '' . rand(0,9999);
            usleep(500000);
         }

         $category->setPostedProducts($products);

         try{
            $category->save();
         }catch(Exception $e){
            echo $e->getMessage();
         }
		
   }
}

#Creating lock to block upload/edit of product
//$lockfile = "/var/www/magento/.lock-random-product";
$shell = new Mage_Product_Random_Listing();
//$myfile = fopen("$lockfile", "w");
//fwrite($myfile, date("h:i A",strtotime('1 hour')));
//fclose($myfile);
$shell->run();
//unlink("$lockfile");
