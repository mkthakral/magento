<?php

/**
 * Nwdthemes Revolution Slider Extension
 *
 * @package     Revslider
 * @author		Nwdthemes <mail@nwdthemes.com>
 * @link		http://nwdthemes.com/
 * @copyright   Copyright (c) 2015. Nwdthemes
 * @license     http://themeforest.net/licenses/terms/regular
 */

class Nwdthemes_Revslider_Helper_Products extends Mage_Core_Helper_Abstract {

	private $_products = array();
	private $_categories = array();

	/**
	 * Gets product
	 *
	 * @param	id		Product Id
	 * @param	boolean	Load if not cached
	 * @return	array
	 */

	public function getProduct($id, $load = true) {
		if ( ! $id || (string)(int)$id !== (string)$id) {
            $product = false;
        } elseif (isset($this->_products[$id])) {
			$product = $this->_products[$id];
		} elseif ($load) {
    		$product = $this->_prepareProduct( Mage::getModel('catalog/product')->load($id) );
        } else {
            $product = false;
        }
        return $product;
	}

	/**
	 * Gets products by querey
	 *
	 * @param	array	$query
	 * @return	array
	 */

	public function getProductsByQuery($query) {

        $attributesToSelect = array('name','small_image','thumbnail','short_description','price','special_price');
        $storeId = Mage::app()->getStore()->getId();

        // workaround for admin preview, to be replaced with store selector
        if ( ! $storeId) {
            $stores = Mage::getSingleton('adminhtml/system_store')->getStoresStructure();
            $store = reset($stores);
            if (isset($store['value'])) {
                $storeId = $store['value'];
            }
        }

		$productsCollection = Mage::getResourceModel('catalog/product_collection')->addStoreFilter($storeId);

        if (Mage::helper('catalog/product_flat')->isEnabled()) {
            $productsCollection
                ->joinTable(
                    array('flat_table' => Mage::getResourceSingleton('catalog/product_flat')->getFlatTableName($storeId)),
                    'entity_id = entity_id',
                    $attributesToSelect
                )
                ->joinAttribute('image', 'catalog_product/image', 'entity_id', null, 'left');
        } else {
            $productsCollection->addAttributeToSelect('*');
        }

		if (isset($query['tax_query'][0]['taxonomy'])
			&& $query['tax_query'][0]['taxonomy'] == 'category'
			&& isset($query['tax_query'][0]['terms'])
			&& ! empty($query['tax_query'][0]['terms'])
			&& is_array($query['tax_query'][0]['terms']))
		{
            if (Mage::helper('catalog/product_flat')->isEnabled()) {

                $productsCollection
                    ->setFlag('do_not_use_category_id', true)
                    ->setFlag('disable_root_category_filter', true);

                $whereCategoryCondition = $productsCollection
                    ->getConnection()
                    ->quoteInto('cat_index.category_id IN(?) ', $query['tax_query'][0]['terms']);

                $productsCollection
                    ->getSelect()
                    ->where($whereCategoryCondition);

                $conditions = array();
                $conditions[] = "cat_index.product_id = e.entity_id";
                $conditions[] = $productsCollection
                    ->getConnection()
                    ->quoteInto('cat_index.store_id = ? ', $storeId);

                $productsCollection
                    ->getSelect()
                    ->join(
                        array('cat_index' => $productsCollection->getTable('catalog/category_product_index')),
                        join(' AND ', $conditions),
                        array()
                    );

            } else {
                $productsCollection
                    ->joinField('category_id', 'catalog/category_product', 'category_id', 'product_id = entity_id', null, 'left')
                    ->addAttributeToFilter('category_id', array('in' => $query['tax_query'][0]['terms']));
            }

		} elseif (isset($query['post__in']) && ! empty($query['post__in']) && is_array($query['post__in'])) {

			$productsCollection->addFieldToFilter('entity_id', array('in' => $query['post__in']));

		} else {
			return array();
		}

        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($productsCollection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($productsCollection);
        Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($productsCollection);

		if (isset($query['orderby']) && $query['orderby'] != 'rand') {
			$productsCollection->setOrder($query['orderby'], isset($query['order']) ? $query['order'] : 'desc');
		}

		if (isset($query['showposts'])) {
			$productsCollection->setPageSize($query['showposts']);
		}

		$productsCollection->getSelect()->group('e.entity_id');

		if (isset($query['orderby']) && $query['orderby'] == 'rand') {
			$productsCollection->getSelect()->order(new Zend_Db_Expr('RAND()'));
		}

		$products = array();
		foreach ($productsCollection as $product) {
			$products[] = $this->_prepareProduct($product);
		}
		return $products;
	}

    /**
     * Get current product Id
     * get most recent product viewed if there is no current one
     *
     * @return int
     */

    public function getCurrentProductId() {
        $currentProduct = Mage::registry('current_product');
        if ($currentProduct) {
            $currentProductId = $currentProduct->getId();
        } else {
            $recentProducts = Mage::getModel('reports/product_index_viewed')->getCollection()->getAllIds();
            $recentProducts = $this->getProductsByQuery(array('post__in' => $recentProducts, 'showposts' => 1));
            if ($recentProducts) {
                $currentProductId = $recentProducts[0]['ID'];
            } else {
                $currentProductId = false;
            }
        }
        return $currentProductId;
    }

	/**
	 * Gets category
	 *
	 * @param	id		$id
	 * @return	array
	 */

	public function getCategory($id) {

		if (isset($this->_categories[$id]))
		{
			return $this->_categories[$id];
		}

		$category = Mage::getModel('catalog/category')->load($id);
		return $this->_prepareCategory($category);
	}

	/**
	 * Gets categories
	 *
	 * @return	array
	 */

	public function getCategories() {

		$categoriesCollection = Mage::getModel('catalog/category')
			->getCollection()
			->addAttributeToSelect('name')
			->addAttributeToSort('path', 'asc');

		$categories = array();
		foreach ($categoriesCollection as $category) {
			if ($_category = $this->_prepareCategory($category))
			{
				$categories[] = $_category;
			}
		}

		return $categories;
	}

	/**
	 * Prepare product data for slider
	 *
	 * @param object $product
	 * @return array
	 */

	private function _prepareProduct($product) {

		if (isset($this->_products[$product->getId()]))
		{
			return $this->_products[$product->getId()];
		}

		$arrProduct = $product->getData();
		try{
			$arrProduct['image'] = $product->getImageUrl();
		} catch (Exception $e) {
            Mage::helper('nwdrevslider')->logException($e);
			$arrProduct['image'] = '';
		}
		$arrProduct['ID'] = $product->getId();
		$arrProduct['post_excerpt'] = $product->getShortDescription();
		$arrProduct['post_status'] = 'published';
		$arrProduct['post_category'] = '';
		$arrProduct['cart_link'] = Mage::helper('checkout/cart')->getAddUrl($product);
		$arrProduct['wishlist_link'] = Mage::helper('wishlist')->getAddUrl($product);
		$arrProduct['price'] = Mage::helper('core')->currency($product->getPrice(), true, false);
		$arrProduct['special_price'] = $product->getSpecialPrice() ? Mage::helper('core')->currency($product->getSpecialPrice(), true, false) : '';
		$arrProduct['view_link'] = $product->getProductUrl();
        $arrProduct['image_thumbnail'] = $product->getThumbnailUrl();
        $arrProduct['image_medium'] = $product->getSmallImageUrl();
        $arrProduct['image'] = Mage::getModel('catalog/product_media_config')->getMediaUrl($product->getImage());

		$this->_products[$product->getId()] = $arrProduct;

		return $arrProduct;
	}

	/**
	 * Prepare category data for slider
	 *
	 * @param object $category
	 * @return array
	 */
	private function _prepareCategory($category) {

		if (isset($this->_categories[$category->getId()])) {
			return $this->_categories[$category->getId()];
		}

		$arrCategory = $category->getData();

		if ( ! ($category->getId() > 1 && isset($arrCategory['name']) && isset($arrCategory['level']) && $arrCategory['level'] > 0)) {
			return false;
		}

		$arrCategory['count'] = 1;
		$arrCategory['name'] = str_repeat('- ', $arrCategory['level'] - 1) . $arrCategory['name'];
		$arrCategory['cat_ID'] = $category->getId();
		$arrCategory['term_id'] = $category->getId();
		$arrCategory['url'] = $category->getUrl($category);

		$this->_categories[$category->getId()] = $arrCategory;

		return $arrCategory;
	}

}