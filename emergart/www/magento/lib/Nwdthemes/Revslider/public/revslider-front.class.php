<?php
/**
 * @author    ThemePunch <info@themepunch.com>
 * @link      http://www.themepunch.com/
 * @copyright 2015 ThemePunch
 */
 
class RevSliderFront extends RevSliderBaseFront{
	
	/**
	 * 
	 * the constructor
	 */
	public function __construct(){
		
		parent::__construct($this);
		
		//set table names
		RevSliderGlobals::$table_sliders = self::$table_prefix.RevSliderGlobals::TABLE_SLIDERS_NAME;
		RevSliderGlobals::$table_slides = self::$table_prefix.RevSliderGlobals::TABLE_SLIDES_NAME;
		RevSliderGlobals::$table_static_slides = self::$table_prefix.RevSliderGlobals::TABLE_STATIC_SLIDES_NAME;
		RevSliderGlobals::$table_settings = self::$table_prefix.RevSliderGlobals::TABLE_SETTINGS_NAME;
		RevSliderGlobals::$table_css = self::$table_prefix.RevSliderGlobals::TABLE_CSS_NAME;
		RevSliderGlobals::$table_layer_anims = self::$table_prefix.RevSliderGlobals::TABLE_LAYER_ANIMS_NAME;
		RevSliderGlobals::$table_navigation = self::$table_prefix.RevSliderGlobals::TABLE_NAVIGATION_NAME;
		
		Mage::helper('nwdrevslider/framework')->add_filter('punchfonts_modify_url', array('RevSliderFront', 'modify_punch_url'));
		
		Mage::helper('nwdrevslider/framework')->add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
	}
	
	
	/**
	 * 
	 * a must function. you can not use it, but the function must stay there!
	 */		
	public static function onAddScripts(){
		
		$slver = Mage::helper('nwdrevslider/framework')->apply_filters('revslider_remove_version', RevSliderGlobals::SLIDER_REVISION);
		
		$style_pre = '<style type="text/css">';
		$style_post = '</style>';
		
		$operations = new RevSliderOperations();
		$arrValues = $operations->getGeneralSettingsValues();
		
		$custom_css = RevSliderOperations::getStaticCss();
		$custom_css = RevSliderCssParser::compress_css($custom_css);

		if(trim($custom_css) == '') $custom_css = '#rs-demo-id {}';

		Mage::helper('nwdrevslider/framework')->wp_add_inline_style( 'rs-plugin-settings', $style_pre.$custom_css.$style_post );

        $waitfor = array('jquery');
        
        $enable_logs = RevSliderFunctions::getVal($arrValues, "enable_logs",'off');
        if($enable_logs == 'on'){
            Mage::helper('nwdrevslider/framework')->wp_enqueue_script('enable-logs', Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL .'public/assets/js/jquery.themepunch.enablelog.js', $waitfor, $slver);
            $waitfor[] = 'enable-logs';
		}

		Mage::helper('nwdrevslider/framework')->add_action('wp_head', array('RevSliderFront', 'add_meta_generator'));
		Mage::helper('nwdrevslider/framework')->add_action("wp_footer", array('RevSliderFront',"load_icon_fonts") );

		// Async JS Loading
		$js_defer = RevSliderBase::getVar($arrValues, 'js_defer', 'off');
		if($js_defer!='off') Mage::helper('nwdrevslider/framework')->add_filter('clean_url', array('RevSliderFront', 'add_defer_forscript'), 11, 1);
		
		Mage::helper('nwdrevslider/framework')->add_action('wp_footer', array('RevSliderFront', 'putAdminBarMenus'), 99);
	}

	/**
	 * add admin menu points in ToolBar Top
	 * @since: 5.0.5
	 */
	public static function putAdminBarMenus () {
		if(!Mage::helper('nwdrevslider/framework')->is_super_admin() || !Mage::helper('nwdrevslider/framework')->is_admin_bar_showing()) return;

		?>
		<script>	
			jQuery(document).ready(function() {			
				
				if (jQuery('#wp-admin-bar-revslider-default').length>0 && jQuery('.rev_slider_wrapper').length>0) {
					var aliases = new Array();
					jQuery('.rev_slider_wrapper').each(function() {
						aliases.push(jQuery(this).data('alias'));
					});								
					if(aliases.length>0)
						jQuery('#wp-admin-bar-revslider-default li').each(function() {
							var li = jQuery(this),
								t = jQuery.trim(li.find('.ab-item .rs-label').data('alias')); //text()
								
							if (jQuery.inArray(t,aliases)!=-1) {
							} else {
								li.remove();
							}
						});
				} else {
					jQuery('#wp-admin-bar-revslider').remove();
				}
			});
		</script>
		<?php
	}

	/**
	 * add admin node
	 * @since: 5.0.5
	 */
	public static function _add_node($title, $parent = false, $href = '', $custom_meta = array(), $id = ''){
		global $wp_admin_bar;

		if(!Mage::helper('nwdrevslider/framework')->is_super_admin() || !Mage::helper('nwdrevslider/framework')->is_admin_bar_showing()) return;
		
		if($id == '') $id = strtolower(str_replace(' ', '-', $title));

		// links from the current host will open in the current window
		$meta = strpos( $href, site_url() ) !== false ? array() : array( 'target' => '_blank' ); // external links open in new tab/window
		$meta = array_merge( $meta, $custom_meta );

		$wp_admin_bar->add_node(array(
			'parent' => $parent,
			'id'     => $id,
			'title'  => $title,
			'href'   => $href,
			'meta'   => $meta,
		));
	}
	
	
	/**
	 *
	 * create db tables
	 */
	public static function createDBTables(){
		if(Mage::helper('nwdrevslider/framework')->get_option('revslider_change_database', false) || Mage::helper('nwdrevslider/framework')->get_option('rs_tables_created', false) === false){
			self::createTable(RevSliderGlobals::TABLE_SLIDERS_NAME);
			self::createTable(RevSliderGlobals::TABLE_SLIDES_NAME);
			self::createTable(RevSliderGlobals::TABLE_STATIC_SLIDES_NAME);
			self::createTable(RevSliderGlobals::TABLE_CSS_NAME);
			self::createTable(RevSliderGlobals::TABLE_LAYER_ANIMS_NAME);
			self::createTable(RevSliderGlobals::TABLE_NAVIGATION_NAME);
		}
		Mage::helper('nwdrevslider/framework')->update_option('rs_tables_created', true);
		Mage::helper('nwdrevslider/framework')->update_option('revslider_change_database', false);

		self::updateTables();
	}
	
	public static function load_icon_fonts(){
		global $fa_icon_var,$pe_7s_var;
		if($fa_icon_var) echo "<link rel='stylesheet' property='stylesheet' id='rs-icon-set-fa-icon-css'  href='" . Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL . "public/assets/fonts/font-awesome/css/font-awesome.css' type='text/css' media='all' />";
		if($pe_7s_var) echo "<link rel='stylesheet' property='stylesheet' id='rs-icon-set-pe-7s-css'  href='" . Nwdthemes_Revslider_Helper_Framework::$RS_PLUGIN_URL . "public/assets/fonts/pe-icon-7-stroke/css/pe-icon-7-stroke.css' type='text/css' media='all' />";
	}
	
	public static function updateTables(){
		$cur_ver = Mage::helper('nwdrevslider/framework')->get_option('revslider_table_version', '1.0.0');
        if(Mage::helper('nwdrevslider/framework')->get_option('revslider_change_database', false)){
            $cur_ver = '1.0.0';
        }
        
		if(version_compare($cur_ver, '1.0.1', '<')){ //add missing settings field, for new creates lines in slide editor for example

			$tableName = RevSliderGlobals::TABLE_SLIDES_NAME;
			$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
						  id int(9) NOT NULL AUTO_INCREMENT,
						  slider_id int(9) NOT NULL,
						  slide_order int not NULL,
                          params LONGTEXT NOT NULL,
                          layers LONGTEXT NOT NULL,
						  settings text NOT NULL,
						  UNIQUE KEY id (id)
						);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			
			$tableName = RevSliderGlobals::TABLE_STATIC_SLIDES_NAME;
			$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
						  id int(9) NOT NULL AUTO_INCREMENT,
						  slider_id int(9) NOT NULL,
                          params LONGTEXT NOT NULL,
                          layers LONGTEXT NOT NULL,
						  settings text NOT NULL,
						  UNIQUE KEY id (id)
						);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			
			Mage::helper('nwdrevslider/framework')->update_option('revslider_table_version', '1.0.1');
			$cur_ver = '1.0.1';
		}

		if(version_compare($cur_ver, '1.0.2', '<')){
			$tableName = RevSliderGlobals::TABLE_SLIDERS_NAME;
			
			$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
						  id int(9) NOT NULL AUTO_INCREMENT,
						  title tinytext NOT NULL,
						  alias tinytext,
                          params LONGTEXT NOT NULL,
						  settings text NULL,
						  UNIQUE KEY id (id)
						);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);

			Mage::helper('nwdrevslider/framework')->update_option('revslider_table_version', '1.0.2');
			$cur_ver = '1.0.2';
		}
		
		if(version_compare($cur_ver, '1.0.3', '<')){
			$tableName = RevSliderGlobals::TABLE_CSS_NAME;
			
			$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
						  id int(9) NOT NULL AUTO_INCREMENT,
						  handle TEXT NOT NULL,
                          settings LONGTEXT,
                          hover LONGTEXT,
						  advanced MEDIUMTEXT,
						  params TEXT NOT NULL,
						  UNIQUE KEY id (id)
						);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			
			Mage::helper('nwdrevslider/framework')->update_option('revslider_table_version', '1.0.3');
			$cur_ver = '1.0.3';
		}
		
		if(version_compare($cur_ver, '1.0.4', '<')){

			$sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_SLIDERS_NAME ." (
					  UNIQUE KEY id (id)
					);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			$sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_SLIDES_NAME ." (
					  UNIQUE KEY id (id)
					);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			$sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_STATIC_SLIDES_NAME ." (
					  UNIQUE KEY id (id)
					);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			$sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_CSS_NAME ." (
					  UNIQUE KEY id (id)
					);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			$sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_LAYER_ANIMS_NAME ." (
					  UNIQUE KEY id (id)
					);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			
			Mage::helper('nwdrevslider/framework')->update_option('revslider_table_version', '1.0.4');
			$cur_ver = '1.0.4';
		}
		
		if(version_compare($cur_ver, '1.0.5', '<')){

            $sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_LAYER_ANIMS_NAME ." (
                      settings text NULL
                    );";
            Mage::helper('nwdrevslider/query')->dbDelta($sql);
            
            Mage::helper('nwdrevslider/framework')->update_option('revslider_table_version', '1.0.5');
            $cur_ver = '1.0.5';
        }
        
        if(version_compare($cur_ver, '1.0.6', '<')){
			$sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_SLIDERS_NAME ." (
                     type VARCHAR(191) NOT NULL DEFAULT '',
                     params LONGTEXT NOT NULL
					);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			$sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_SLIDES_NAME ." (
                      settings text NOT NULL DEFAULT '',
                      params LONGTEXT NOT NULL,
                      layers LONGTEXT NOT NULL
					);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			$sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_STATIC_SLIDES_NAME ." (
                      params LONGTEXT NOT NULL,
                      layers LONGTEXT NOT NULL
					);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
            $sql = "CREATE TABLE " .self::$table_prefix.RevSliderGlobals::TABLE_CSS_NAME ." (
                      advanced LONGTEXT
					);";
			Mage::helper('nwdrevslider/query')->dbDelta($sql);
			
            Mage::helper('nwdrevslider/framework')->update_option('revslider_table_version', '1.0.6');
            $cur_ver = '1.0.6';
		}

	}
	
	
	/**
	 * create tables
	 */
	public static function createTable($tableName){
		$wpdb = Mage::helper('nwdrevslider/query');

		$parseCssToDb = false;

		$checkForTablesOneTime = Mage::helper('nwdrevslider/framework')->get_option('revslider_checktables', '0');
		
		if($checkForTablesOneTime == '0'){
			Mage::helper('nwdrevslider/framework')->update_option('revslider_checktables', '1');
			if(RevSliderFunctionsWP::isDBTableExists(self::$table_prefix.RevSliderGlobals::TABLE_CSS_NAME)){ //$wpdb->tables( 'global' )
				//check if database is empty
				$result = $wpdb->get_row("SELECT COUNT( DISTINCT id ) AS NumberOfEntrys FROM ".self::$table_prefix.RevSliderGlobals::TABLE_CSS_NAME);
				if($result->NumberOfEntrys == 0) $parseCssToDb = true;
			}
		}

		if($parseCssToDb){
			$RevSliderOperations = new RevSliderOperations();
			$RevSliderOperations->importCaptionsCssContentArray();
			$RevSliderOperations->moveOldCaptionsCss();
		}
        
        if(!Mage::helper('nwdrevslider/framework')->get_option('revslider_change_database', false)){
            //if table exists - don't create it.
            $tableRealName = self::$table_prefix.$tableName;
            if(RevSliderFunctionsWP::isDBTableExists($tableRealName))
                return(false);
        }
        
		switch($tableName){
			case RevSliderGlobals::TABLE_SLIDERS_NAME:
			$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
						  id int(9) NOT NULL AUTO_INCREMENT,
						  title tinytext NOT NULL,
						  alias tinytext,
                          params LONGTEXT NOT NULL,
						  UNIQUE KEY id (id)
						);";
			break;
			case RevSliderGlobals::TABLE_SLIDES_NAME:
				$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
							  id int(9) NOT NULL AUTO_INCREMENT,
							  slider_id int(9) NOT NULL,
							  slide_order int not NULL,
                              params LONGTEXT NOT NULL,
                              layers LONGTEXT NOT NULL,
							  UNIQUE KEY id (id)
							);";
			break;
			case RevSliderGlobals::TABLE_STATIC_SLIDES_NAME:
				$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
							  id int(9) NOT NULL AUTO_INCREMENT,
							  slider_id int(9) NOT NULL,
                              params LONGTEXT NOT NULL,
                              layers LONGTEXT NOT NULL,
							  UNIQUE KEY id (id)
							);";
			break;
			case RevSliderGlobals::TABLE_CSS_NAME:
				$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
							  id int(9) NOT NULL AUTO_INCREMENT,
							  handle TEXT NOT NULL,
                              settings LONGTEXT,
                              hover LONGTEXT,
                              params LONGTEXT NOT NULL,
							  UNIQUE KEY id (id)
							);";
				$parseCssToDb = true;
			break;
			case RevSliderGlobals::TABLE_LAYER_ANIMS_NAME:
				$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
							  id int(9) NOT NULL AUTO_INCREMENT,
							  handle TEXT NOT NULL,
							  params TEXT NOT NULL,
							  UNIQUE KEY id (id)
							);";
			break;
			case RevSliderGlobals::TABLE_NAVIGATION_NAME:
				$sql = "CREATE TABLE " .self::$table_prefix.$tableName ." (
							  id int(9) NOT NULL AUTO_INCREMENT,
							  name VARCHAR(191) NOT NULL,
							  handle VARCHAR(191) NOT NULL,
                              css LONGTEXT NOT NULL,
                              markup LONGTEXT NOT NULL,
                              settings LONGTEXT NULL,
							  UNIQUE KEY id (id)
							);";
			break;
			default:
				RevSliderFunctions::throwError("table: $tableName not found");
			break;
		}
		
		Mage::helper('nwdrevslider/query')->dbDelta($sql);
		
        if(!Mage::helper('nwdrevslider/framework')->get_option('revslider_change_database', false)){
            if($parseCssToDb){
                $RevSliderOperations = new RevSliderOperations();
                $RevSliderOperations->importCaptionsCssContentArray();
                $RevSliderOperations->moveOldCaptionsCss();
            }
		}

	}
	
	
	
	public function enqueue_styles(){
		
	}
	
	
	/**
	 * Change FontURL to new URL (added for chinese support since google is blocked there)
	 * @since: 5.0
	 */
	public static function modify_punch_url($url){
		$operations = new RevSliderOperations();
		$arrValues = $operations->getGeneralSettingsValues();
		
		$set_diff_font = RevSliderFunctions::getVal($arrValues, "change_font_loading",'');
		if($set_diff_font !== ''){
			return $set_diff_font;
		}else{
			return $url;
		}
	}
	
	
	/**
	 * Add Meta Generator Tag in FrontEnd
	 * @since: 5.0
	 */
	public static function add_meta_generator(){
		$revSliderVersion = RevSliderGlobals::SLIDER_REVISION;
		
		echo Mage::helper('nwdrevslider/framework')->apply_filters('revslider_meta_generator', '<meta name="generator" content="Powered by Slider Revolution '.$revSliderVersion.' - responsive, Mobile-Friendly Slider Plugin for WordPress with comfortable drag and drop interface." />'."\n");
	}

	/**
	 *
	 * adds async loading
	 * @since: 5.0
	 */
	public static function add_defer_forscript($url)
	{
	    if ( strpos($url, 'themepunch.enablelog.js' )===false && strpos($url, 'themepunch.revolution.min.js' )===false  && strpos($url, 'themepunch.tools.min.js' )===false )
	        return $url;
	    else if (Mage::helper('nwdrevslider/framework')->is_admin())
	        return $url;
	    else
	        return $url."' defer='defer"; 
	}
	
}