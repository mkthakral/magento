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

class Nwdthemes_Revslider_Block_Adminhtml_Slider_Overview extends Nwdthemes_Revslider_Block_Adminhtml_Block_Template {

	/**
	 * Constructor
	 */

	public function __construct() {

		parent::__construct();

		$orders = false;
		//order=asc&ot=name&type=reg
		if(isset(Nwdthemes_Revslider_Helper_Data::$_GET['ot']) && isset(Nwdthemes_Revslider_Helper_Data::$_GET['order']) && isset(Nwdthemes_Revslider_Helper_Data::$_GET['type'])){
			$order = array();
			switch(Nwdthemes_Revslider_Helper_Data::$_GET['ot']){
				case 'alias':
					$order['alias'] = (Nwdthemes_Revslider_Helper_Data::$_GET['order'] == 'asc') ? 'ASC' : 'DESC';
				break;
				case 'favorite':
					$order['favorite'] = (Nwdthemes_Revslider_Helper_Data::$_GET['order'] == 'asc') ? 'ASC' : 'DESC';
				break;
				case 'name':
				default:
					$order['title'] = (Nwdthemes_Revslider_Helper_Data::$_GET['order'] == 'asc') ? 'ASC' : 'DESC';
				break;
			}

			$orders = $order;
		}

		$slider = new RevSlider();
        $operations = new RevSliderOperations();
		$arrSliders = $slider->getArrSliders($orders);
        $glob_vals = $operations->getGeneralSettingsValues();

		$addNewLink = self::getViewUrl(RevSliderAdmin::VIEW_SLIDER);


		$fav = Mage::helper('nwdrevslider/framework')->get_option('rev_fav_slider', array());
		if($orders == false){ //sort the favs to top
			if(!empty($fav) && !empty($arrSliders)){
				$fav_sort = array();
				foreach($arrSliders as $skey => $sort_slider){
					if(in_array($sort_slider->getID(), $fav)){
						$fav_sort[] = $arrSliders[$skey];
						unset($arrSliders[$skey]);
					}
				}
				if(!empty($fav_sort)){
					//revert order of favs
					krsort($fav_sort);
					foreach($fav_sort as $fav_arr){
						array_unshift($arrSliders, $fav_arr);
					}
				}
			}
		}

		$exampleID = '"slider1"';
		if(!empty($arrSliders))
			$exampleID = '"'.$arrSliders[0]->getAlias().'"';
		
		$latest_version = Mage::helper('nwdrevslider/framework')->get_option('revslider-latest-version', RevSliderGlobals::SLIDER_REVISION);
		$stable_version = Mage::helper('nwdrevslider/framework')->get_option('revslider-stable-version', '4.1');

        $this->assign([
            'slider' => $slider,
            'arrSliders' => $arrSliders,
            'addNewLink' => $addNewLink,
            'exampleID' => $exampleID,
            'latest_version' => $latest_version,
            'stable_version' => $stable_version,
            'operations' => $operations,
            'glob_vals' => $glob_vals
        ]);
	}
}
