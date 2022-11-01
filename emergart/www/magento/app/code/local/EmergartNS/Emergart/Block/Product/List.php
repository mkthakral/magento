<?php
class EmergartNS_Emergart_Block_Product_List extends Mage_Catalog_Block_Product_List{
	protected function _getProductCollection()
    {
		if (is_null($this->_productCollection)) {
            $layer = $this->getLayer();
            /* @var $layer Mage_Catalog_Model_Layer */
            if ($this->getShowRootCategory()) {
                $this->setCategoryId(Mage::app()->getStore()->getRootCategoryId());
            }

            // if this is a product view page
            if (Mage::registry('product')) {
                // get collection of categories this product is associated with
                $categories = Mage::registry('product')->getCategoryCollection()
                    ->setPage(1, 1)
                    ->load();
                // if the product is associated with any category
                if ($categories->count()) {
                    // show products from this category
                    $this->setCategoryId(current($categories->getIterator()));
                }
            }

            $origCategory = null;
            if ($this->getCategoryId()) {
                $category = Mage::getModel('catalog/category')->load($this->getCategoryId());
                if ($category->getId()) {
                    $origCategory = $layer->getCurrentCategory();
                    $layer->setCurrentCategory($category);
                    $this->addModelTags($category);
                }
            }
            
			//Default OOB
			//$this->_productCollection = $layer->getProductCollection();
            
            //Showing just endorsed products
		    //$this->_productCollection = $layer->getProductCollection()->addAttributeToFilter('galcode', array('notnull'=> false ));

			
	        //show products randomly
			$products = $layer->getProductCollection();
			$products->addAttributeToSelect('*');
			$products->getSelect()->order('RAND()');
			$this->_productCollection = $products;
			//end random code
			
			
			$this->prepareSortableFieldsByCategory($layer->getCurrentCategory());

            if ($origCategory) {
                $layer->setCurrentCategory($origCategory);
            }
        }

        return $this->_productCollection;
    }
}
?>