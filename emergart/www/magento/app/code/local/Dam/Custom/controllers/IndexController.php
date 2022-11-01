<?php
class Dam_Custom_IndexController extends Mage_Core_Controller_Front_Action{
    public function IndexAction() {
      
	  $this->loadLayout();   
	  $this->getLayout()->getBlock("head")->setTitle($this->__("Meet the Artists"));
    $this->renderLayout(); 
	  
    }
    public function DetailsAction() {

// $id = $this->getRequest()->getParam('id');
// $collection = Mage::getModel('customer/customer')
        
        
//         ->load($id);
//         echo '<pre>';print_r($collection->getData());

    $this->loadLayout();
    $this->getLayout()->getBlock("head")->setTitle($this->__("Gallery Details"));          
 
        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'custom',
            array('template' => 'custom/details.phtml')
        );
    
        $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
        $this->getLayout()->getBlock('content')->append($block);
        $this->_initLayoutMessages('core/session'); 
        $this->renderLayout();
    

    }
	
	  public function ArtistAction() {

		$id = $this->getRequest()->getParam('id');
		$customer = Mage::getModel('customer/customer')->load($id);
		$this->loadLayout();
		$this->getLayout()->getBlock("head")->setTitle($this->__($customer->getName()));          
 
        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'custom',
            array('template' => 'custom/artist.phtml')
        );
    
        $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
        $this->getLayout()->getBlock('content')->append($block);
        $this->_initLayoutMessages('core/session'); 
        $this->renderLayout();
    

    }
}