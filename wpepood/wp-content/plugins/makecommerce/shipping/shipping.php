<?php

namespace MakeCommerce;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://makecommerce.net/
 * @since      3.0.0
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/shipping
 */

/**
 * The shipping-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/shipping
 * @author     Maksekeskus AS <support@maksekeskus.ee>
 */

use MakeCommerce;
use MakeCommerce\Shipping\Method\ParcelMachine\Map;

class Shipping {
	use Map;

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
    
	private $loader;

	private Shipping\Label $label;
	private Shipping\Product $product;
	private Shipping\Order $order;
	
	/**
	 * Define all shipping methods
	 */
	public $shipping_methods = [
		[
			"option" => "mk_transport_apt_omniva",
			"method" => "parcelmachine_omniva",
			"class"  => "MakeCommerce\Shipping\Method\ParcelMachine\Omniva"
		],
		[
			"option" => "mk_transport_apt_smartpost",
			"method" => "parcelmachine_smartpost",
			"class"  => "MakeCommerce\Shipping\Method\ParcelMachine\Smartpost"
		],
		[
			"option" => "mk_transport_apt_dpd",
			"method" => "parcelmachine_dpd",
			"class"  => "MakeCommerce\Shipping\Method\ParcelMachine\DPD"
		],
		[
			"option" => "mk_transport_apt_lp_express_lt",
			"default_name" => "LP Express",
			"method" => "parcelmachine_lp_express_lt",
			"class"  => "MakeCommerce\Shipping\Method\ParcelMachine\LPExpress"
		],
		[
			"option" => "mk_transport_courier_omniva",
			"method" => "courier_omniva",
			"class"  => "MakeCommerce\Shipping\Method\Courier\Omniva"
		],
		[
			"option" => "mk_transport_courier_smartpost",
			"method" => "courier_smartpost",
			"class"  => "MakeCommerce\Shipping\Method\Courier\Smartpost"
		],
		[
			"option" => "mk_transport_courier_dpd",
			"method" => "courier_dpd",
			"class"  => "MakeCommerce\Shipping\Method\Courier\DPD"
		]
	];

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    3.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, Loader $loader ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->loader = $loader;

		//loads all child classes such as label, admin specific functions, email functions
		$this->load_children();
		
		if ( is_admin() ) {
		    add_action( 'wp_ajax_verify_feature_swc', [$this, 'verify_makecommerce_shipment_mediaton_availablity'] );
		}

		if ( self::map_enabled() ) {
			add_action('woocommerce_before_checkout_form', [$this, 'initialize_mc_map'] );
		}
	}
	
	/**
     * Check if the customer has shipment mediation for Omniva enabled in MakeCommerce
     * 
     * @since 3.0.0
     */
    public function verify_makecommerce_shipment_mediaton_availablity() {

        $status = $this->check_shipment_mediaton_availablity( 'shipments_without_credentials' );

        wp_send_json( ['feature_name' => 'shipments_without_credentials', 'feature_status' => $status] );

        exit; 
    }

    /**
     * Check if a shipment mediation is enabled in MakeCommerce
     * 
     * @since 3.0.0
     */
    public function check_shipment_mediaton_availablity( $feature_name = '' ) {
        
        $feature_enabled = false;
        
        $MK = MakeCommerce::get_api();

        if ( !$MK ) {
            return false;
        }
                                
        try {
            $shopConfig = $MK->getShopConfig( MakeCommerce::config_request_parameters( "makecommerce feature_staus_check" ) );
			$features = $shopConfig->features;	
			
        } catch ( \Exception $e ) {

            error_log( print_r( $e, 1 ) );

            return false;
        }
        
        if ( isset( $features ) ) {

            foreach ( $features as $feature ) {
                
                if ( $feature->name == $feature_name ) {
                    $feature_enabled = $feature->enabled;
                }
            }
        }
            
        return $feature_enabled;
    }
	
	/**
	 * Removes transient cache to be able to override shipping cost
	 * 
	 * @since 3.0.0
	 */
    public function initalize() {

        global $wpdb;
        
		$transients = $wpdb->get_col( "SELECT `option_name` FROM `".$wpdb->options."` WHERE `option_name` LIKE '_transient_wc_ship%'" );
		if ( count($transients) ) {
			foreach ( $transients as $tr ) {
				delete_transient( substr( $tr, 11 ) );
			}
        }
        
        $transient_value = get_transient( 'shipping-transient-version' );
        
        \WC_Cache_Helper::delete_version_transients( $transient_value );
        
		if ( WC()->session ) {
			WC()->session->set( 'shipping_for_package', '' );
		}
    }

	/**
	 * Load all supporting child classes
	 * 
	 * @since 3.0.0
	 */
    public function load_children() {

		//load label class (everything to do with shipping labels and printing)
        $this->label = new Shipping\Label( $this->loader );
		
		//load product class (everything to do with shipping and products)
		$this->product = new Shipping\Product( $this->loader );

		//load order class (everything to do with shipping and orders)
		$this->order = new Shipping\Order( $this->loader );
    }

	/**
	 * Manually add all different shipping methods to WooCommerce
	 * 
	 * @since 3.0.0
	 */
    public function add_shipping_methods( $methods ) {

		foreach ( $this->shipping_methods as $shipping_method ) {

			if ( get_option( $shipping_method["option"] ) === "yes" ) {
				$methods[$shipping_method["method"]] = $shipping_method["class"];
			}
		}

		return $methods;
    }
	
	/**
	 * Clears shipping rates cache, needed for calculation shipping cost
	 * 
	 * @since 3.0.0
	 */
    public function clear_shipping_rates_cache() {

        $packages = WC()->cart->get_shipping_packages();
        
		foreach ( $packages as $key => $value ) {

			$shipping_session = "shipping_for_package_$key";
			unset( WC()->session->$shipping_session );
		}
    }
	
	/**
	 * Set order parcel machine meta
	 * 
	 * @since 3.0.0
	 */
    public function set_parcel_machine_meta( $post_id ) {

		$update = false;
		$order = wc_get_order( $post_id );

		// Not an order
		if ( !$order || get_transient( '_set_parcel_machine_meta_transient' ) ) {
			return;
		}

		if ( isset( $_POST['_mk_machine_id'] ) ) {

			//if the machine id is not the same as it was before then delete the _parcel_machine_shipment_id so a new one can be created.
			$current_parcel_machine = $order->get_meta( '_parcel_machine', true );

			// LP Express set order template
			if ( isset( $_POST['_mk_template_id'] ) ) {
				$current_template = $order->get_meta( '_mk_parcel_template', true );
				//set the template for the order
				if ( $_POST['_mk_template_id'] != $current_template ) {
					$order->update_meta_data( '_mk_parcel_template', $_POST['_mk_template_id'] );
					$update = true;
				}
			}

			if ( $_POST['_mk_machine_id'] != $current_parcel_machine || $update ) {
				//set new parcel machine
				$order->update_meta_data( '_parcel_machine', $_POST['_mk_machine_id'] );

				//remove old shipment id
				$order->update_meta_data( '_parcel_machine_shipment_id', "" );

				set_transient( '_set_parcel_machine_meta_transient', true );

				$order->save();

				//register shipment again
				$this->register_shipment( $post_id );

				//update address
				self::update_order_parcelmachine_meta( $_POST['_mk_machine_id'], $post_id );

				delete_transient( '_set_parcel_machine_meta_transient' );
			}
		}
    }

	/**
	 * Changes order view WHERE clause to include orders using MakeCommerce shipping
	 *
	 * @since 3.0.0
	 */
	public function shipping_filter( $where, $wp_query ) {

		global $pagenow, $wpdb;

		$method = !empty( $_REQUEST['_shipping_method'] ) ? $_REQUEST['_shipping_method'] : false;
		//HPOS?
		if ( is_admin() && $pagenow=='edit.php' && $wp_query->query_vars['post_type'] == 'shop_order' && !empty( $method ) ) {
			$where .= $GLOBALS['wpdb']->prepare( ' AND ID
				IN (
				SELECT items.order_id
				FROM '.$wpdb->prefix.'woocommerce_order_itemmeta meta, '.$wpdb->prefix.'woocommerce_order_items items
				WHERE meta.order_item_id = items.order_item_id
				AND meta.meta_key = "method_id"
				AND meta.meta_value = %s
			) ', $method );
		}

		return $where;
	}

	/**
	 * Set order parcel machine meta
	 * 
	 * @since 3.1.0
	 */
    public function update_courier_meta ( $post_id ) {

		$order = wc_get_order( $post_id );

		if ( !$order || get_transient( '_update_courier_meta_transient' ) ) {
			return;
		}

		// Check if a shipping method exists
		if ( isset( $_POST["shipping_method"] ) ) {
			$method = $_POST["shipping_method"];
		} else {
			return;
		}

		if ( is_array( $method ) && count( $method ) > 0 ) {
			// Check if it is a courier
			if ( array_values( $method )[0] == "courier_smartpost"
			     || array_values( $method )[0] == "courier_omniva"
			     || array_values( $method )[0] == "courier_dpd"
			) {

				//remove old shipment id
				$order->update_meta_data( '_parcel_machine_shipment_id', "" );

				set_transient( '_update_courier_meta_transient', true );

				$order->save();

				//register shipment again
				$this->register_shipment( $post_id );

				delete_transient( '_update_courier_meta_transient' );
			}
		}
	}

	/**
	 * Update parcel machine meta data for an order
	 * Adds all the shipping information possible.
	 * 
	 * @since 3.0.3
	 */
	public static function update_order_parcelmachine_meta( $machine_id, $order_id ) {
		
		list( $carrier, $machine ) = explode( '||', $machine_id );

		$machine = self::mk_get_machine( $carrier, $machine );
		
		if ( !empty( $machine['id'] ) ) {

			$order = wc_get_order( $order_id );

			if ( empty( $order->get_shipping_first_name() ) ) {
				$order->set_shipping_first_name( $order->get_billing_first_name() );
			}

			if ( empty( $order->get_shipping_last_name() ) ) {
				$order->set_shipping_last_name( $order->get_billing_last_name() );
			}


			$order->set_shipping_address_1( sanitize_text_field( $machine['name'] ) );
			$order->set_shipping_address_2( sanitize_text_field( $machine['address'] ) );
			$order->set_shipping_city( sanitize_text_field( $machine['city'] ) );
			$order->set_shipping_postcode( sanitize_text_field( $machine['zip'] ) );

			$order->update_meta_data( '_parcel_machine', sanitize_text_field( $machine_id ) );

			$order->save();
		}
	}

	/**
	 * Registers shipment(s) via MakeCommerce API
	 * 
	 * @since 3.0.0
	 */
    public function register_shipment( $post_ids, $select_carrier = null ) {

		//we always expect an array of post ids
		if ( !is_array( $post_ids ) ) {
			$post_ids = [$post_ids];
		}

		//initialize to delete transient and set WC session
		$this->initalize();

		$shipping_request = ['credentials' => [], 'orders' => []];

		//setup shipping classes map
		$shipping_classes_map = [];
		foreach ( $this->shipping_methods as $shipping_method ) {

			$shipping_classes_map[$shipping_method["method"]]  = $shipping_method["class"];
		}

		//loop all post ids
		foreach ( $post_ids as $post_id ) {

			$order = wc_get_order( $post_id );

			//if parcel machine shipment id already exists then skip
			$oldId = $order->get_meta( '_parcel_machine_shipment_id', true );

			if ( strlen( $oldId ) > 6 ) {
				continue;
			}

			//check if the post state is even paid, if not, ignore this post...
			//this could perhaps be in a way better location. Why is the function even called when post state is set to failed.
			if ( ( string )$order->get_status() !== "completed" && ( string )$order->get_status() !== "processing" ) {
				continue;
			}
			
			//skip if the order doesnt have a shipping method
			$shipping_methods = $order->get_shipping_methods();
			if ( empty( $shipping_methods ) ) {
				continue;
			}

			//loop all MakeCommerce shipping methods
			foreach ( $shipping_methods as $shipping_method ) {

				$shipping_id = explode( ':', $shipping_method['method_id'] );
				$shipping_class = $shipping_id[0];
				$shipping_instance = !empty( $shipping_id[1] ) ? $shipping_id[1] : null;
				
				//if chosen method is not the current method then move on to the next one
				if ( empty( $shipping_classes_map[$shipping_class] ) ) {
					continue;
				}

				// New metadata for keeping track of order shipping methods
				if ( isset( $shipping_class ) ) {
					$order->update_meta_data( '_mc_shipping_method', sanitize_text_field( $shipping_class ) );
					$order->save();
				}

				$transport_class = new $shipping_classes_map[$shipping_class]( $shipping_instance );
				$carrier_uc = mb_strtoupper( $transport_class->carrier_id );

				//change carrier_uc if it has been set in the interface.
				if ( isset( $transport_class->settings['service_carrier'] ) ) {
					$carrier_uc = $transport_class->settings['service_carrier'];
				}

				if ( empty( $shipping_request['credentials'][$carrier_uc] ) ) {
					$shipping_request = $this->set_shipping_request_credentials( $carrier_uc, $transport_class, $shipping_request );
					// Move on to next post if the function returned empty values
					if ( empty( $shipping_request['credentials'][$carrier_uc] ) && empty( $transport_class->settings['use_mk_contract'] ) ) {
						continue;
					}
				}

				$sr_order = $this->set_sr_order_data(
					$order,
					$transport_class,
					$shipping_class,
					$carrier_uc
				);

				if ( !$sr_order ) continue;

				if ( $carrier_uc === 'LP_EXPRESS_LT' ) {
					$default = get_option( 'mk_lpexpress_template' );

					if ( !empty( $order->get_meta( '_mk_parcel_template', true ) ) ) {
						$template = $order->get_meta( '_mk_parcel_template', true );
					} else {
						$template = $default;
					} 
					$sr_order['lpExpressShipmentDetails']["templateId"] = $template;
				}

				$shipping_request['orders'][] = $sr_order;
			}
		}
		
		$shipping_request['credentials'] = array_values( $shipping_request['credentials'] );

		if ( empty( $shipping_request['orders'] ) ) {
			return;
		}
		
		$MK = MakeCommerce::get_api();
		if ( !$MK ) {
			return;
		}
		
		try {
			$response = $MK->createShipments( $shipping_request );
		} catch ( \Exception $e ) {
			error_log( 'Error while creating shipment ['.$e->getMessage().']' );
			return;
		}

		$shipments = !empty( $response->shipments ) ? $response->shipments : $response;
		$manifest = !empty( $response->manifests ) ? $response->manifests[0] : false;
		
		foreach ( $shipments as $order_data ) {
			// No id present, continue
			if ( !wc_get_order( ( int ) $order_data->orderId ?? false ) ) {
				continue;
			}

			$order = wc_get_order( ( int ) $order_data->orderId );

			if ( $manifest ) {
				$order->update_meta_data( '_parcel_machine_manifest', sanitize_text_field( $manifest ) );
			}

			if ( !empty( $order_data->lpExpressCartIdentifier ) ) {
				$order->update_meta_data( '_lp_express_cart_identifier', sanitize_text_field( $order_data->lpExpressCartIdentifier ) );
			}

			// LP Express has shipmentId AND barcode
			if ( isset( $order_data->barcode ) ) {
				$order->update_meta_data( '_parcel_machine_shipment_barcode', sanitize_text_field( $order_data->barcode ) );
			}

			// OrderId needed for all the following blocks
			if ( !empty( $order_data->orderId ) ) {
				if ( ! empty( $order_data->shipmentId ) ) {
					$order->update_meta_data( '_parcel_machine_shipment_id', sanitize_text_field( $order_data->shipmentId ) );
					$order->update_meta_data( '_tracking_number', sanitize_text_field( $order_data->shipmentId ) ); //default value used by other plugins, such as WooCommerce PDF Invoices and so on
					$order->delete_meta_data( '_parcel_machine_error' );
				} else if ( !empty( $order_data->barCode ) ) {

					$order->update_meta_data( '_parcel_machine_shipment_id', sanitize_text_field( $order_data->barCode ) );
					$order->update_meta_data( '_tracking_number', sanitize_text_field( $order_data->barCode ) ); //default value used by other plugins, such as WooCommerce PDF Invoices and so on
					$order->delete_meta_data( '_parcel_machine_error' );
				} else if ( !empty( $order_data->errorMessage ) ) {

					$order->update_meta_data( '_parcel_machine_error', sanitize_text_field( $order_data->errorMessage ) );
				}
			}

			$order->save();
		}
	}

	/**
	 * Returns shipping information for order
	 * 
	 * @since 3.0.9
	 */
	public function get_order_shipping_information( $order) {

		$shipping_information = [];

		$shipping_address = $order->get_address( 'shipping' );
		$billing_address = $order->get_address( 'billing' );

		$phone = '';
		if ( isset( $shipping_address['phone'] ) ) {
			$phone = $shipping_address['phone'];
		}
		
		if ( $phone == '' && isset( $billing_address['phone'] ) ) {
			$phone = $billing_address['phone'];
		}

		$shipping_information['first_name'] = $shipping_address['first_name'] ? $shipping_address['first_name'] : $billing_address['first_name'];
		$shipping_information['last_name'] = $shipping_address['last_name'] ? $shipping_address['last_name'] : $billing_address['last_name'];
		$shipping_information['phone'] = $phone;
		$shipping_information['email'] = $order->get_meta( '_shipping_email', true ) ? $order->get_meta( '_shipping_email', true ) : $billing_address['email'];

		$shipping_information['recipient_name'] = $shipping_information['first_name'] . ' ' . $shipping_information['last_name'];

		return $shipping_information;
	}

    /**
	 * Returns the correct link to be used for tracking link
	 * 
	 * @since 3.0.0
	 */
    public function get_tracking_link( $carrier, $order, $shipment_id, $shopLocation = false ) {
        //do we use shop location or delivery location
        if ( $shopLocation ) {
            //get_base_country returns either EE, LT or LV. Everything else is irrelevant
            $dst = substr( strtolower( WC()->countries->get_base_country() ), 0, 2);
            $lang = get_locale();
            if ( strlen( $lang ) > 2 ) {
                // Make locales like en_US or lt_LT shorter
                $lang = substr($lang, 0,2);
            }
        } else {
            //get order delivery location. Returns EE, LT or LV. Evertyhing else is irrelevant
            $dst = substr( strtolower( $order->get_shipping_country() ), 0, 2 );
            $lang = substr( strtolower( $order->get_meta( 'wpml_language', true ) ), 0, 2 ); //returns nothing if it doesnt exist, otherwise returns en, et, lt, lv, ru
            if ( empty( $lang ) ) {
                // Try getting language with ploylang
                if( function_exists('pll_current_language' ) ) {
                    $lang = pll_current_language();
                }
            }
        }

        $link = MC_TRACKING_SERVICE_URL . urlencode( $shipment_id );

        $params  = [
            'carrier' => urlencode( $carrier ),
            'dst' => urlencode( $dst ),
            'lang' => urlencode( $lang )
        ];

        return esc_url( add_query_arg( $params, $link ) );
    }
	
	/**
	 * Returns all available parcelmachines
	 * 
	 * @since 3.0.0
	 */
	public static function mk_get_machines( $carrier, $country = null, $aptopts = [] ) {

		//set machine cache
		$data = get_option( 'mk_machines_cache', false );
		$data_expires = get_option( 'mk_machines_expires', false );

		if ( !$data || empty( $data ) || $data_expires < time() ) {

			$MK = MakeCommerce::get_api();
			
			if( !$MK ) {
				return [];
			}

			$data = $MK->getDestinations( ['type' => 'APT,PUP'] );
			
			update_option( 'mk_machines_cache', $data, 'no' );
			update_option( 'mk_machines_expires', time() + 3 * 60 * 60, 'no' );
		}

		//return empty if no machines found for destination
		if ( !$data || empty( $data ) ) {
			
			return [];
		}
		
		//create machines array
		$machines = [];

		foreach ( $data as $machine ) {

			if ( ( !$country || $machine->country === $country ) && 
				 ( $machine->type === 'APT' || $machine->type === 'PUP' ) && 
				 ( $carrier === '*' || strtolower( $carrier ) === strtolower( $machine->carrier ) ) ) {
				
				if ( !(isset($aptopts['use_white_apts']) && $aptopts['use_white_apts'] == 1 &&
				     $machine->carrier == 'SMARTPOST' && strpos($machine->name, 'valge') != false)) {

					if ( isset( $machine->x ) && isset( $machine->y ) ) {
						$machines[] = [
							'carrier' => strtolower( $machine->carrier ),
							'id' => $machine->id,
							'name' => $machine->name, 
							'city' => $machine->city,
							'address' => $machine->address ?? '',
							'commentEt' => $machine->commentEt ?? '',
							'commentLv' => $machine->commentLv ?? '',
							'commentLt' => $machine->commentLt ?? '',
							'commentFi' => $machine->commentFi ?? '',
							'availability' => $machine->availability ?? '',
							'zip' => $machine->zip,
							'x' => $machine->x,
							'y' => $machine->y
						];
						continue;
					} 
					
					$machines[] = [
						'carrier' => strtolower( $machine->carrier ),
						'id' => $machine->id,
						'name' => $machine->name, 
						'city' => $machine->city,
						'address' => !empty( $machine->address ) ? $machine->address : '',
						'zip' => $machine->zip
					];
				}
			}
		}

		//sort machines
		usort( $machines, function( $a, $b ) { 
			if ( $a['city'] === $b['city'] ) {
				
				return $a['name'] > $b['name'] ? 1 : -1;
			}

			return $a['city'] > $b['city'] ? 1 : -1;
		});

		return $machines;
	}

	/**
	 * Returns a specific parecelmachine
	 * 
	 * @since 3.0.0
	 */
	public static function mk_get_machine( $carrier, $id ) {

		//get all available machines
		$machines = self::mk_get_machines( $carrier );

		//loop and return the machine we are searching for if found
		foreach ( $machines as $machine ) {

			if ( $machine['carrier'] === $carrier && $machine['id'] == $id ) {

				return $machine;
			}
		}

		return false;
	}
    
	/**
	 * Register the stylesheets.
	 *
	 * @since 3.0.0
	 */
	public function enqueue_styles() {

		if ( is_admin() ) {
			wp_enqueue_style( $this->plugin_name . "-parcelmachine-admin", plugin_dir_url(__FILE__) . 'css/parcelmachine-admin.css', [], $this->version );
		}
	}

	/**
	 * Register the JavaScript.
	 *
	 * @since 3.0.0
	 */
	public function enqueue_scripts() {

		if ( is_admin() ) {
			MakeCommerce::mc_enqueue_script('MC_PARCELMACHINE_JS_ADMIN', dirname(__FILE__) . '/js/parcelmachine-admin.js', [], [ 'jquery' ]);
		} else {

			MakeCommerce::mc_enqueue_script('MC_PARCELMACHINE_SEARCHABLE_JS', dirname(__FILE__) . '/js/parcelmachine_searchable.js', 
				[['placeholder' => __( '-- select parcel machine --', 'wc_makecommerce_domain' )]], [ 'jquery' ]
			);

			MakeCommerce::mc_enqueue_script('MC_PARCELMACHINE_JS', dirname(__FILE__) . '/js/parcelmachine.js', [], [ 'jquery' ]);
		}
	}

	/**
	 * Initializes the parcel machine map with required variables
	 *
	 * @since 3.3.0
	 */
	public function initialize_mc_map() {
		// Prepare the address or location you want to geocode
		$address = implode( ',', array_filter(
			[
				get_option( 'woocommerce_store_address' ),
				get_option( 'woocommerce_store_city' ),
				get_option( 'woocommerce_store_postcode' )
			]
		) );

		$api_key = get_option( 'mc_google_api_key', '' );
		$url = 'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&callback=initMCPMMap&v=weekly';

		self::initialize_map(
			[
				'site_url' => get_site_url(),
				'address' => $address,
				'map_init_url' => $url,
				'locale' => substr( get_locale(), 0, 2 )
			]
		);
	}

	/**
	 * Updates the coordinates for the map centre
	 *
	 * @since 3.3.0
	 */
	public function update_map_center() {

		$coords = [0,0];

		// If geocoding is disabled, just update the country
		if ( !self::geocoding_enabled() ) {
			wp_send_json( [ 'coordinates' => $coords, 'country' => WC()->customer->get_shipping_country() ] );
		}

		// Prepare the address or location you want to geocode
		$address = implode( ',', array_filter(
			[
				WC()->customer->get_shipping_address(),
				WC()->customer->get_shipping_city(),
				WC()->customer->get_shipping_postcode()
			]
		) );

		$api_key = get_option( 'mc_map_geocoding_api_key', '' );
		$url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . $address . '&key=' . $api_key;

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'status' => '400' ], '400' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( isset( $data->results[0]->geometry->location ) ) {
			$coords = $data->results[0]->geometry->location;
			$coords = [ $coords->lat, $coords->lng ];
		}

		wp_send_json( [ 'coordinates' => $coords, 'country' => WC()->customer->get_shipping_country() ] );
	}

    /**
     * Adds credentials to the label / shipping request
     *
     * @since 3.3.0
     */
    public function set_shipping_request_credentials( $carrier_uc, $transport_class, $shipping_request ) {
        // TMS enabled, return
        if ( !empty( $transport_class->settings['use_mk_contract'] ) ) {
            return $shipping_request;
        }

        $shipping_request['credentials'][$carrier_uc]['carrier'] = $carrier_uc;

        if ( !empty( $transport_class->settings['api_key'] ) ) {
            $shipping_request['credentials'][$carrier_uc]['apiKey'] = $transport_class->settings['api_key'];
        }

        if ( !empty( $transport_class->settings['service_user'] ) ) {
            $shipping_request['credentials'][$carrier_uc]['username'] = $transport_class->settings['service_user'];
        }

        if ( !empty( $transport_class->settings['service_password'] ) ) {
            $shipping_request['credentials'][$carrier_uc]['password'] = $transport_class->settings['service_password'];
        }

        return $shipping_request;
    }

	/**
	 * Adds sender and destination data to label / shipping request
	 *
	 * @since 3.3.0
	 */
	public function set_sr_order_data( 
		$order,
		$transport_class,
		$shipping_class,
		$carrier_uc,
		$shipment_id = null,
		$label_creation = false
	) {

		if ( $transport_class->type === 'apt' ) {	
			$parcel_machine = $order->get_meta( '_parcel_machine', true );
			
			if ( !$parcel_machine ) {
				return;
			}

			list( $carrier, $machine_id ) = explode( '||', $parcel_machine );

			if ( !$carrier || !$machine_id ) {
				return;
			}
		}

		$sender = [	
			'name' => !empty( $transport_class->settings['shop_name'] ) ? $transport_class->settings['shop_name'] : '',
			'phone' => !empty( $transport_class->settings['shop_phone'] ) ? $transport_class->settings['shop_phone'] : '',
			'email' => !empty( $transport_class->settings['shop_email'] ) ? $transport_class->settings['shop_email'] : '',
			'country' => !empty( $transport_class->settings['shop_address_country'] ) ? $transport_class->settings['shop_address_country'] : '',
			'city' => !empty( $transport_class->settings['shop_address_city'] ) ? $transport_class->settings['shop_address_city'] : '',
			'street' => !empty( $transport_class->settings['shop_address_street'] ) ? $transport_class->settings['shop_address_street'] : '',
			'postalCode' => !empty( $transport_class->settings['shop_postal_code'] ) ? $transport_class->settings['shop_postal_code'] : '',
		];

		// Shipping additions
		if ( !$label_creation ) {
			$sender['building'] = !empty( $transport_class->settings['shop_building'] ) ? $transport_class->settings['shop_building'] : '';
			$sender['apartment'] = !empty( $transport_class->settings['shop_apartment'] ) ? $transport_class->settings['shop_apartment'] : '';
		}

		$shipping_information = $this->get_order_shipping_information( $order );
                
		$sr_order = [
			'carrier' => $carrier_uc,
			'orderId' => $order->get_id(),
			'recipient' => [
				'name' => $shipping_information['recipient_name'], 
				'phone' => $shipping_information['phone'], 
				'email' => $shipping_information['email'],
			],
			'sender' => $sender,
		];

		// Either label or shipment registration
		$label_creation ? $sr_order['shipmentId'] = $shipment_id : $sr_order['destination'] = [];

		$m_service_type = null;

		if ( $transport_class->carrier === 'omniva' && $transport_class->type === 'atp' ) {
			$m_service_type = 'PA';
		} elseif ( !empty( $transport_class->settings['registerOnPaymentCode'] ) ) {
			$m_service_type = $transport_class->settings['registerOnPaymentCode'];
		}

		if ( $transport_class->type === 'cou' || $carrier_uc === 'LP_EXPRESS_LT' ) {
			$sr_order['destination'] = [
				'postalCode' => $order->get_shipping_postcode(),
				'country' => $order->get_shipping_country(),
				'county' => $order->get_shipping_state(),
				'city' => $order->get_shipping_city(),
				'street' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
			];

			if ( $shipping_class == "courier_smartpost" ) {
				$delivery_time = $order->get_meta( '_delivery_time', true );
				
				if ( $delivery_time ) {
					$sr_order['destination']['timeWindow'] = $delivery_time;
				}
			}
		}

		if ( !empty( $machine_id ) ) {
			$sr_order['destination']['destinationId'] = $machine_id;
		}

		if ( $m_service_type ) {
			$sr_order['services'] = ['serviceType' => $m_service_type];
		}

		return $sr_order;
	}

	/**
	 * Checks geocoding API key with request when key is changed in settings
	 *
	 * @since 3.3.0
	 */
	public function mc_check_api_key() {
		// Test API key with geocode request and random address
		$response = wp_remote_get(
			'https://maps.googleapis.com/maps/api/geocode/json?address=niine,Tallinn&key=' .
			get_option( 'mc_map_geocoding_api_key', '' )
		);

		if ( is_wp_error( $response ) ) {
			$this->add_google_api_key_db_entry( $response->get_error_message() );

			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		// Api key was accepted
		if ( $data->status === "OK" ) {
			// Delete error notice DB entry
			delete_user_meta( get_current_user_id(), 'mc_invalid_google_api_key');

			return;
		}

		$this->add_google_api_key_db_entry( $data->error_message );
	}

	/**
	 * Adds entry into DB to display a notice in admin view
	 *
	 * @since 3.3.0
	 */
	public function add_google_api_key_db_entry( $message ) {
		$user_id = get_current_user_id();

		// Unset the $_GET key to avoid removing the notice at the same time
		unset( $_GET['dismiss_mc_invalid_google_api_key'] );

		if ( !get_user_meta( $user_id, 'mc_invalid_google_api_key' ) ) {
			add_user_meta( $user_id, 'mc_invalid_google_api_key', $message, true );
		} else {
			update_user_meta( $user_id, 'mc_invalid_google_api_key', $message );
		}
	}

	/**
	 * Adds an error to admin notices if the Google Javascript API key is wrong
	 *
	 * @since 3.3.0
	 */
	public function invalid_api_key_notification() {
		$user_id = get_current_user_id();

		// Dismissed then remove value from DB
		if ( isset( $_GET['dismiss_mc_invalid_google_api_key'] ) ) {
			delete_user_meta( $user_id, 'mc_invalid_google_api_key');
			return;
		}

		// If DB does not contain value then do not display the error
		if ( !get_user_meta( $user_id, 'mc_invalid_google_api_key' ) ) {
			return;
		}

		$message = trim( get_user_meta( $user_id, 'mc_invalid_google_api_key', true ) );

		$_GET['dismiss_mc_invalid_google_api_key'] = '1';

		$dismiss_href = add_query_arg( $_GET );

		// Display error
		echo '
		<div class="notice notice-error">
			<p>
				<a style="float: right; " href="' . $dismiss_href . '">' . __( 'Dismiss', 'wc_makecommerce_domain' ) . '</a>
				' . __( 'Unable to use Google Geocoding:', 'wc_makecommerce_domain' ) .
				' "' . $message . '" ' . __( 'Check your app settings or', 'wc_makecommerce_domain' ) . ' ' . '
				<a href=/wp-admin/admin.php?page=wc-settings&tab=advanced&section=mk_api>' .
					__( 'update the key here', 'wc_makecommerce_domain' ) . '
				</a>
			</p>
		</div>';
	}
}
