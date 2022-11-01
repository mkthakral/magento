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

class Nwdthemes_Revslider_Helper_Data extends Mage_Core_Helper_Abstract {

    const REVSLIDER_PRODUCT = 'revslider_magento';
    const ASSETS_ROUTE = 'nwdthemes/revslider/public/assets/';

    public static $_GET = array();
    public static $_REQUEST = array();

	/**
	 *	Constructor
	 */

	public function __construct() {
        $requestParams = Mage::app()->getRequest()->getParams();
        self::$_GET = array_merge(self::$_GET, $requestParams);
        self::$_REQUEST = array_merge(self::$_REQUEST, $requestParams);
	}

    /**
     *  Set page for get imitation
     *
     *  @param  string  $page
     */

    public static function setPage($page) {
        self::$_GET['page'] = $page;
    }

    /**
     *  Set page for get imitation
     *
     *  @param  string  $view
     */

    public static function setView($view) {
        self::$_GET['view'] = $view;
    }
	
    /**
     * This function can autoloads classes
     *
     * @param string $class
     */

    public static function loadRevClasses($class) {
		switch ($class) {
			case 'RevSliderFunctions' :	$class = 'RevSliderFunctions'; break;
			case 'RevSlider' : 			$class = 'RevSliderSlider'; break;
			case 'RevSlide' : 			$class = 'RevSliderSlide'; break;
		}
		switch ($class) {
            case 'RevSliderLoadBalancer' :	$classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/framework/loadbalancer.class.php'; break;
            case 'TPColorpicker' :	        $classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/framework/colorpicker.class.php'; break;
            case 'Rev_addon_Admin' :	    $classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/framework/addon-admin.class.php'; break;
            case 'RevSliderEventsManager' :	$classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/framework/em-integration.class.php'; break;
            case 'RevSliderCssParser' :		$classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/framework/cssparser.class.php'; break;
			case 'RevSliderWooCommerce' :	$classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/framework/woocommerce.class.php'; break;
			case 'RevSliderAdmin' : 		$classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/admin/revslider-admin.class.php'; break;
			case 'RevSliderFront' :			$classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/public/revslider-front.class.php'; break;
			case 'RevSliderFacebook' :
			case 'RevSliderTwitter' :
			case 'RevSliderTwitterApi' :
			case 'RevSliderInstagram' :
			case 'RevSliderFlickr' :
			case 'RevSliderYoutube' :
			case 'RevSliderVimeo' :			$classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/external-sources.class.php'; break;
			default:
				if (preg_match( '#^RevSlider#', $class)) {
					$className = str_replace(array('RevSlider', 'WP'), array('', 'Wordpress'), $class);
					preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $className, $matches);
					$ret = $matches[0];
					foreach ($ret as &$match) {
						$match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
					}
					$className = implode('-', $ret);
					$classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/framework/' . $className . '.class.php';
					if ( ! file_exists($classFile)) {
						$classFile = Mage::getBaseDir('lib') . '/Nwdthemes/Revslider/' . $className . '.class.php';
					}
					if ( ! file_exists($classFile)) {
						unset($classFile);
					}
				}
			break;
		}
		if (isset($classFile)) {
			require_once($classFile);
		}
    }

	/**
	 * Get store options for multiselect
	 *
	 * @return array Array of store options
	 */

	public function getStoreOptions() {
		$storeValues = Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true);
		$storeValues = $this->_makeFlatStoreOptions($storeValues);
		return $storeValues;
	}

	/**
	 * Make flat store options
	 *
	 * @param array $storeValues Store values tree array
	 * @retrun array Flat store values array
	 */

	private function _makeFlatStoreOptions($storeValues) {
		$arrStoreValues = array();
		foreach ($storeValues as $_storeValue) {
			if ( ! is_array($_storeValue['value']) ) {
				$arrStoreValues[] = $_storeValue;
			} else {
				$arrStoreValues[] = array(
					'label'	=> $_storeValue['label'],
					'value' => 'option_disabled'
				);
				$_arrSubStoreValues = $this->_makeFlatStoreOptions($_storeValue['value']);
				foreach ($_arrSubStoreValues as $_subStoreValue) {
					$arrStoreValues[] = $_subStoreValue;
				}
			}
		}
		return $arrStoreValues;
	}

    /**
     * Log exception details to nwd_revslider.log
     *
     * @param Exception $e
     */
    public function logException($e) {
        $trace = array();
        foreach ($e->getTrace() as $data) if (isset($data['file'])) {
            $trace[] = $data['file'].':'.$data['line'];
        }
        $this->log('Revolution Slider Exception: ' . $e->getMessage() . ' in ' .  $e->getFile() . ' on line ' . $e->getLine(), $trace);
    }

    /**
     * Log variable to nwd_revslider.log
     *
     * @param var $var
     */

    public function log($var) {
        $log = array();
        foreach (func_get_args() as $arg)
            $log[] = is_string($arg) ? $arg : (is_bool($arg) ? var_export($arg, true) : print_r($arg, true));
        Mage::log(implode(', ', $log), null, 'nwd_revslider.log');
    }



    /**
     *
     *  Url helper functions
     *
     */



    /**
     *	Convert assets url for frontend
     *
     *	@param  string  Handle
     *	@param  array   Params
     *	@return	string
     */
    public function convertAssetUrlForOutput($url) {
        if (strpos($url, self::ASSETS_ROUTE) !== false) {
            $urlParts = explode(self::ASSETS_ROUTE, $url);
            $urlFile = isset($urlParts[1]) ? $urlParts[1] : '';
            $assetUrl = $this->getAssetUrl(ltrim($urlFile, '/'));
        } else {
            $assetUrl = $this->forceSSL($url);
        }
        return $assetUrl;
    }

    /**
     *	Get Asset Url
     *
     *	@param  string  Handle
     *	@param  array   Params
     *	@return	string
     */
    public function getAssetUrl($handle = '', $params = array()) {
        $_params = array('_theme' => 'default');
        $_params = array_merge($_params, $params);
        $_handle = self::ASSETS_ROUTE . $handle;
        return Mage::getDesign()->getSkinUrl($_handle, $_params);
    }

    /**
     *	Force ssl on urls
     *
     *	@param	string
     *	@return	string
     */
    public function forceSSL($url) {
        if (Mage::app()->getStore()->isCurrentlySecure()) {
            $url = str_replace('http://', 'https://', $url);
        }
        return $url;
    }




    /**
     * Get styles to output in front end head
     *
     * @return string
     */

    public function getStaticStyles() {
        spl_autoload_register(array(Mage::helper('nwdrevslider'), 'loadRevClasses'), true, true);
        return'<style type="text/css">'
            . RevSliderCssParser::compress_css(RevSliderOperations::getStaticCss())
            . '</style>';
    }

}
