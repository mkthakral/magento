<?php
/**
 * @author    ThemePunch <info@themepunch.com>
 * @link      http://www.themepunch.com/
 * @copyright 2015 ThemePunch
 */

class RevSliderBaseAdmin extends RevSliderBase {
	
	protected static $master_view;
	protected static $view;
	
	private static $arrSettings = array();
	private static $tempVars = array();
	private static $startupError = '';

    private static $allowed_views = array('master-view', 'system/validation', 'system/dialog-video', 'system/dialog-update', 'system/dialog-global-settings', 'sliders', 'slider', 'slider_template', 'slides', 'slide', 'navigation-editor', 'slide-editor', 'slide-overview', 'slide-editor', 'slider-overview', 'themepunch-google-fonts', 'global-settings');
	
	/**
	 * 
	 * main constructor		 
	 */
	public function __construct($t){
		
		parent::__construct($t);
		
		//set view
		self::$view = self::getGetVar("view");
		if(empty(self::$view))
			self::$view = 'sliders';
	}
	

	/**
	 * 
	 * set startup error to be shown in master view
	 */
	public static function setStartupError($errorMessage){
		self::$startupError = $errorMessage;
	}

	/**
     * add global used scripts
     * @since: 5.1.1
     */
    public static function addGlobalScripts(){
        Mage::helper('nwdrevslider/framework')->wp_enqueue_script(array('jquery', 'jquery-ui-core', 'jquery-ui-sortable', 'wpdialogs'));
        Mage::helper('nwdrevslider/framework')->wp_enqueue_style(array('wp-jquery-ui', 'wp-jquery-ui-dialog', 'wp-jquery-ui-core'));
    }


    /**
	 * add common used scripts
	 */
	public static function addCommonScripts(){
		
		if(function_exists("wp_enqueue_media"))
			wp_enqueue_media();
		
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script(array('jquery', 'jquery-ui-core', 'jquery-ui-mouse', 'jquery-ui-accordion', 'jquery-ui-datepicker', 'jquery-ui-dialog', 'jquery-ui-slider', 'jquery-ui-autocomplete', 'jquery-ui-sortable', 'jquery-ui-droppable', 'jquery-ui-tabs', 'jquery-ui-widget', 'wp-color-picker'));
		
		Mage::helper('nwdrevslider/framework')->wp_enqueue_style(array('wp-jquery-ui', 'wp-jquery-ui-core', 'wp-jquery-ui-dialog', 'wp-color-picker'));
		
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('unite_settings', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/js/settings.js', array(), RevSliderGlobals::SLIDER_REVISION );
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('unite_admin', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/js/admin.js', array(), RevSliderGlobals::SLIDER_REVISION );
		
		Mage::helper('nwdrevslider/framework')->wp_enqueue_style('unite_admin', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/css/admin.css', array(), RevSliderGlobals::SLIDER_REVISION);
		
		//add tipsy
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('tipsy', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/js/jquery.tipsy.js', array(), RevSliderGlobals::SLIDER_REVISION );
		Mage::helper('nwdrevslider/framework')->wp_enqueue_style('tipsy', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/css/tipsy.css', array(), RevSliderGlobals::SLIDER_REVISION);
		
		//include codemirror
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('codemirror_js', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/js/codemirror/codemirror.js', array(), RevSliderGlobals::SLIDER_REVISION );
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('codemirror_js_highlight', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/js/codemirror/util/match-highlighter.js', array(), RevSliderGlobals::SLIDER_REVISION );
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('codemirror_js_searchcursor', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/js/codemirror/util/searchcursor.js', array(), RevSliderGlobals::SLIDER_REVISION );
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('codemirror_js_css', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/js/codemirror/css.js', array(), RevSliderGlobals::SLIDER_REVISION );
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('codemirror_js_html', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/js/codemirror/xml.js', array(), RevSliderGlobals::SLIDER_REVISION );
		Mage::helper('nwdrevslider/framework')->wp_enqueue_style('codemirror_css', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'admin/assets/js/codemirror/codemirror.css', array(), RevSliderGlobals::SLIDER_REVISION);
		
	}

	
	/**
	 * 
	 * validate permission that the user is admin, and can manage options.
	 */
	protected static function isAdminPermissions(){
		
		if( Mage::helper('nwdrevslider/framework')->is_admin() && Mage::helper('nwdrevslider/framework')->current_user_can("manage_options") )
			return(true);
			
		return(false);
	}
	
	/**
	 * 
	 * validate admin permissions, if no pemissions - exit
	 */
	protected static function validateAdminPermissions(){
		if(!self::isAdminPermissions()){
			echo "access denied";
			return(false);
		}			
	}
	
	/**
	 * 
	 * set view that will be the master
	 */
	protected static function setMasterView($masterView){
		self::$master_view = $masterView;
	}
	
	/**
	 * 
	 * inlcude some view file
	 */
	protected static function requireView($view){
		try{
			//require master view file, and 
			if(!empty(self::$master_view) && !isset(self::$tempVars["is_masterView"]) ){
				$masterViewFilepath = self::$path_views.self::$master_view.".php";
				RevSliderFunctions::validateFilepath($masterViewFilepath,"Master View");
				
				self::$tempVars["is_masterView"] = true;
				require $masterViewFilepath;
			}else{		//simple require the view file.
				if(!in_array($view, self::$allowed_views)) RevSliderFunctions::throwError(__('Wrong Request'));
				
				switch($view){ //switch URLs to corresponding php files
					case 'slide':
						$view = 'slide-editor';
					break;
					case 'slider':
						$view = 'slider-editor';
					break;
					case 'sliders':
						$view = 'slider-overview';
					break;
					case 'slides':
						$view = 'slide-overview';
					break;
				}
				
				$viewFilepath = self::$path_views.$view.".php";
				
				RevSliderFunctions::validateFilepath($viewFilepath,"View");
				require $viewFilepath;
			}
			
		}catch (Exception $e){
            Mage::helper('nwdrevslider')->logException($e);
			echo "<br><br>View (".Mage::helper('nwdrevslider/framework')->esc_attr($view).") Error: <b>".Mage::helper('nwdrevslider/framework')->esc_attr($e->getMessage())."</b>";
		}
	}
	
	/**
	 * require some template from "templates" folder
	 */
	protected static function getPathTemplate($templateName){
		$pathTemplate = self::$path_templates.$templateName.'.php';
		RevSliderFunctions::validateFilepath($pathTemplate,'Template');
		
		return($pathTemplate);
	}
	
	
	/**
	 * 
	 * add all js and css needed for media upload
	 */
	protected static function addMediaUploadIncludes(){
		
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('thickbox');
		Mage::helper('nwdrevslider/framework')->wp_enqueue_script('media-upload');
		Mage::helper('nwdrevslider/framework')->wp_enqueue_style('thickbox');
		
	}

	/**
	 * 
	 * get url to some view.
	 */
	public static function getViewUrl($viewName,$urlParams=""){
		$params = "";
		if(!empty($urlParams))
			$params .= "?".$urlParams;

		$link = Mage::helper('nwdrevslider/framework')->admin_url( 'adminhtml/nwdrevslider/' . $viewName) . $params;
		return($link);
	}
	
	/**
	 * 
	 * register the "onActivate" event
	 */
	protected function addEvent_onActivate($eventFunc = "onActivate"){
		Mage::helper('nwdrevslider/framework')->register_activation_hook( RS_PLUGIN_FILE_PATH, array(self::$t, $eventFunc) );
	}
	
	
	protected function addAction_onActivate(){
		Mage::helper('nwdrevslider/framework')->register_activation_hook( RS_PLUGIN_FILE_PATH, array(self::$t, 'onActivateHook') );
	}
	
	
	public static function onActivateHook(){
		
		$options = array();
		
		$options = Mage::helper('nwdrevslider/framework')->apply_filters('revslider_mod_activation_option', $options);
		
		$operations = new RevSliderOperations();
        $options_exist = $operations->getGeneralSettingsValues();
		if(!is_array($options_exist)) $options_exist = array();
		
        $options = array_merge($options_exist, $options);

        $operations->updateGeneralSettings($options);
		
	}
	
	
	/**
	 * 
	 * store settings in the object
	 */
	protected static function storeSettings($key,$settings){
		self::$arrSettings[$key] = $settings;
	}
	
	
	/**
	 * 
	 * get settings object
	 */
	protected static function getSettings($key){
		if(!isset(self::$arrSettings[$key]))
			RevSliderFunctions::throwError("Settings $key not found");
		$settings = self::$arrSettings[$key];
		return($settings);
	}
	
	
	/**
	 * 
	 * add ajax back end callback, on some action to some function.
	 */
	protected static function addActionAjax($ajaxAction,$eventFunction){
		Mage::helper('nwdrevslider/framework')->add_action('wp_ajax_revslider_'.$ajaxAction, array('RevSliderAdmin', $eventFunction));
	}
	
	
	/**
	 * 
	 * echo json ajax response
	 */
	private static function ajaxResponse($success,$message,$arrData = null){
		
		$response = array();			
		$response["success"] = $success;				
		$response["message"] = $message;
		
		if(!empty($arrData)){
			
			if(gettype($arrData) == "string")
				$arrData = array("data"=>$arrData);				
			
			$response = array_merge($response,$arrData);
		}
			
		$json = json_encode($response);
		
		echo $json;
		exit();
	}

	
	/**
	 * 
	 * echo json ajax response, without message, only data
	 */
	protected static function ajaxResponseData($arrData){
		if(gettype($arrData) == "string")
			$arrData = array("data"=>$arrData);
		
		self::ajaxResponse(true,"",$arrData);
	}
	
	
	/**
	 * 
	 * echo json ajax response
	 */
	protected static function ajaxResponseError($message,$arrData = null){
		
		self::ajaxResponse(false,$message,$arrData,true);
	}
	
	
	/**
	 * echo ajax success response
	 */
	protected static function ajaxResponseSuccess($message,$arrData = null){
		
		self::ajaxResponse(true,$message,$arrData,true);
		
	}
	
	
	/**
	 * echo ajax success response
	 */
	protected static function ajaxResponseSuccessRedirect($message,$url){
		$arrData = array("is_redirect"=>true,"redirect_url"=>$url);
		
		self::ajaxResponse(true,$message,$arrData,true);
	}
	

}

/**
 * old classname extends new one (old classnames will be obsolete soon)
 * @since: 5.0
 **/
class UniteBaseAdminClassRev extends RevSliderBaseAdmin {}