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

class Nwdthemes_Revslider_Model_Observer {

    public function setHandle(Varien_Event_Observer $observer) {

		if ( Mage::helper('nwdall')->getCfg('general/enabled', 'nwdrevslider_config') ) {

			$settings = unserialize( Mage::getModel('nwdrevslider/options')->getOption('revslider-global-settings') );
			$includeSlider = isset($settings['includes_globally']) && $settings['includes_globally'] == 'on';

			if ( ! $includeSlider) {
				$fullActionName = $observer->getEvent()->getAction()->getFullActionName();
				$arrHandles = explode(',', isset($settings['pages_for_includes']) ? $settings['pages_for_includes'] : '');
				foreach ($arrHandles as $_handle) {
					if (trim($_handle) == $fullActionName) {
						$includeSlider = true;
					}
				}
			}

			if ($includeSlider) {

			    $layoutUpdate  = Mage::app()->getLayout()->getUpdate();
                $layoutUpdate->addHandle('nwdrevslider_default');

                if (isset($settings['load_all_javascript']) && $settings['load_all_javascript'] !== 'off') {
                    $layoutUpdate->addHandle('nwdrevslider_load_all_javascript');
                }
			}
		}
    }

}
