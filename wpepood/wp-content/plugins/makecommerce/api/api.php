<?php

/**
 * The api functionality of the plugin.
 *
 * @link       https://makecommerce.net/
 * @since      3.0.0
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/api
 */

namespace MakeCommerce;

/**
 * The payment functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/api
 * @author     Maksekeskus AS <support@maksekeskus.ee>
 */
class API {

	/**
	 * The ID of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	private Loader $loader;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    3.0.0
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, Loader $loader ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->loader = $loader;
	}

	public function label_formats() {

		$MK = \MakeCommerce::get_api();
		
		if ( !$MK ) {
			return array( 'A4' => 'A4' );
		}

		$optionsArray = array();

		try {
			
			$formats = $MK->getLabelFormats();

			foreach ( $formats as $format ) {
				$optionsArray[$format] = $format;
			}
		} catch ( \Exception $e ) {
			//do nothing, something is wrong with the credentials or the shop is disabled
	    }
		
		return $optionsArray;
	}
	
	/**
	 * Gives error in admin if curl is not loaded
	 */
	public function check_if_curl_is_loaded() {

		if ( !extension_loaded('curl') ) {

			$class = 'notice notice-error';
			$message = __( 'You have enabled MakeCommerce module but it seems that you don\'t have CURL enabled. This way MakeCommerce unfortunately does not work!', 'wc_makecommerce_domain' );
			
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		}
	}

	/**
	 * Add menu item to WooCommerce advanced section
	 * 
	 * @since 3.0.0
	 */
	public function add_woo_menu( $sections ) { 

		$sections['mk_api'] = __('MakeCommerce API access', 'wc_makecommerce_domain');

		return $sections;
	}

	/**
	 * Adds new column (payment method) in order view
	 * 
	 * @since 3.0.0
	 */
	public function add_ordersview_paymentmethod_column( $columns ) {
		
		$columns['makecommerce_payment_method'] = __('Payment method (MC)');

		return $columns;
	}

	/**
	 * Makes payment method column in order view sortable
	 * 
	 * @since 3.0.0
	 */
	public function make_ordersview_paymentmethod_column_sortable( $columns ) {

		return wp_parse_args (array( 'makecommerce_payment_method' => 'makecommerce_payment_method' ), $columns );
	}
		
	/**
	 * Inserts content for payment method column in order view
	 * 
	 * @since 3.0.0
	 */
	public function fill_ordersview_paymentmethod_column( $column, $post_id ) {

		if ( 'makecommerce_payment_method' === $column ) {
			$order = wc_get_order( $post_id );
			echo $order->get_meta( '_makecommerce_preselected_method', true );
		}
	}

	/**
	 * Update banklinks when an admin logs in
	 * 
	 * @since 3.0.0
	 */
	public function admin_login( $user_login, $user ) {

	    if ( user_can( $user, 'administrator' ) ) {
	    	Payment::update_banklinks();
	    }
	}

	/**
	 * Adds settings link to plugins page
	 * 
	 * @since 3.0.0
	 */
	public function add_plugin_settings_link( $links ) {

		$settings_link = '<a href="admin.php?page=wc-settings&tab=api&section=mk_api">API '.__('Settings').'</a>';

		if ( \MakeCommerce::new_woo_version() ) {
			$settings_link = '<a href="admin.php?page=wc-settings&tab=advanced&section=mk_api">API '.__('Settings').'</a>';
		}

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Updates banklinks if current section is not in mk_api
	 * 
	 * @since 3.0.0
	 */
	public function save_settings() {
		
		global $current_section;

		if ( $current_section !== 'mk_api' ) {
			return;
		}

		Payment::update_banklinks();
	}

	/**
	 * Test and live switching in API settings (jQuery)
	 * 
	 * @since 3.0.0
	 */
	public function api_javascript_ui( $data ) {

		wp_enqueue_script( $this->plugin_name . "api-admin", plugin_dir_url(__FILE__) . 'js/api.js', array( 'jquery' ), $this->version );
    }

	/**
	 * Add admin settings for api in woocommerce->settings->advanced->Makcommerce API
	 * 
	 * @since 3.0.0
	 */
	public function add_woo_admin_settings( $settings ) {

		/**
		 * Prevent the settings from being loaded if we are not at actually the page
		 */
		global $current_section;

		if ( $current_section !== 'mk_api' ) {
			return $settings;
		}
		
		/**
		 * Return array with fields for settings
		 */
		return [
			[ 'type' => 'title', 'desc' => \MakeCommerce::get_logo_html() ],
			[
				'type' => 'title', 
				'title' => __('MakeCommerce API access credentials', 'wc_makecommerce_domain'), 
				'desc' => __('To use MakeCommerce/Maksekeskus services you need to enter API credentials below here <br/> <br/>', 'wc_makecommerce_domain').
				sprintf( __('To further configure the Payment methods please go to <a href="%s">MakeCommerce Checkout Options</a>, links to settings of our Shipment methods are listed below', 'wc_makecommerce_domain'), 'admin.php?page=wc-settings&tab=checkout&section=makecommerce'),
				'id' => 'mk_api_settings'
			],
			[
				'type' => 'select',
				'title' => __('Current environment', 'wc_makecommerce_domain'),
				'desc' => __('See more about <a href="https://maksekeskus.ee/en/for-developers/test-environment/">MakeCommerce Test environment</a>', 'wc_makecommerce_domain'),
				'default' => 'live',
				'options' => [
						'live' => __('Live', 'wc_makecommerce_domain'),
						'test' => __('Test', 'wc_makecommerce_domain'),
				],
				'id' => 'mk_api_type'
			],
			[
				'id' => 'mk_shop_id',
				'type' => 'text',
				'title' => __('Shop ID (live)', 'wc_makecommerce_domain'),
				'desc' => __('Get it from <a href="https://merchant.maksekeskus.ee/api.html" target="_blank">Merchant Portal</a>','wc_makecommerce_domain'), 
				'class' => 'input-text regular-input',
			],
			[
				'id' => 'mk_private_key',
				'type' => 'text',
				'title' => __('Secret key (live)', 'wc_makecommerce_domain'),
				'class' => 'input-text regular-input',
			],		      	
			[
				'id' => 'mk_public_key',
				'type' => 'text',
				'title' => __('Publishable key (live)', 'wc_makecommerce_domain'),
				'class' => 'input-text regular-input',
			],
			[
				'id' => 'mk_test_shop_id',
				'type' => 'text',
				'title' => __('Shop ID (test)', 'wc_makecommerce_domain'),
				'class' => 'input-text regular-input',
				'default' => 'f64b4f20-5ef9-4b7b-a6fa-d623d87f0b9c',
				'desc' => __('Get it from <a href="https://merchant.test.maksekeskus.ee/api.html" target="_blank">Merchant Portal Test</a>','wc_makecommerce_domain'),
			],		      	
			[
				'id' => 'mk_test_private_key',
				'type' => 'text',
				'title' => __('Secret key (test)', 'wc_makecommerce_domain'),
				'default' => 'MPjcVMoRZPAucsTuGK5ZukOlV7BlzgSvXOowJUkk9IFSQPVooRwcVOxzz3mhEpgM',
				'class' => 'input-text regular-input',
			],
			[
				'id' => 'mk_test_public_key',
				'type' => 'text',
				'title' => __('Publishable key (test)', 'wc_makecommerce_domain'),
				'default' => '7Hog41ci2mKkmtviMycWxpNx14pNP70m',
				'class' => 'input-text regular-input',
			],
			[
				'type' => 'select',
				'title' => __('Label print format', 'wc_makecommerce_domain'),
				'desc' => __('In which format should shipping labels be printed. (*make sure you have API access to see more options)', 'wc_makecommerce_domain'),
				'default' => 'A4',
				'options' => $this->label_formats(),
				'id' => 'mk_label_format'
			],
			['type' => 'sectionend', 'id' => 'mk_api_settings'],
			[
				'type' => 'title',
				'desc' => '<br><hr><br>',
			],
			[
				'title' => __( 'Parcel Machine Map Settings', 'wc_makecommerce_domain' ),
				'type' => 'title',
				'desc' => __( 'Before using, please read more about the functionalities, benefits and security measures of our parcel machine map from', 'wc_makecommerce_domain' ) .
                          ' ' . sprintf('<a target="_blank" href="%s">' .
                          __( 'our plugin instructions', 'wc_makecommerce_domain' ) .
                          '</a>', __( 'https://makecommerce.net/integration-modules/makecommerce-plugin-for-woocommerce/#map-view-for-the-selection-of-parcel-machines', 'wc_makecommerce_domain' ) ),
				'id' => 'mc_map_settings',
			],
			[
				'title' => __( 'Enable map selection for parcel machines', 'wc_makecommerce_domain' ),
				'label' => __( 'Allow customers to select their desired parcel machine from a map view', 'wc_makecommerce_domain' ),
				'type' => 'checkbox',
				'default' => 'no',
				'id' => 'mc_parcel_machine_map',
			],
			[
				'title' => __( 'Define Google Javascript API key', 'wc_makecommerce_domain' ),
				'type' => 'text',
				'desc_tip' => __( 'In order to enable the map feature for parcel machine seletion, a Google Javascript API key needs to be obtained', 'wc_makecommerce_domain' ),
				'class' => 'ui-map-identifier',
				'id' => 'mc_google_api_key',
			],
			[
				'title' => __( 'Use Google Geocoding', 'wc_makecommerce_domain' ),
				'label' => __( 'Geocoding allows the map to centralize on the shipping address', 'wc_makecommerce_domain' ),
				'type' => 'checkbox',
				'default' => 'no',
				'id' => 'mc_map_geocoding',
			],
			[
				'title' => __( 'Define a Google Geocoding API key', 'wc_makecommerce_domain' ),
				'type' => 'text',
				'desc_tip' => __( 'A separate API key is recommended to be defined for Geocoding due to security reasons', 'wc_makecommerce_domain' ),
				'class' => 'ui-map-identifier',
				'id' => 'mc_map_geocoding_api_key',
			],
			[
				'type' => 'sectionend', 
				'id' => 'mc_map_settings',
			],
			[
				'type' => 'title',
				'desc' => '<br><hr><br>',
			],
			[
				'id' => 'mk_module_title',
				'type' => 'title',
				'title' => __('Makecommerce modules', 'wc_makecommerce_domain'),
				'desc' => __('Our plugin adds several modules to your shop. Here you can switch off modules that are not important for you, they will disappear from Woocommerce settings menus.<br> Each active module have their own settings dialog, where they must also be Enabled before use.', 'wc_makecommerce_domain'),
				'class' => '',
			],
			[
				'id' => 'mk_transport_apt_omniva',
				'type' => 'checkbox',
				'default' => 'yes',
				'title' => __('Omniva Parcel Machine', 'wc_makecommerce_domain'),
				'desc' => __('enable Omniva parcel machines shipping method', 'wc_makecommerce_domain').' ('. sprintf(__('<a href="%s">module settings</a>', 'wc_makecommerce_domain'), admin_url('admin.php?page=wc-settings&tab=shipping&section=parcelmachine_omniva')).')',
				'class' => '',
			],
			[
				'id' => 'mk_transport_apt_smartpost',
				'type' => 'checkbox',
				'default' => 'yes',
				'title' => __('Smartpost Parcel Machine', 'wc_makecommerce_domain'),
				'desc' => __('enable Smartpost parcel machines shipping method', 'wc_makecommerce_domain').' ('. sprintf(__('<a href="%s">module settings</a>', 'wc_makecommerce_domain'), admin_url('admin.php?page=wc-settings&tab=shipping&section=parcelmachine_smartpost')).')',
				'class' => '',
			],
			[
				'id' => 'mk_transport_apt_dpd',
				'type' => 'checkbox',
				'default' => 'no',
				'title' => __('DPD', 'wc_makecommerce_domain') . ' ' . __('parcel machine', 'wc_makecommerce_domain'),
				'desc' => __('enable DPD parcel machine shipping method', 'wc_makecommerce_domain').' ('. sprintf(__('<a href="%s">module settings</a>', 'wc_makecommerce_domain'), admin_url('admin.php?page=wc-settings&tab=shipping&section=parcelmachine_dpd')).')',
				'class' => '',
			],
			[
				'id' => 'mk_transport_apt_lp_express_lt',
				'type' => 'checkbox',
				'default' => 'no',
				'title' => __('LP Express', 'wc_makecommerce_domain') . ' ' . __('parcel machine', 'wc_makecommerce_domain'),
				'desc' => __('enable LP Express parcel machine shipping method', 'wc_makecommerce_domain').' ('. sprintf(__('<a href="%s">module settings</a>', 'wc_makecommerce_domain'), admin_url('admin.php?page=wc-settings&tab=shipping&section=parcelmachine_lp_express_lt')).')',
				'class' => '',
			],
			[
				'id' => 'mk_transport_courier_omniva',
				'type' => 'checkbox',
				'default' => 'yes',
				'title' => __('Omniva Courier', 'wc_makecommerce_domain'),
				'desc' => __('enable Omniva courier shipping method', 'wc_makecommerce_domain').' ('. sprintf(__('<a href="%s">module settings</a>', 'wc_makecommerce_domain'), admin_url('admin.php?page=wc-settings&tab=shipping&section=courier_omniva')).')',			        
				'class' => '',
			],
			[
				'id' => 'mk_transport_courier_smartpost',
				'type' => 'checkbox',
				'default' => 'yes',
				'title' => __('Smartpost Courier', 'wc_makecommerce_domain'),
				'desc' => __('enable Smartpost courier shipping method', 'wc_makecommerce_domain').' ('. sprintf(__('<a href="%s">module settings</a>', 'wc_makecommerce_domain'), admin_url('admin.php?page=wc-settings&tab=shipping&section=courier_smartpost')).')',			        
				'class' => '',
			],
			[
				'id' => 'mk_transport_courier_dpd',
				'type' => 'checkbox',
				'default' => 'yes',
				'title' => __('DPD Courier', 'wc_makecommerce_domain'),
				'desc' => __('enable DPD courier shipping method', 'wc_makecommerce_domain').' ('. sprintf(__('<a href="%s">module settings</a>', 'wc_makecommerce_domain'), admin_url('admin.php?page=wc-settings&tab=shipping&section=courier_dpd')).')',
				'class' => '',
			],
			[
				'id' => 'mk_javascript_ui',
				'type' => 'api_javascript_ui',
			],
			['type' => 'sectionend', 'id' => 'mk_api_settings']
		];
	}

	/**
     * Function for resetting machines and payment methods when API type is changed
     * 
     * @since 3.2.1
     */
	public function mk_delete_api_cache() {

		global $wpdb;
		$tableName = $wpdb->prefix . MAKECOMMERCE_TABLENAME;

		// Delete machines' cache and timer
		delete_option( 'mk_machines_expires' );
		delete_option( 'mk_machines_cache' );

		// Delete payment methods
        $wpdb->query( 'TRUNCATE TABLE `'.$tableName.'`' );
	}

	/**
     * Notice for missing API information
     * 
     * @since 3.0.0
     */
    public function api_info_missing() {

        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php echo __( 'You have not entered the Shop ID and keys for the MakeCommerce payment module. The module will not work without them.', 'wc_makecommerce_domain' ); ?>
                <?php if ( \MakeCommerce::new_woo_version() ) { ?>
                <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=advanced&section=mk_api' ); ?>"><?php echo __( 'Click here to enter them', 'wc_makecommerce_domain' ); ?></a>
                <?php } else { ?>
                <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=api&section=mk_api' ); ?>"><?php echo __( 'Click here to enter them', 'wc_makecommerce_domain' ); ?></a>
                <?php } ?>
            </p>
        </div>
        <?php
    }
}