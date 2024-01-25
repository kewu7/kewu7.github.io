<?php

namespace MakeCommerce\Shipping;

/**
 * All functionality that has to do with shipping and orders
 * 
 * @since 3.0.0
 */

class Order extends \MakeCommerce\Shipping {

    private $loader;

	/**
	 * Constructs Order class, defines hooks
	 * 
	 * @since 3.0.0
	 */
    public function __construct( \MakeCommerce\Loader $loader ) {

        $this->loader = $loader;
    
        $this->define_hooks();
	}
	
	/**
	 * Define all wordpress hooks
	 * 
	 * @since 3.0.0
	 */
    public function define_hooks() {
        $this->loader->add_filter( 'woocommerce_order_details_after_customer_details', $this, 'parcel_machine_details' );
        $this->loader->add_filter( 'woocommerce_admin_order_data_after_shipping_address', $this, 'parcel_machine_changing', 10, 1 );
        $this->loader->add_filter( 'manage_shop_order_posts_custom_column', $this, 'shipping_method_order_view', 3 );
        $this->loader->add_filter( 'woocommerce_email_customer_details_fields', $this, 'shipping_email_details', 10, 3 );
        $this->loader->add_filter( 'restrict_manage_posts', $this, 'filter_orders' );

        // HPOS Add shipping method filtering selectbox to Woo orders page as well
        $this->loader->add_filter( 'woocommerce_order_list_table_restrict_manage_orders', $this, 'hpos_filter_orders' );
        // HPOS Add shipping method filtering functionality
        $this->loader->add_filter( 'woocommerce_order_query_args', $this, 'order_filter_by_shipping_method' );
        // HPOS Add bulk actions
        $this->loader->add_filter( 'bulk_actions-woocommerce_page_wc-orders', $this, 'mc_bulk_actions' );
    }

    /**
     * Add parcel machine information to order view (Customer)
     * 
     * @since 3.0.0
     */
    public function parcel_machine_details( $order ) {

        $machine_id = $order->get_meta( '_parcel_machine', true );
        
		if ( empty( $machine_id ) ) {
            return;
        }

		list( $carrier, $machine ) = explode( '||', $machine_id );
        
        if ( empty( $machine ) ) {
            return;
        }

		$machine = self::mk_get_machine( $carrier, $machine );

        if ( !$machine ) {
            return;
        }

		if ( $carrier == 'lp_express_lt' ) {
			$carrier = "LP Express";
		}

        echo '
        <tr>
            <th>'.__( 'Parcel machine', 'wc_makecommerce_domain' ).' ('.ucfirst( $carrier ).') </th>
		    <td>'.$machine['name'].'<br/>'.$machine['address'].'</td>
        </tr>
        ';
    }

    /**
     * Allow changing parcel machine in admin and show data in order view
     * 
     * @since 3.0.0
     */
    public function parcel_machine_changing( $order ) {

		$machine_id = $order->get_meta( '_parcel_machine', true );
		$carrier = '';
		
		if ( !empty( $machine_id ) ) {

			list( $carrier, $machine ) = explode( '||', $machine_id );
		}
		
		if ( !empty( $machine ) ) {

			$machine = self::mk_get_machine( $carrier, $machine );

			if ( $machine )  {

				echo '
				<div class="edit_address">
					<p class="form-field form-field-wide _mk_machine_id" style="padding-bottom: 10px !important;">
						<label for="_mk_machine_id">'.__( 'Parcel machine', 'wc_makecommerce_domain' ).'</label>
						<select id="_mk_machine_id" name="_mk_machine_id" class="wc-enhanced-select select">
				';

				$machines = self::mk_get_machines( $carrier, method_exists( $order, 'get_shipping_country' ) ? $order->get_shipping_country() : $order->shipping_country , [] );

				echo Method\ParcelMachine::create_parcelmachine_html( $machines, 'no', $machine['id'] );
				
				echo '
						</select>
					</p>
				</div>
				';

				// Add the parcel size templates to LP Express orders
				if ( $carrier === 'lp_express_lt' ) {

					echo '
					<div class="edit_address">
						<p class="form-field form-field-wide _mk_template_id" style="padding-bottom: 10px !important;">
							<label for="_mk_template_id">'.__( 'LP Express template', 'wc_makecommerce_domain' ).'</label>
							<select id="_mk_template_id" name="_mk_template_id" class="wc-enhanced-select select">
					';

					// Get all the presets from API
					$presetArray = \MakeCommerce\Shipping\Method\ParcelMachine\LPExpress::get_lp_express_presets();
					// Default setting
					$default = get_option( 'mk_lpexpress_template' );
					// If order has been edited and a template has been chosen
					if ( !empty( $order->get_meta( '_mk_parcel_template', true ) ) ) {
						$template = $order->get_meta( '_mk_parcel_template', true );
					} else {
						// Otherwise use the default preset
						$template = $default;
					}
					$chosenTemplate = $presetArray[$template];

					$output = '';

					// Add all the presets as html
					foreach ( $presetArray as $key => $label ) {
						$output .= '<option value="'. $key .'" '.( $template == $key ? ' selected="selected"' : '' ).'>'.$label.'</option>';
					}

					echo $output .'
							</select>
						</p>
					</div>
					';


					// Show the chosen template in the order view
					echo '
					<div class="address">
						<p>LP Express</strong><br/>'.$machine['name'] . '<br/><small>' . $machine['address'] . '</small></p>
						<p>Chosen template for LP Express: <strong>'.$chosenTemplate.'</strong></p>';
				} else {
					echo '
					<div class="address">
						<p>'.ucfirst( $carrier ).'</strong><br/>'.$machine['name'] . '<br/><small>' . $machine['address'] . '</small></p>';
				}
			}
		} else {
			//in case of a courier, wrap the time and trackinglink in address div as well
			//to hide it during order editing.
			//can be an empty div in case of other shipping methods
			echo '<div class="address">';
		}

		$shipment_id = $order->get_meta( '_parcel_machine_shipment_id', true );
		$shipment_id_error = $order->get_meta( '_parcel_machine_error', true );
		$shipment_manifest = $order->get_meta( '_parcel_machine_manifest', true );

		$shippingMethod["method_title"] = '';

		//currently the only delivery time option exists for smartpost courier shipping method.
		//only one shipping method, get it easily from array with foreach
		foreach ( $order->get_shipping_methods() as $shippingMethod) {

			if ( $shippingMethod["method_id"] == "courier_smartpost" && !$shipment_id_error ) {
				$delivery_time = $order->get_meta( '_delivery_time', true );
					
				switch ( $delivery_time ) {
					case '1':
						echo '<p><strong>'.__( 'Delivery time', 'wc_makecommerce_domain' ).'</strong> - '.__( 'Any time', 'wc_makecommerce_domain' ).'</p>';
						break;
					case '2':
						echo '<p><strong>'.__( 'Delivery time', 'wc_makecommerce_domain' ).'</strong> - 09:00..17:00</p>';
						break;
					case '3':
						echo '<p><strong>'.__( 'Delivery time', 'wc_makecommerce_domain' ).'</strong> - 17:00..21:00</p>';
						break;
				}
			}
		}
		
		if ( $shipment_id ) {
			$trackinglink = '<p><strong>'.__( 'Shipment tracking code', 'wc_makecommerce_domain' ).'</strong>';

			if ( $carrier == "" ) {
				$carrier = $this->get_order_shipment_carrier( $order );
			}

			if ( $carrier === 'lp_express_lt' ) {
				$shipment_id = $order->get_meta( '_parcel_machine_shipment_barcode', true );
				$carrier = "lpexpress";
			}

			$theLink = $this->get_tracking_link( $carrier, $order, $shipment_id, true );

			if ( $carrier != "" && $theLink != "" ) {
				$trackinglink .= ' ('. ucfirst( $shippingMethod["method_title"] ) .'): <br/> <a target="_blank" href="' . $theLink . '">' . $shipment_id . '</a>';
			} else {
				$trackinglink .= ': <br/>'.$shipment_id;
			}

			echo $trackinglink.'</p>';
		}
		
		if ( $shipment_manifest ) {
			echo '<p><strong>'.__( 'Shipments pickup manifest' ).':</strong><br/><a href="' . $shipment_manifest . '" target="_blank">link</a></small></p>';
		}

		if ( $shipment_id_error ) {
			echo '<p><strong style="color: red;">'.__( 'Shipment registration error' ).':</strong><br/>' . $shipment_id_error . '</small></p>';
		}

		echo '</div>';
    }

    /**
     * Add shipping information on order list view
     * 
     * @since 3.0.0
     */
    public function shipping_method_order_view( $column ) {

        global $post, $woocommerce, $the_order;

        if ( !$the_order ) {
            return;
        }

        $the_order_id = $the_order->get_id();

        if ( empty( $the_order ) || $the_order_id != $post->ID ) {

            $the_order = wc_get_order( $post->ID );
        }

        if ( $column === 'shipping_address' ) {

            $shipment_id = $the_order->get_meta( '_parcel_machine_shipment_id', true );
            $shipment_id_error = $the_order->get_meta( '_parcel_machine_error', true );
            $identifier = $the_order->get_meta( '_lp_express_cart_identifier', true );
            $barcode = $the_order->get_meta( '_parcel_machine_shipment_barcode', true );

            // LP Express
            if ( !empty( $identifier ) && !empty( $barcode ) ) {
                $shipment_id = $barcode;
            }

            if ( $shipment_id ) {

                echo __( 'Shipment tracking code', 'wc_makecommerce_domain' ) . ': ' . $shipment_id . '<br/>';
                echo '<span class="has_shipment_id"></span>';
            } else if ( $shipment_id_error ) {
                echo '<span style="color: red;">'.__( 'Package shipment generation error:', 'wc_makecommerce_domain' ) . '</span><br/>' .$shipment_id_error;
            }
        }
    }

    /**
	 * Fires before the Filter button on the Posts and Pages list tables.
	 * Allows filtering of orders by shipping method
	 * 
	 * @since 3.0.0
	 */
    public function filter_orders() {

		global $typenow;
		
        if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ) ) ) {

			$selected_method = !empty( $_REQUEST['_shipping_method'] ) ? $_REQUEST['_shipping_method'] : false;
			$methods = WC()->shipping->load_shipping_methods();

			echo '<select name="_shipping_method" id="shipping_type" class="enhanced">';
			echo '<option value="">'.__( '-- filter by shipping method', 'wc_makecommerce_domain' ) . '</option>';

			foreach ( $methods as $method ) {
				echo '<option value="'.$method->id.'"'.($selected_method === $method->id ? ' selected="selected"' : '').'>'.$method->get_method_title().'</option>';
			}

			echo '</select>';
		}
	}

	/**
	 * Fires before the Filter button on the orders page
	 * Allows filtering of orders by shipping method
	 *
	 * @since 3.4.0
	 */
	public function hpos_filter_orders() {

		if ( $_GET['page'] == 'wc-orders' ) {

			$selected_method = !empty( $_REQUEST['_shipping_method'] ) ? $_REQUEST['_shipping_method'] : false;
			$methods = WC()->shipping->load_shipping_methods();

			echo '<select name="_shipping_method" id="shipping_type" class="enhanced">';
			echo '<option value="">'.__( '-- filter by shipping method', 'wc_makecommerce_domain' ) . '</option>';

			foreach ( $methods as $method ) {
				// Can not filter by methods which are not provided by MK
				if ( !$method instanceof \MakeCommerce\Shipping\Method ) continue;
				echo '<option value="'.$method->id.'"'.($selected_method === $method->id ? ' selected="selected"' : '').'>'.$method->get_method_title().'</option>';
			}

			echo '</select>';
		}
	}
	
	/**
	 * Returns order shipment carrier
	 * 
	 * @since 3.0.0
	 */
	private function get_order_shipment_carrier( $order ) {

		foreach ( $order->get_shipping_methods() as $shippingMethod ) {
			$carrier = explode( "_", $shippingMethod["method_id"] );

			$carrier = $carrier[1];
		}

		return $carrier;
	}

    /**
     * Add shipment data to email
     * 
     * @since 3.0.0
     */
    public function shipping_email_details( $fields, $sent_to_admin, $order ) {
		// No mc metadata without calling the wc_get_order again
		$order = wc_get_order( $order->get_id() );

		//check if makecommerce shipping has been used
		//add tracking information (if possible)
		$shipment_id = $order->get_meta( "_parcel_machine_shipment_id", true );

		$machine_id = $order->get_meta( '_parcel_machine', true );

		$machine = "";
		$carrier = "";
		
        $machineIdArr = explode( '||', $machine_id );
        
		if ( count( $machineIdArr ) == 2 ) {

			$carrier = $machineIdArr[0];
			$machine = $machineIdArr[1];
		} else if ( count( $machineIdArr ) == 1 ) {

			$carrier = $machineIdArr[0];
		}
		
		$machine = self::mk_get_machine( $carrier, $machine );

		//do something about the language only if we even have something to add to the email
		if ( $shipment_id || ( $machine != "" && $carrier != "" ) ) {

			//this only needs to be done for polylang. Don't do it for wpml, that won't work.
			if ( \MakeCommerce\i18n::is_polylang_active() ) {

				$orderLang = get_locale();

				if ( $orderLang != "" ) {

					if ( substr( $orderLang, 0 ,2 ) == "en" ) {

						switch_to_locale( "et" );
						do_action( 'wpml_switch_language', "et" );
					} else {

						switch_to_locale( "en_GB" );
						do_action( 'wpml_switch_language', "en_GB" );
					}

					switch_to_locale( $orderLang );
					do_action( 'wpml_switch_language', $orderLang );
				}

				load_plugin_textdomain( 'wc_makecommerce_domain', false, '/makecommerce/languages/' );
			}
		}

		if ( $machine != "" && $carrier != "" ) {
			//add parcel machine name/location
			if ( strtolower( $carrier ) == 'lp_express_lt') {
				$fields[] = array( 'label' => __( 'Parcel machine', 'wc_makecommerce_domain' ).' (LP Express)', 'value' => $machine['name'].' - '.$machine['address']);
			} else {
				$fields[] = array( 'label' => __( 'Parcel machine', 'wc_makecommerce_domain' ).' ('.ucfirst( $carrier ).')', 'value' => $machine['name'].' - '.$machine['address']);
			}
		}

		$shipment_id_error = $order->get_meta( '_parcel_machine_error', true );
		
		//currently the only delivery time option exists for smartpost courier shipping method.
		foreach ( $order->get_shipping_methods() as $shippingMethod ) {

			if ( $shippingMethod["method_id"] == "courier_smartpost" ) {
                $delivery_time = $order->get_meta( '_delivery_time', true );
                
                if ( !$shipment_id_error ) {

                    if ( $delivery_time === '1' ) {

                        $fields[] = array( 'label' => __( 'Delivery time', 'wc_makecommerce_domain' ), 'value' => __( 'Any time', 'wc_makecommerce_domain' ) );
                    }

                    if ( $delivery_time === '2' ) {

                        $fields[] = array( 'label' => __( 'Delivery time', 'wc_makecommerce_domain' ), 'value' => "09:00..17:00" );
                    }

                    if ( $delivery_time === '3' ) {

                        $fields[] = array( 'label' => __( 'Delivery time', 'wc_makecommerce_domain' ), 'value' => "17:00..21:00" );
                    }
                }
			}
		}

		//add tracking information (if possible)
		if ( $shipment_id && strtolower( $carrier ) !== "lp_express_lt" ) {

			if ( $carrier == "" ) {
				$carrier = $this->get_order_shipment_carrier( $order );
			}

			$href = $this->get_tracking_link( $carrier, $order, $shipment_id );

			if ( $href != "" ) {
				$fields[] = array( 'label' => __( 'Shipment tracking info', 'wc_makecommerce_domain' ).' ('.$carrier.')', 'value' => "<a target='_blank' title='".__( 'Shipment tracking code', 'wc_makecommerce_domain' )."' href='".$href."'>".$href."</a>" );
			} else {
				$fields[] = array( 'label' => __( 'Shipment tracking info', 'wc_makecommerce_domain' ), 'value' => $shipment_id );
			}
		}

		return $fields;
    }

	/**
	 * Adds bulk actions to orders page without JS
	 * Works with HPOS enabled
	 *
	 * @since 3.4.0
	 */
	public function mc_bulk_actions( $actions ) {
		$actions['parcel_machine_register_orders'] =  __( 'Register parcel machine shipments', 'wc_makecommerce_domain' );
		$actions['parcel_machine_print_labels'] =  __( 'Print parcel machine labels', 'wc_makecommerce_domain' );
		return $actions;
	}

	/**
	 * Filters orders by _mc_shipping_method meta key
	 * Relies on hpos_filter_orders()
	 * Works with HPOS enabled
	 *
	 * @since 3.4.0
	 */
	public function order_filter_by_shipping_method( $args ) {

		if ( is_admin() && !empty( $_REQUEST['_shipping_method'] ) && $_GET['page'] == 'wc-orders' ) {
			$shipping_method = sanitize_text_field( $_REQUEST['_shipping_method'] );

			$args['meta_query'] = [
				[
					'key' => '_mc_shipping_method',
					'value' => $shipping_method
				]
			];
		}

		return $args;
	}
}
