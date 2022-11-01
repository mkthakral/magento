<?php
class Webkul_Marketplace_Block_Adminhtml_Customer_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct(){
        parent::__construct();
        $this->setId('customer_info_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('customer')->__('Customer Information'));
    }

    protected function _beforeToHtml(){
        $this->addTab('account', array(
            'label'     => Mage::helper('customer')->__('Account Information'),
            'content'   => $this->getLayout()->createBlock('adminhtml/customer_edit_tab_account')->initForm()->toHtml(),
            'active'    => Mage::registry('current_customer')->getId() ? false : true
        ));
        $this->addTab('addresses', array(
            'label'     => Mage::helper('customer')->__('Addresses'),
            'content'   => $this->getLayout()->createBlock('adminhtml/customer_edit_tab_addresses')->initForm()->toHtml(),
        ));
        if (Mage::registry('current_customer')->getId()) {
            if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
                $this->addTab('orders', array(
                    'label'     => Mage::helper('customer')->__('Orders'),
                    'class'     => 'ajax',
                    'url'       => $this->getUrl('*/*/orders', array('_current' => true)),
                 ));
            }
            $this->addTab('cart', array(
                'label'     => Mage::helper('customer')->__('Shopping Cart'),
                'class'     => 'ajax',
                'url'       => $this->getUrl('*/*/carts', array('_current' => true)),
            ));
            $this->addTab('wishlist', array(
                'label'     => Mage::helper('customer')->__('Wishlist'),
                'class'     => 'ajax',
                'url'       => $this->getUrl('*/*/wishlist', array('_current' => true)),
            ));
            if (Mage::getSingleton('admin/session')->isAllowed('newsletter/subscriber')) {
                $this->addTab('newsletter', array(
                    'label'     => Mage::helper('customer')->__('Newsletter'),
                    'content'   => $this->getLayout()->createBlock('adminhtml/customer_edit_tab_newsletter')->initForm()->toHtml()
                ));
            }
            if (Mage::getSingleton('admin/session')->isAllowed('catalog/reviews_ratings')) {
                $this->addTab('reviews', array(
                    'label'     => Mage::helper('customer')->__('Product Reviews'),
                    'class'     => 'ajax',
                    'url'       => $this->getUrl('*/*/productReviews', array('_current' => true)),
                ));
            }
            if (Mage::getSingleton('admin/session')->isAllowed('catalog/tag')) {
                $this->addTab('tags', array(
                    'label'     => Mage::helper('customer')->__('Product Tags'),
                    'class'     => 'ajax',
                    'url'       => $this->getUrl('*/*/productTags', array('_current' => true)),
                ));
            }
			$customerid=Mage::registry('current_customer')->getId();
			$isPartner= Mage::getModel('marketplace/userprofile')->isPartner();
			if($isPartner==1){
				$this->addTab('payment', array(
					'label'     => Mage::helper('marketplace')->__('Payment Information'),
					'content'   => $this->paymentmode(),
				));
				$this->addTab('sellerinformation', array(
					'label'     => Mage::helper('marketplace')->__("Seller Account Information"),
					'content'   => $this->sellerinformation(),
				));
				$this->addTab('sellercommision', array(
					'label'     => Mage::helper('marketplace')->__("Seller Commission"),
					'content'   => $this->sellercommision(),
				));
				$this->addTab('newproduct', array(
					'label'     => Mage::helper('marketplace')->__("Add Product"),
					'content'   => $this->addproduct(),
				));
				$this->addTab('removeproduct', array(
					'label'     => Mage::helper('marketplace')->__("Remove Product"),
					'content'   => $this->removeproduct(),
				));
				$this->addTab('notpartner', array(
					'label'     => Mage::helper('marketplace')->__("Do You Want To Remove This Seller?"),
					'content'   => $this->removepartner(),
				));
			}
			else {
			$this->addTab('partner', array(
					'label'     => Mage::helper('marketplace')->__('Do You Want To Make This Customer As Seller?'),
					'content'   => $this->wantpartner(),
				));
			}
			
        }
        $this->_updateActiveTab();
        Varien_Profiler::stop('customer/tabs');
        return parent::_beforeToHtml();
    }	
	protected function paymentmode(){	
		$helper = Mage::helper('marketplace');
		$row =Mage::getModel('marketplace/userprofile')->getpaymentmode();
		if($row!=''){	
			return '<div class="entry-edit">
						<div class="entry-edit-head">
							<h4 class="icon-head head-customer-view">'.$helper->__('Payment Details').'</h4>
						</div>
						<fieldset>
							<address>
								<strong>'.$row.'</strong><br>
							</address>
						</fieldset>
					</div>';
		}
		else{
			return '<div class="entry-edit">
							<div class="entry-edit-head">
								<h4 class="icon-head head-customer-view">'.$helper->__('Payment Details').'</h4>
							</div>
							<fieldset>
								<address>
									<strong>'.$helper->__('Not Mentioned Yet').'</strong><br>
								</address>
							</fieldset>
						</div>';
		}
	
	}
	
	protected function sellerinformation(){
		$helper = Mage::helper('marketplace');
		$id=Mage::registry('current_customer')->getId();	
		$partner=Mage::getModel('marketplace/userprofile')->getPartnerProfileById($id);
		$options ='';
		foreach(Mage::getModel('directory/country')->getResourceCollection()->loadByStore()->toOptionArray(true) as $country){
			if($country['value']!=''){
				$selectedval = $partner['countrypic']==$country['value']?"selected='selected'":"";
			}
			if($country['value'])
				$options = $options.'<option '.$selectedval.' value="'.$country['value'].'">'.$country['label'].'</option>';
		} 
		$html = '<style>#wksellerinfo .form-list td.value input.input-text, #wksellerinfo .form-list td.value textarea{width:100%!important;}#wksellerinfo .defaultSkin table.mceToolbar{float:left} #wksellerinfo .defaultSkin table.mceLayout{width:100%!important;}</style>
		<script language="javascript" type="text/javascript" src="'.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS).'/tiny_mce/tiny_mce.js'.'"></script>
			<script type="text/javascript">
			//< ![CDATA[
			Event.observe(window, "load", function() {
			tinyMCE.init({
			mode : "exact",
			theme : "advanced",
			elements : "compdesi,returnpolicy,shippingpolicy",
			theme_advanced_toolbar_location : "top",
			theme_advanced_toolbar_align : "left",
			theme_advanced_path_location : "bottom",
			extended_valid_elements : "a[name|href|target|title|onclick],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style]",
			theme_advanced_resize_horizontal : "false",
			theme_advanced_resizing : "false",
			apply_source_formatting : "true",
			convert_urls : "false",
			force_br_newlines : "false",
			JustifyFull : "true",
			doctype : \'< !DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">\'
			});
			});
			 
			//]]>
			</script>';
		$tw_active = '';
		$fb_active = '';
		$gplus_active = '';
		$instagram_active = '';
		$youtube_active = '';
		$vimeo_active = '';
		$pinterest_active = '';
		$moleskine_active = '';
		if($partner['tw_active'] == 1){ 
			$tw_active = "checked='checked'";
		}
		if($partner['fb_active'] == 1){ 
			$fb_active = "checked='checked'";
		}
		if($partner['gplus_active'] == 1){ 
			$gplus_active = "checked='checked'";
		}
		if($partner['instagram_active'] == 1){ 
			$instagram_active = "checked='checked'";
		}
		if($partner['youtube_active'] == 1){ 
			$youtube_active = "checked='checked'";
		}
		if($partner['vimeo_active'] == 1){ 
			$vimeo_active = "checked='checked'";
		}
		if($partner['pinterest_active'] == 1){ 
			$pinterest_active = "checked='checked'";
		}
		if($partner['moleskine_active'] == 1){ 
			$moleskine_active = "checked='checked'";
		}
		return $html.'<div class="entry-edit" id="wksellerinfo">
					<div class="entry-edit-head">
						<h4 class="icon-head head-customer-view">'.$helper->__('Seller Information').'</h4>
					</div>
					<fieldset>
						<table cellspacing="0" class="form-list">
				            <tbody>
								<tr>
							        <td class="label"><label>'.$helper->__('Twitter ID').'</label></td>
								    <td class="value">
								        <input name="twitterid" class="input-text" type="text" value="'.$partner['twitterid'].'">
								        <input type="checkbox" name="tw_active" value="1" title="'.$helper->__('Allow to Display Twitter Icon in Profile Page').'" style="margin: 5px;" '.$tw_active.'>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Facebook ID').'</label></td>
								    <td class="value">
								        <input name="facebookid" class="input-text" type="text" value="'.$partner['facebookid'].'">
								        <input type="checkbox" name="fb_active" value="1" title="'.$helper->__('Allow to Display Facebook Icon in Profile Page').'" style="margin: 5px;" '.$fb_active.'>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Instagram ID').'</label></td>
								    <td class="value">
								        <input name="instagram_id" class="input-text" type="text" value="'.$partner['instagram_id'].'">
								        <input type="checkbox" name="instagram_active" value="1" title="'.$helper->__('Allow to Display Instagram Icon in Profile Page').'" style="margin: 5px;" '.$instagram_active.'>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Google Plus ID').'</label></td>
								    <td class="value">
								        <input name="gplus_id" class="input-text" type="text" value="'.$partner['gplus_id'].'">
								        <input type="checkbox" name="gplus_active" value="1" title="'.$helper->__('Allow to Display Google Plus Icon in Profile Page').'" style="margin: 5px;" '.$gplus_active.'>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Youtube ID').'</label></td>
								    <td class="value">
								        <input name="youtube_id" class="input-text" type="text" value="'.$partner['youtube_id'].'">
								        <input type="checkbox" name="youtube_active" value="1" title="'.$helper->__('Allow to Display Youtube Icon in Profile Page').'" style="margin: 5px;" '.$youtube_active.'>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Vimeo ID').'</label></td>
								    <td class="value">
								        <input name="vimeo_id" class="input-text" type="text" value="'.$partner['vimeo_id'].'">
								        <input type="checkbox" name="vimeo_active" value="1" title="'.$helper->__('Allow to Display Vimeo Icon in Profile Page').'" style="margin: 5px;" '.$vimeo_active.'>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Pinterest ID').'</label></td>
								    <td class="value">
								        <input name="pinterest_id" class="input-text" type="text" value="'.$partner['pinterest_id'].'">
								        <input type="checkbox" name="pinterest_active" value="1" title="'.$helper->__('Allow to Display Pinterest Icon in Profile Page').'" style="margin: 5px;" '.$pinterest_active.'>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Moleskine ID').'</label></td>
								    <td class="value">
								        <input name="moleskine_id" class="input-text" type="text" value="'.$partner['moleskine_id'].'">
								        <input type="checkbox" name="moleskine_active" value="1" title="'.$helper->__('Allow to Display Moleskine Icon in Profile Page').'" style="margin: 5px;" '.$moleskine_active.'>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Contact Number').'</label></td>
								    <td class="value">
								        <input name="contactnumber" class="input-text" type="text" value="'.$partner['contactnumber'].'" placeholder="'.$helper->__('Enter Mobile Number with country code ex: (123) 456-7890').'">
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Shop Title').'</label></td>
								    <td class="value">
								        <input name="shoptitle" class="input-text" type="text" value="'.$partner['shoptitle'].'">
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Company Locality').'</label></td>
								    <td class="value">
								        <input name="complocality" class="input-text" type="text" value="'.$partner['complocality'].'">
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Country').'</label></td>
								    <td class="value">
								        <select name="countrypic" id="countrypic">
											<option value="" selected="selected" disabled="disabled">'.$helper->__("Select Country").'</option>'.$options.'</select>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Company Description').'</label></td>
								    <td class="value">
								        <textarea type="text" id="compdesi" name="compdesi" title="'.$helper->__('Company Description').'" class="input-text" >'.$partner['compdesi'].'</textarea>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Return Policy').'</label></td>
								    <td class="value">
								    	<textarea type="text" id="returnpolicy" name="returnpolicy" title="'.$helper->__('Return Policy').'" class="input-text" >'.$partner['returnpolicy'].'</textarea>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Shipping Policy').'</label></td>
								    <td class="value">
								    	<textarea type="text" id="shippingpolicy" name="shippingpolicy" title="'.$helper->__('Shipping Policy').'" class="input-text" >'.$partner['shippingpolicy'].'</textarea>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Meta Keywords').'</label></td>
								    <td class="value">
								    	<textarea type="text" id="meta_keywords" name="meta_keyword" title="Meta Keyword" class="input-text" >'.$partner['meta_keyword'].'</textarea>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Meta Description').'</label></td>
								    <td class="value">
								    	<textarea type="text" id="meta_keywords" name="meta_description" title="Meta Description" class="input-text" >'.$partner['meta_description'].'</textarea>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Company Banner').'</label></td>
								    <td class="value">
								        <input name="bannerpic" class="input-text" type="file">
								        <img style="margin:5px 0;width:700px;heigth:200px;" src="'.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'avatar/'.$partner['bannerpic'].'"/>
								    </td>
							    </tr>
							    <tr>
							        <td class="label"><label>'.$helper->__('Company Logo').'</label></td>
								    <td class="value">
								        <input name="logopic" class="input-text" type="file">
								        <div><img style="margin:5px 0;width:100px;" src="'.Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA).'avatar/'.$partner['logopic'].'"/></div>
								    </td>
							    </tr>
				            </tbody>
				        </table>
					</fieldset>
				</div>';
	}

	protected function sellercommision(){
		$helper = Mage::helper('marketplace');
		$id=Mage::registry('current_customer')->getId();	
		$collection = Mage::getModel('marketplace/saleperpartner')->getCollection();
		$collection->addFieldToFilter('mageuserid',array('eq'=>$id));	
		if(count($collection)) {
		  foreach ($collection as $value) {
				$rowcom = $value->getCommision();
			}	
		}
		else
		{
		  $rowcom = Mage::helper('marketplace')->getConfigCommissionRate(); 
		}
		$tsale=0;
		$tcomm=0;
		$tact=0;
		$collection1 = Mage::getModel('marketplace/saleslist')->getCollection();
		$collection1->addFieldToFilter('mageproownerid',array($id));	
		foreach ($collection1 as $key) {
				$tsale+=$key->gettotalamount();
				$tcomm+=$key->gettotalcommision();
				$tact+=$key->getactualparterprocost();
			}		
	
	    $comm = $rowcom;
		return '<div class="entry-edit">
					<div class="entry-edit-head">
						<h4 class="icon-head head-customer-view">'.$helper->__('Commission Details').'</h4>
					</div>
					<fieldset>
						'.$helper->__('Set Commission In Percentage For This Particular Seller').' : <input name="commision" type="text" classs="input-text no-changes" value="'.$rowcom.'"/>
						<table class="grid table" id="customer_cart_grid1_table" >
							<tr>
								<td>&nbsp;</td>
								<td>&nbsp;</td>
							</tr>
							<tr>
								<td>'.$helper->__('Total Sale').'</td>
								<td>'.Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol().' '.$tsale.'</td>
							</tr>
							<tr>
								<td>'.$helper->__('Total Seller Sale').'</td>
								<td>'.Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol().' '.$tact.'</td>
							</tr>
							<tr>
								<td>'.$helper->__('Total Admin Sale').'</td>
								<td>'.Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol().' '.$tcomm.'</td>
							</tr>
							<tr>
								<td>'.$helper->__('Current Commission %').'</td>
								<td>'.$comm.'%</td>
							</tr>
						</table>
					</fieldset>							
				</div>';
	}

	protected function addproduct(){
		$helper = Mage::helper('marketplace');
		$customerId=Mage::registry('current_customer')->getId();
		$coll=Mage::getModel('marketplace/product')->getCollection()
						->addFieldToFilter('userid',array('eq'=>$customerId))
						->addFieldToFilter('adminassign',array('eq'=>1));
		$productids=array();
		foreach($coll as $row){
			array_push($productids, $row->getMageproductid());
		}
		if(count($productids))
			$proids= implode(',', $productids);
		else
			$proids= 'none';
		$html='<div class="entry-edit">
					<div class="entry-edit-head">
						<h4 class="icon-head head-customer-view">'.$helper->__('Assign Product To Seller').'</h4>
					</div>
					<fieldset>
						'.$helper->__('Enter Product ID').' : <input name="sellerassignproid" type="text" classs="input-text no-changes" value=""/>	&nbsp;&nbsp;&nbsp;&nbsp;<b>'.$helper->__('Notice: Enter Only Integer value by comma').'(,)</b>
					</fieldset>
					<fieldset>
						'.$helper->__('Assigned Product Ids').' : '.$proids.'
					</fieldset>
				</div>';
		return $html;
	}

	protected function removeproduct(){
		$helper = Mage::helper('marketplace');
		$html='<div class="entry-edit">
					<div class="entry-edit-head">
						<h4 class="icon-head head-customer-view">'.$helper->__('Unassign Product To Seller').'</h4>
					</div>
					<fieldset>
						'.$helper->__('Enter Product ID').' : <input name="sellerunassignproid" type="text" classs="input-text no-changes" value=""/>	&nbsp;&nbsp;&nbsp;&nbsp;<b>'.$helper->__('Notice: Enter Only Integer value by comma').'(,)</b>';
		$html.= '</fieldset></div>';
		return $html;
	}	
	
	protected function wantpartner(){	
		$helper = Mage::helper('marketplace');
		$customerId=Mage::registry('current_customer')->getId();
		$coll=Mage::getModel('marketplace/userprofile')->getCollection();
		$coll->addFieldToFilter('mageuserid',array('eq'=>$customerId));
		$profileurl='';
		foreach($coll as $row){
			$profileurl=$row->getProfileurl();
		}
		return '<div class="entry-edit">
					<div class="entry-edit-head">
						<h4 class="icon-head head-customer-view">'.$helper->__('Do You Want To Make This Customer As Seller?') .'</h4>
					</div>
					<fieldset>
						<input type="checkbox" name="partnertype" value="1">&nbsp;'.$helper->__('Approve Seller') .'<br><br>'.$helper->__('Shop Name') .' : <input type="text" name="profileurl" placeholder="'.$helper->__('Enter Shop Name') .'" value="'.$profileurl.'" class="profileurl"/>
					</fieldset>							
				</div>
				<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
				<script>
					var $wk_jq= jQuery.noConflict();
					$wk_jq(function(){
						$wk_jq(".profileurl").keyup(function(){
							$wk_jq(this).val($wk_jq(this).val().replace(/[^a-z^0-9\.]/g,""));
						});
					});
				</script>';
	}
	
	protected function removepartner(){	
		$helper = Mage::helper('marketplace');
		return '<div class="entry-edit">
					<div class="entry-edit-head">
						<h4 class="icon-head head-customer-view">'.$helper->__('Do You Want To Remove This Seller?') .'</h4>
					</div>
					<fieldset>
						<input type="checkbox" name="partnertype" value="2">&nbsp;'.$helper->__('Unapprove Seller') .'
					</fieldset>							
				</div>';
	}
    protected function _updateActiveTab(){
        $tabId = $this->getRequest()->getParam('tab');
        if( $tabId ) {
            $tabId = preg_replace("#{$this->getId()}_#", '', $tabId);
            if($tabId) {
                $this->setActiveTab($tabId);
            }
        }
    }
}
