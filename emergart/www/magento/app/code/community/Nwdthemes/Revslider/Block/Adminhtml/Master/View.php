<?php

/**
 * Nwdthemes Revolution Slider Extension
 *
 * @package     Revslider
 * @author		Nwdthemes <mail@nwdthemes.com>
 * @link		http://nwdthemes.com/
 * @copyright   Copyright (c) 2014. Nwdthemes
 * @license     http://themeforest.net/licenses/terms/regular
 */

class Nwdthemes_Revslider_Block_Adminhtml_Master_View extends Mage_Adminhtml_Block_Template
{
	public function __construct() {

		parent::__construct();

		$revSliderVersion = RevSliderGlobals::SLIDER_REVISION;

		$wrapperClass = "";
		if(RevSliderGlobals::$isNewVersion == false)
			$wrapperClass = " oldwp";

        $wrapperClass = Mage::helper('nwdrevslider/framework')->apply_filters( 'rev_overview_wrapper_class_filter', $wrapperClass );

        $nonce = Mage::helper('nwdrevslider/framework')->wp_create_nonce("revslider_actions");

		$rsop = new RevSliderOperations();
		$glval = $rsop->getGeneralSettingsValues();

		$waitstyle = '';
		if(isset(Nwdthemes_Revslider_Helper_Data::$_REQUEST['update_shop'])){
			$waitstyle = 'display:block';
		}

        $operations = new RevSliderOperations();
        $glob_vals = $operations->getGeneralSettingsValues();
        $pack_page_creation = RevSliderFunctions::getVal($glob_vals, "pack_page_creation", "on");
        $single_page_creation = RevSliderFunctions::getVal($glob_vals, "single_page_creation", "off");

        $this->assign([
            'revSliderVersion'      => $revSliderVersion,
            'rsop'                  => $rsop,
            'wrapperClass'          => $wrapperClass,
            'nonce'                 => $nonce,
            'glval'                 => $glval,
            'waitstyle'             => $waitstyle,
            'operations'            => $operations,
            'glob_vals'             => $glob_vals,
            'pack_page_creation'    => $pack_page_creation,
            'single_page_creation'  => $single_page_creation
        ]);
	}
	
}
