<?php

namespace MakeCommerce\Shipping;

/**
 * All functionality that has to do with shipping labels and printing
 * 
 * @since 3.0.0
 */

class Label extends \MakeCommerce\Shipping {

	private $loader;
	
	//page format used for creating labels
	public $page_format = "A4";

	/**
	 * Constructs Label class, defines loader and hooks
	 * 
	 * @since 3.0.0
	 */
    public function __construct( \MakeCommerce\Loader $loader ) {
    
		$this->loader = $loader;

		$this->set_print_page_format();
		
		$this->define_hooks();
	}

	/**
	 * Set label print page format
	 * 
	 * @since 3.0.4
	 */
	public function set_print_page_format() {

		$this->page_format = get_option( 'mk_label_format', 'A4' );
	}
	
	/**
	 * Define all wordpress hooks needed for printing stuff and creating labels
	 * 
	 * @since 3.0.0
	 */
    public function define_hooks() {

        $this->loader->add_action( 'woocommerce_order_actions_end', $this, 'print_button' );
		$this->loader->add_action( 'wp_ajax_print_pml', $this, 'print' );
		$this->loader->add_filter( 'admin_action_parcel_machine_print_labels', $this, 'bulk_print' );
		$this->loader->add_filter( 'admin_footer', $this, 'bulk_print_and_register' );
		$this->loader->add_filter( 'admin_action_parcel_machine_labels', $this, 'bulk_register' );
		// HPOS bulk action handling
		$this->loader->add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', $this, 'handle_bulk_action' );
    }

	/**
	 * Creates shipping label using MakeCommerce API and redirect to the page where it is available or return data
	 * 
	 * @since 3.0.0
	 */
    public function create_labels( $post_ids, $ajax = false, $hpos = false ) {

		if ( !is_array( $post_ids ) ) {
			$post_ids = [$post_ids];
		}
        
        $this->initalize();
        
		$shipping_request = ['credentials' => [], 'orders' => [], 'printFormat' => $this->page_format];
		
		foreach ( $this->shipping_methods as $shipping_method ) {
			$shipping_classes_map[$shipping_method["method"]] = $shipping_method["class"];
		}
        
        $missing_ids = [];
        
		foreach ( $post_ids as $post_id ) {
			$order = wc_get_order( $post_id );
			$shipment_id = $order->get_meta( '_parcel_machine_shipment_id', true );
            
			if ( !$shipment_id ) { 
				$missing_ids[] = $post_id;
				continue;
            }

            $shipping_methods = $order->get_shipping_methods();
            
            if ( empty( $shipping_methods ) ) {
				continue;
			}
            
			foreach ( $shipping_methods as $shipping_method ) {

				$shipping_id = explode( ':', $shipping_method['method_id'] );
				$shipping_class = $shipping_id[0];
                $shipping_instance = !empty( $shipping_id[1] ) ? $shipping_id[1] : null;
                
				if ( empty( $shipping_classes_map[$shipping_class] ) ) {
					continue;
				}

				$transport_class = new $shipping_classes_map[$shipping_class]($shipping_instance);
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
					$carrier_uc,
					$shipment_id,
					true,
				);

				if ( !$sr_order ) continue;

				if ( $carrier_uc === 'LP_EXPRESS_LT' ) {
					$identifier = $order->get_meta( '_lp_express_cart_identifier', true );
					$sr_order['lpExpressShipmentDetails']['lpExpressCartIdentifier'] = $identifier;
				}

				$shipping_request['orders'][] = $sr_order;
			}
        }
        
        $shipping_request['credentials'] = array_values( $shipping_request['credentials'] );
        
		if ( empty( $shipping_request['orders'] ) ) {
			return;
        }
        
        $MK = \MakeCommerce::get_api();
        
		if ( !$MK ) {
			return;
        }
        
		try {
			$response = $MK->createLabels( $shipping_request );
		} catch ( \Exception $e ) {

			echo $e->getMessage();
			exit();
        }
        
		if ( empty( $response->labelUrl ) ) {
			return;
        }
        
		if ( $ajax ) {
			return $response->labelUrl;
        }
        
		$mk_err = false;
		if ( !empty( $missing_ids ) ) {
            $mk_err = sprintf( __( 'Orders %s did not have shipment ID attached so no labels was printed! Please register those packages if you think this is an error!', 'wc_makecommerce_domain' ), join( ', ', $missing_ids ) );
        }

		if ( $hpos ) {
			$sendback = add_query_arg( ['page' => 'wc-orders', 'mk_pdf' => urlencode( $response->labelUrl ), 'mk_err' => urlencode( $mk_err ) ], '' );
		} else {
			$sendback = add_query_arg( ['post_type' => 'shop_order', 'mk_pdf' => urlencode( $response->labelUrl ), 'mk_err' => urlencode( $mk_err ) ], '' );
		}

        wp_redirect( esc_url_raw( $sendback ) );
        
        exit();
	}

	/**
	 * Prints labels using ajax function in admin
	 * 
	 * @since 3.0.0
	 */
    public function print() {

		if ( !current_user_can( 'manage_woocommerce' ) ) {
			die();
        }
        
        echo $this->create_labels( [intval( $_POST['id'] )], true );
        
		die();
	}
	
	/**
	 * Prints labels for all selected orders
	 * 
	 * @since 3.0.0
	 */
	public function bulk_print() {

		$post_ids = array_map( 'absint', ( array )$_REQUEST['post'] );

		$this->create_labels( $post_ids );
	}

	/**
	 * Adds bulk shipment register and printing actions to order view
	 * 
	 * @since 3.0.0
	 */
	public function bulk_print_and_register() {

		global $post_type;
		$enqueue = false;

		//Should this even be done using JS???
		if ( 'shop_order' === $post_type ) {

			$args = [
				'shipments_text' => __( 'Register parcel machine shipments', 'wc_makecommerce_domain' ),
				'labels_text'    => __( 'Print parcel machine labels', 'wc_makecommerce_domain' )
			];
			$enqueue = true;
		}
		if ( !empty( $_GET['page'] ) && $_GET['page'] == 'wc-orders' ) {
			$args    = [ 'hpos' => true ];
			$enqueue = true;
		}

		if ( $enqueue ) {

			if ( !empty( $_REQUEST['mk_err'] ) ) {
				$args["error"] = htmlspecialchars( $_REQUEST['mk_err'] );
			}

			if ( !empty( $_REQUEST['mk_pdf'] ) ) {
				$args["pdf"] = $_REQUEST['mk_pdf'];
			}

			\MakeCommerce::mc_enqueue_script( 
				'MC_LABEL_BULK_ACTIONS', 
				dirname( __FILE__ ) . '/js/label_bulk_actions.js', 
				$args, 
				[ 'jquery' ]
			);
		}
    }

	/**
	 * Registers shipments in bulk (admin action)
	 * 
	 * @since 3.0.0
	 */
	public function bulk_register() {

		$post_ids = array_map( 'absint', ( array )$_REQUEST['post'] );

		if ( !empty($post_ids) ) {
			$this->register_shipment( $post_ids );
		}
	}

	/**
	 * Supporting function for print_labels. Creates needed javasript for printing button
	 * 
	 * @since 3.0.0
	 */
    public function print_button( $post_id ) {
        $order = wc_get_order( $post_id );

        if ( !$order->get_meta( '_parcel_machine_shipment_id', true ) ) {
            return;
        }

	    echo '
	    <li class="wide">
		    <a id="print_parcel_machine_label" href="#" class="button">'. __( 'Print parcel label', 'wc_makecommerce_domain' ) .'</a>
		    <img class="mc_loading" src="' . site_url( '/wp-admin/images/loading.gif' ).'">
		</li>';

		\MakeCommerce::mc_enqueue_script( 
			'MC_LABEL_BUTTON', 
			dirname( __FILE__ ) . '/js/print_label.js', 
			[ 
				'post_id' => $post_id, 
				'error' => __( 'Something went wrong generating a label for this order. Please try again! Contact us, if the problem persists.', 'wc_makecommerce_domain' )
			], 
			[ 'jquery' ]
		);
    }

	/**
	 * Handles bulk actions when HPOS is enabled for WooCommerce
	 *
	 * @since 3.4.0
	 */
	public function handle_bulk_action( $page ) {
		$ids = array_map( 'absint', ( array )$_REQUEST['id'] );

		if ( !empty( $ids ) ) {

			if ( $_GET['action'] == 'parcel_machine_register_orders' ) {
				$this->register_shipment( $ids );
			} elseif ( $_GET['action'] == 'parcel_machine_print_labels') {
				$this->create_labels( $ids, false, true );
			}
		}
	}
}
