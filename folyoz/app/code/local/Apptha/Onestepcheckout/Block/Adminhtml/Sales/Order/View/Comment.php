<?php
/**
 * @name         :  Apptha One Step Checkout
 * @version      :  1.4
 * @since        :  Magento 1.4
 * @author       :  Apptha - http://www.apptha.com
 * @copyright    :  Copyright (C) 2011 Powered by Apptha
 * @license      :  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @Creation Date:  June 20 2011
 * 
 * */ ?>

<?php
class Apptha_Onestepcheckout_Block_Adminhtml_Sales_Order_View_Comment extends Mage_Adminhtml_Block_Sales_Order_View_Items
{
	public function _toHtml(){
        $html = parent::_toHtml();
        $comment = $this->getCommentHtml();
        return $html.$comment;
    }

    /**
     * get comment from order and return as html formatted string
     *
     *@return string
     */
    public function getCommentHtml(){
        $comment = $this->getOrder()->getOnestepcheckoutCustomercomment();
        $html = '';

        if ($this->isShowCustomerCommentEnabled() && $comment){
            $html .= '<div id="customer_comment" class="giftmessage-whole-order-container"><div class="entry-edit">';
            $html .= '<div class="entry-edit-head"><h4>'.$this->helper('onestepcheckout')->__('Customer Comment').'</h4></div>';
            $html .= '<fieldset>'.nl2br($this->helper('onestepcheckout')->htmlEscape($comment)).'</fieldset>';
            $html .= '</div></div>';
        }
        return $html;
    }

    public function isShowCustomerCommentEnabled(){
        return Mage::getStoreConfig('onestepcheckout/display_option/display_comments', $this->getOrder()->getStore());
    }
}
