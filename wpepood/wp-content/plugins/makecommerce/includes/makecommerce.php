<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://makecommerce.net/
 * @since      3.0.0
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      3.0.0
 * @package    Makecommerce
 * @subpackage Makecommerce/includes
 * @author     Maksekeskus AS <support@maksekeskus.ee>
 */
class MakeCommerce {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    3.0.0
	 * @access   protected
	 * @var      MakeCommerce\Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    3.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    3.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    3.0.0
	 */
	public function __construct() {

		$this->version = MAKECOMMERCE_VERSION;

		$this->plugin_name = 'makecommerce';

		//configure autoloader
		require_once plugin_dir_path( __FILE__ ) . "autoloader.php";

		$autoLoader = new MakeCommerce\Autoloader;
		$autoLoader->register();

		//register includes and the complete plugin path as MakeCommerce namespace
		$autoLoader->addNamespace( 'MakeCommerce', __DIR__ );
		$autoLoader->addNamespace( 'MakeCommerce', plugin_dir_path( __DIR__ ) );

		//get Maksekeskus API
		require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
		require_once plugin_dir_path( __FILE__ ) . 'vendor/Maksekeskus.php';

		$this->loader = new MakeCommerce\Loader();

		$this->set_locale();

		$plugin_payment = new MakeCommerce\Payment ( $this->get_plugin_name(), $this->get_version(), $this->loader );
		
		$this->define_api_hooks();
		$this->define_shipping_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Makecommerce_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    3.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new MakeCommerce\i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to cron functionality
	 * of the plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 */
	private function define_cron_hooks() {

		$plugin_cron = new MakeCommerce\Cron( $this->get_plugin_name(), $this->get_version() );

		//cron define query vars
		$this->loader->add_filter( 'query_vars', $plugin_cron, 'query_vars' );

		// WP schedule callable action
		$this->loader->add_action( 'mc_banklinks_update_cron', $plugin_cron, 'update_banklinks' );

		$this->loader->add_action( 'parse_request', $plugin_cron, 'parse_update_vars' );
	}

	/**
	 * Register all of the hooks related to the api functionality
	 * of the plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 */
	private function define_api_hooks() {

		$api = new MakeCommerce\API( $this->get_plugin_name(), $this->get_version(), $this->loader );
		
		//check if CURL is installed
		$this->loader->add_action( 'admin_notices',	$api, 'check_if_curl_is_loaded');

		//Add admin notice if api is not set up
        if ( !\MakeCommerce::get_api() ) {
            $this->loader->add_action( 'admin_notices', $api, 'api_info_missing' );
        }

		//if API type is updated then refresh the cache
		$this->loader->add_action( 'update_option_mk_api_type', $api, 'mk_delete_api_cache' );
		
		//add API access menu in woocommerce->settings->advanced (_api version is for WC <= 3.4.0)
		$this->loader->add_action( 'woocommerce_get_sections_advanced', $api, 'add_woo_menu' );
		$this->loader->add_action( 'woocommerce_get_sections_api', $api, 'add_woo_menu');

		//add woocommerce admin menu settings for woocommerce->settings->advanced->mk_api (_api version is for WC <= 3.4.0)
		$this->loader->add_filter( 'woocommerce_get_settings_advanced', $api, 'add_woo_admin_settings', 10, 2 );
		$this->loader->add_filter( 'woocommerce_get_settings_api', $api, 'add_woo_admin_settings', 10, 2 );

		//add new customer column to orders view, make it sortable and fill with values
		$this->loader->add_filter( 'manage_edit-shop_order_columns', $api, 'add_ordersview_paymentmethod_column' );
		$this->loader->add_filter( 'manage_edit-shop_order_sortable_columns', $api, 'make_ordersview_paymentmethod_column_sortable' );
		$this->loader->add_action( 'manage_shop_order_posts_custom_column', $api, 'fill_ordersview_paymentmethod_column', 10, 2 );

		//various hooks that update banklinks
		$this->loader->add_action( 'wp_login', $api, 'admin_login', 10, 2 );
		$this->loader->add_action( 'woocommerce_settings_saved', $api, 'save_settings', 30, 0 );

		//adds settings link to plugins page
		$this->loader->add_filter( 'plugin_action_links_makecommerce/makecommerce.php', $api, 'add_plugin_settings_link' );

		$this->loader->add_filter( 'woocommerce_admin_field_api_javascript_ui', $api, 'api_javascript_ui' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 */
	private function define_shipping_hooks() {

		$plugin_shipping = new MakeCommerce\Shipping( $this->get_plugin_name(), $this->get_version(), $this->loader );

		//load scripts and styles
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_shipping, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_shipping, 'enqueue_scripts' );

		//load scripts and styles
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_shipping, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_shipping, 'enqueue_scripts' );

		//Initialize shipping
		$this->loader->add_action( 'woocommerce_shipping_init', $plugin_shipping, 'initalize' );

		//add shipping methods
		$this->loader->add_filter( 'woocommerce_shipping_methods', $plugin_shipping, 'add_shipping_methods' );

		$this->loader->add_filter( 'woocommerce_checkout_update_order_review', $plugin_shipping, 'clear_shipping_rates_cache' );
		$this->loader->add_filter( 'posts_where', $plugin_shipping, 'shipping_filter', 10, 2 );
		
		$this->loader->add_filter( 'woocommerce_order_status_processing', $plugin_shipping, 'register_shipment' );
		
		$this->loader->add_action( 'woocommerce_update_order', $plugin_shipping, 'set_parcel_machine_meta' );
		$this->loader->add_action( 'woocommerce_update_order', $plugin_shipping, 'update_courier_meta' );

		$this->loader->add_action( 'wp_ajax_update_map_center', $plugin_shipping, 'update_map_center' );
		$this->loader->add_action( 'wp_ajax_nopriv_update_map_center', $plugin_shipping, 'update_map_center' );

		$this->loader->add_action( 'update_option_mc_map_geocoding_api_key', $plugin_shipping, 'mc_check_api_key' );
		$this->loader->add_action( 'admin_notices', $plugin_shipping, 'invalid_api_key_notification' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    3.0.0
	 */
	public function run() {

		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     3.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {

		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     3.0.0
	 * @return    Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {

		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     3.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {

		return $this->version;
	}

	/**
    * Get the HTML version of the logo
    * 
    * @since     3.0.0
    */
	public static function get_logo_html() {

		return '
			<div class="makecommerce-info">
				<div class="makecommerce-logo">
					<a target="_blank" href="http://maksekeskus.ee"><img src="'. plugins_url( '../payment/gateway/woocommerce/images/makecommerce_logo_en.svg', __FILE__ ) .'" class="makecommerce-logo"></a>
				</div>
				
				<div class="makecommerce-links">
					<div class="makecommerce-link"><a target="_blank" href="https://merchant.maksekeskus.ee">Merchant Portal</a></div>
					<div class="makecommerce-link"><a target="_blank" href="https://makecommerce.net/">makecommerce.net</a></div>
					<div class="makecommerce-link"><a target="_blank" href="http://maksekeskus.ee">maksekeskus.ee</a></div>
				</div>
			</div>
		';
	}

	/**
	 * Returns true if WooCommerce version is 3.4.0 or higher (different links in admin)
	 * 
	 * @since    3.0.0
	 */
	public static function new_woo_version() {

		global $woocommerce;

		if ( version_compare( $woocommerce->version, "3.4.0", ">=" ) ) {
			return true;
		} 

		return false;
	}

	/**
	 * Checks if API is set.
	 * 
	 * @since 3.0.0
	 */
	public static function is_api_set() {

		return self::get_api( true );
	}

	/**
	 * Displays error in admin that API access is not configured properly
	 * 
	 * @since 3.0.0
	 */
	public function mk_admin_error() {

		printf( '<div class="%1$s"><p>%2$s</p></div>', 'notice notice-error', __( 'Please check that you have configured correctly MakeCommerce API accesses', 'wc_makecommerce_domain' ) ); 
	}

	/**
	 * Returns MK api, unless check is set to true, then returns if api is set
	 * 
	 * @since 3.0.0
	 */
	public static function get_api( $check = false ) {

		$mk_api_type = get_option( 'mk_api_type', false );

		if ( !$mk_api_type ) {
			return false;
		}

		$key_prefix = '';
		if ( $mk_api_type !== 'live' ) {
			$key_prefix = $mk_api_type.'_';
		}

		$mk_shop_id = get_option( 'mk_'.$key_prefix.'shop_id', '' );
		$mk_public_key = get_option( 'mk_'.$key_prefix.'public_key', '' );
		$mk_private_key = get_option( 'mk_'.$key_prefix.'private_key', '' );

		if ( !$mk_shop_id || !$mk_public_key || !$mk_shop_id ) {
			return false;
		}

		//if this was just a check, return true
		if ( $check === true ) {
			return true;
		}
		
		global $MKAPI;
		$MKAPI = new \Maksekeskus\Maksekeskus( $mk_shop_id, $mk_public_key, $mk_private_key, $mk_api_type === 'live' ? false : true );

		return $MKAPI;
	}

	/**
	 * Sets up shop getConfig request parameters
	 * 
	 * @since 3.0.0
	 */
	public static function config_request_parameters( $module = "" ) {
		
        return array(
            'environment' => json_encode( array(
                'system' => array( 'wordpress' => get_bloginfo( 'version' ), "php" => phpversion() ),
                'platform' => 'woocommerce '. WC_VERSION,
                'module' => $module,
            )),
        );
	}
	
	/**
	 * Helpful tool for debugging
	 * 
	 * @since 3.0.0
	 */
	public static function prd() {

		//get all arguments
		$args = func_get_args();

		echo "<pre>";

		foreach ( $args as $arg ) {

			$printR = print_r( $arg, true );

			error_log( $printR );

			echo $printR . "\r\n\r\n\r\n";
		}

		echo "</pre>";

		die();
	}


	public static $scriptLoaderAttributes = array();

	/**
	 * Enqueues javascript with parameters.
	 * 
	 * @since	3.0.9
	 */
	public static function mc_enqueue_script( $handle, $src, $data = [], $deps = [], $external = false ) {

		//check if the script is already enqueued. Fixes issue for misbehaving plugins who the queue again which causes inline script to be added more than once.
		//https://github.com/wp-media/wp-rocket/issues/3125
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}
		
		$version = null;
		if ( !$external ) {

			$version = filemtime( $src );
			$src = plugin_dir_url( $src ) . basename( $src );
		}

		wp_register_script( $handle, $src, $deps, $version );
        
		if ( !empty( $data ) ) {
			wp_add_inline_script( $handle, 'const ' . $handle . ' = ' . json_encode( $data ), 'before' );
		}

        wp_enqueue_script( $handle );
	}
}