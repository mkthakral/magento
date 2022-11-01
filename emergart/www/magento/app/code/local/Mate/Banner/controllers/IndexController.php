<?php
class Mate_Banner_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
    	
    	/*
    	 * Load an object by id 
    	 * Request looking like:
    	 * http://site.com/banner?id=15 
    	 *  or
    	 * http://site.com/banner/id/15 	
    	 */
    	/* 
		$banner_id = $this->getRequest()->getParam('id');

  		if($banner_id != null && $banner_id != '')	{
			$banner = Mage::getModel('banner/banner')->load($banner_id)->getData();
		} else {
			$banner = null;
		}	
		*/
		
		 /*
    	 * If no param we load a the last created item
    	 */ 
    	/*
    	if($banner == null) {
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$bannerTable = $resource->getTableName('banner');
			
			$select = $read->select()
			   ->from($bannerTable,array('banner_id','title','content','status'))
			   ->where('status',1)
			   ->order('created_time DESC') ;
			   
			$banner = $read->fetchRow($select);
		}
		Mage::register('banner', $banner);
		*/

			
		$this->loadLayout();     
		$this->renderLayout();
    }
}