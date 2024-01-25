<?php

namespace MakeCommerce\Payment\Gateway;

/**
 * Simplecheckout payment method
 * handles admin settings and overrules default woocommerce checkout
 * 
 * @since 3.0.0
 */

class Simplecheckout extends \MakeCommerce\Payment\Gateway {

    public $id = 'makecommerce_sc';
    public $method_title = "Simple Checkout (MC)";

    /**
     * Set gateway specific hooks/filters
     * 
     * @since 3.0.0
     */
    public function set_gateway_hooks() {

        add_action( 'woocommerce_before_checkout_form', array( $this, 'take_over_checkout' ), 10, 1 );
        add_action( 'query_vars', array( $this, 'return_triggers' ) );
        add_action( 'template_redirect', array( $this, 'return_trigger_check' ) );
        add_action( 'woocommerce_before_cart', array( $this, 'cart_scripts' ) );
    }

    /**
     * Variables that trigger return check
     * 
     * @since 3.0.0
     */
    public function return_triggers( $vars ) {

		$vars[] = 'mc_cart_to_order';
		$vars[] = 'mc_calculate_shipment';
        $vars[] = 'mc_cart_update';
        
		$vars[] = 'mc_cart_id';
		$vars[] = 'mc_nonce';
		$vars[] = 'lang1';

		return $vars;
    }
    
    /**
     * Process return from payment, also handles cart updates among other things
     * 
     * @since 3.0.0
     */
    public function return_trigger_check() {

		if (intval(get_query_var('mc_cart_to_order')) === 1) {
            $this->cart_to_order();
        }
        
        //calculate shipment cost
		if (intval(get_query_var('mc_calculate_shipment')) === 1) {
			$this->calculate_shipment_cost();
        }
        
        //cart update
		if ( intval( get_query_var( 'mc_cart_update' ) ) > 0 ) {
            $this->update_cart();			
		}
    }
    
    /**
     * Returns cart
     * 
     * @since 3.0.0
     */
    private function get_cart( $cart_info ) {

        global $wpdb;

        $cart = $wpdb->get_row( "
        SELECT 
            `content`, 
            `order_id` 
        FROM 
            `" . $wpdb->prefix . MAKECOMMERCE_SCO_TABLENAME . "`
        WHERE 
            `cart_id` = '".$cart_info->cartId."'
        " );

        if ( !$cart ) {
            error_log( 'no such cart' );
            echo json_encode( array( 'code' => -1 ) );

            exit;
        }

        return $cart;
    }

    /**
     * Check for MITM manipulations
     * 
     * @since 3.0.0
     */
    private function check_mitm( $cart_info ) {

        global $wpdb;

        $tmp_order_sco_data = $wpdb->get_row( "
        SELECT 
            `content`, 
            `order_id` 
        FROM 
            `" . $wpdb->prefix . MAKECOMMERCE_SCO_TABLENAME . "` 
        WHERE 
            `cart_id` = '".$cart_info->cartId . "_provided_sco_data'
        " );

        if ( !$tmp_order_sco_data ) {

            error_log( 'Missing sco provided data for cart' );
            echo json_encode( array( 'code' => -1 ) );

            exit;
        } else {

            $matching_shipment_method_found = false;
            $provided_cart_data = json_decode( $tmp_order_sco_data->content );
            
            if ( !empty( $provided_cart_data->shipmentMethods ) ) {
                foreach ( $provided_cart_data->shipmentMethods as $p_shipment_method ) {

                    //check to see if the selected shipment has a matching cart id and amount.
                    if ( $p_shipment_method->methodId == $cart_info->shipmentMethod->methodId && $p_shipment_method->amount == $cart_info->shipmentMethod->amount ) {
                        $matching_shipment_method_found = true;
                    }
                }
            } else { //no shipment methods. Most likely a virtual product
                $matching_shipment_method_found = true;
            }

            //response option does not match with provided sco data options. Either something went terribly wrong or someone manipulated with the data. Cancel payment
            if ( !$matching_shipment_method_found ) {
                
                error_log( 'Cart shipping method with matching price not found in sco_provided_data' );
                header( "HTTP/1.1 400 Bad Request" );

                exit;
            }
        }

        //cleanup sco mitm table. Delete all rows older than 24 hours
        $wpdb->query( "
        DELETE 
        FROM 
            `" . $wpdb->prefix . MAKECOMMERCE_SCO_TABLENAME . "` 
        WHERE 
            `modified` < '" . (time() - 86400) . "'
        ");
    }

    /**
     * Turns cart into order
     * 
     * @since 3.0.0
     */
    private function cart_to_order() {

        $cart_info = json_decode( file_get_contents( 'php://input' ) );
        
        //change language if it is set.
        if ( isset( $_GET["lang1"] ) ) {
            \MakeCommerce\i18n::switch_language( $_GET["lang1"] );
        }
        
        $this->tmp_order = $this->get_cart( $cart_info );
        
        //check for mitm manipulations
        $this->check_mitm( $cart_info );

        $free_shipping = false;

        $tmp_cart = new \WC_Cart();

        $this->tmp_order->content = json_decode($this->tmp_order->content);

        $content = !empty( $this->tmp_order->content->order ) ? $this->tmp_order->content->order : $this->tmp_order->content;

        //add all items to cart
        foreach ( $content as $item_row ) {
            $tmp_cart->add_to_cart( $item_row->id, $item_row->qty );
        }

        //order already created.
        if ( $this->tmp_order->order_id ) {

            $this->order = new \WC_Order( $this->tmp_order->order_id );
        } else { //create new order

            $this->order = wc_create_order();

            $this->add_products_to_order( $content );
            
            $this->order->calculate_totals();
            
            $free_shipping = $this->free_shipping();
        }

        $this->order->set_payment_method( new WooCommerce() );

        $billing_address = array(
            'first_name' => $cart_info->customer->firstname,
            'last_name'  => $cart_info->customer->lastname,
            'email'      => $cart_info->customer->email,
            'phone'      => $cart_info->customer->phone,
            'company'    => ''
        );
        
        if ( !empty( $cart_info->invoiceAddress ) ) {
            $billing_address = $this->set_billing_address( $billing_address, $cart_info->invoiceAddress );
        }

        $shipment_address = array();
        if ( !empty( $cart_info->shipmentAddress ) ) {

            $shipment = $cart_info->shipmentAddress;
            $shipment_address = $this->set_shipment_address( $shipment );
        }

        $this->order->set_address( $billing_address, 'billing' );

        if ( empty( $shipment_address ) ) {
            $this->order->set_address( $billing_address, 'shipping' );
        } else {
            $this->order->set_address( $shipment_address, 'shipping' );
        }

        $shipment_method = $this->get_shipment_method( $cart_info, $shipment );
        
        $this->order->remove_order_items('shipping');

        if ( $shipment_method ) {

            $package = $tmp_cart->get_shipping_packages();

            if ( !empty( $package ) ) {
                $package = $package[0];
            }

            $price_int = $shipment_method->get_rates_for_package( $package );
            $price_int = \MakeCommerce\Payment::get_rate_without_taxes( $shipment_method->id.':'.$shipment_method->instance_id, $price_int );
            $price = !empty( $cart_info->shipmentMethod->amount ) ? ( double )$cart_info->shipmentMethod->amount : 0;

            if ( $free_shipping ) {
                $price = $price_int = 0;
            }

            $rate = new \WC_Shipping_Rate(
                $shipment_method->id.':'.$shipment_method->instance_id,
                !empty( $shipment_method->name_ext ) ? $shipment_method->name_ext : $shipment_method->title,
                $price_int,
                array(),
                $shipment_method->id.':'.$shipment_method->instance_id
            );

            if ( class_exists( '\WC_Order_Item_Shipping' ) ) {

                $item = new \WC_Order_Item_Shipping();
                $item->set_order_id( $this->order->get_id() );
                $item->set_shipping_rate( $rate );
                $shipping_id = $item->save();
            } else {
                $this->order->add_shipping( $rate );
            }

            if ( !empty( $shipment_method->type ) && $shipment_method->type === 'apt' ) {
                
                $machine = \MakeCommerce\Shipping::mk_get_machine( strtolower( $shipment_method->carrier ), $shipment->destinationId );
                if ( $machine ) {
                    
                    update_post_meta( $this->order->get_id(), '_shipping_first_name', get_post_meta( $this->order->get_id(), '_billing_first_name', true ) );
                    update_post_meta( $this->order->get_id(), '_shipping_last_name', get_post_meta( $this->order->get_id(), '_billing_last_name', true ) );
                    update_post_meta( $this->order->get_id(), '_shipping_address_1', sanitize_text_field( $machine['name'] ) );
                    update_post_meta( $this->order->get_id(), '_shipping_address_2', sanitize_text_field( $machine['address'] ) );
                    update_post_meta( $this->order->get_id(), '_shipping_city', sanitize_text_field( $machine['city'] ) );
                    update_post_meta( $this->order->get_id(), '_shipping_postcode', '' );
                    update_post_meta( $this->order->get_id(), '_parcel_machine', strtolower( $shipment_method->carrier ).'||'.$shipment->destinationId );
                }
            }
        }

        update_post_meta( $this->order->get_id(), '_makecommerce_sc_cart_id', $cart_info->cartId );

        $this->order->calculate_totals();

        if ( !empty( $this->tmp_order->content->discount ) ) {
            $this->order->set_discount_total( $this->tmp_order->content->discount );
        }

        if ( !empty( $this->tmp_order->content->discount_tax ) ) {
            $this->order->set_discount_tax( $this->tmp_order->content->discount_tax );
        }

        $this->order->set_total( $this->order->get_total() - ( $this->order->get_discount_total() + $this->order->get_discount_tax() ) );
        $this->order->save();

        global $wpdb;
        $wpdb->update( $wpdb->prefix . MAKECOMMERCE_SCO_TABLENAME, array( 'status' => 1, 'order_id' => $this->order->get_id(), 'modified' => time() ), array( 'cart_id' => $cart_info->cartId ) );
        
        header( 'Content-type: application/json' );
        echo $this->create_json_response( $cart_info->cartId, ( string )$this->order->get_order_number() );

        exit;
    }

    /**
     * Returns shipment method
     * 
     * @since 3.0.0
     */
    private function get_shipment_method( $cart_info, $shipment ) {

        if ( !empty( $cart_info->shipmentMethod ) ) {

            $shipment_details = $cart_info->shipmentMethod;
            $zones = \WC_Shipping_Zones::get_zones();
            $continents = WC()->countries->get_continents();

            $supported_methods = array(
                'parcelmachine_omniva' => 'APT',
                'parcelmachine_smartpost' => 'APT',
                'parcelmachine_dpd' => 'APT',
                'courier_omniva' => 'COU',
                'courier_smartpost' => 'COU',
                'local_pickup' => 'OTH',
                'flat_rate' => 'COU'
            );

            foreach ( $zones as $zone ) {
                foreach ( $zone['shipping_methods'] as $method ) {

                    //skip if not one of the supported methods
                    if ( empty( $supported_methods[$method->id] ) ) {
                        continue;
                    }

                    if ( $supported_methods[$method->id] === $shipment_details->type && ( empty( $shipment_details->carrier ) || strtoupper( $method->carrier ) === $shipment_details->carrier ) ) {
                        foreach ( $zone['zone_locations'] as $location ) {
                            if ( $location->type === 'continent' && !empty( $continents[$location->code] ) ) {

                                if ( in_array( $shipment->country, $continents[$location->code]['countries'] ) ) {
                                    return $method;
                                }
                            } else if ( $location->type === 'state' ) {
                                
                                list( $country, $state ) = explode( ':', $location->code );
                                if ( $country === $shipment->country ) {
                                    return $method;
                                }
                            } else if ( $shipment->country === $location->code ) {
                                return $method;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Set shipment address
     * 
     * @since 3.0.0
     */
    private function set_shipment_address( $shipment ) {

        if ( !empty( $shipment->country ) ) {
            $shipment_address['country'] = $shipment->country;
        }

        if ( !empty( $shipment->county ) ) {
            $shipment_address['state'] = $shipment->county;
        }

        if ( !empty( $shipment->city ) ) {
            $shipment_address['city'] = $shipment->city;
        }

        if ( !empty( $shipment->street1 ) ) {
            $shipment_address['address_1'] = $shipment->street1;
        }

        if ( !empty( $shipment->street2 ) ) {
            $shipment_address['address_2'] = $shipment->street2;
        }

        if ( !empty( $shipment->postalCode ) ) {
            $shipment_address['postcode'] = $shipment->postalCode;
        }

        if ( !empty( $shipment->firstname ) ) {
            $shipment_address['first_name'] = $shipment->firstname;
        }

        if ( !empty( $shipment->lastname ) ) {
            $shipment_address['last_name'] = $shipment->lastname;
        }

        return $shipment_address;
    }

    /**
     * Set billing address data
     * 
     * @since 3.0.0
     */
    private function set_billing_address( $billing_address,  $invoice ) {

        if ( !empty( $invoice->firstname ) ) {
            $billing_address['first_name'] = $invoice->firstname;
        }
        
        if ( !empty( $invoice->lastname ) ) {
            $billing_address['last_name'] = $invoice->lastname;
        }

        if ( !empty( $invoice->country ) ) {
            $billing_address['country'] = $invoice->country;
        }

        if ( !empty( $invoice->county ) ) {
            $billing_address['state'] = $invoice->county;
        }

        if ( !empty( $invoice->city ) ) {
            $billing_address['city'] = $invoice->city;
        }

        if ( !empty( $invoice->street1 ) ) {
            $billing_address['address_1'] = $invoice->street1;
        }

        if ( !empty( $invoice->street2 ) ) {
            $billing_address['address_2'] = $invoice->street2;
        }

        if ( !empty( $invoice->postalCode ) ) {
            $billing_address['postcode'] = $invoice->postalCode;
        }

        if ( !empty( $invoice->legalName ) ) {
            $billing_address['company'] .= $invoice->legalName;
        }

        if ( !empty( $invoice->registryCode ) ) {
            $billing_address['company'] .= ' ' . $invoice->registryCode;
        }
        
        if ( !empty( $invoice->vatNum ) ) {
            $billing_address['company'] .= ' ' . $invoice->vatNum;
        }

        return $billing_address;
    }
    
    /**
     * Add products to newly created order.
     * 
     * @since 3.0.0
     */
    private function add_products_to_order( $content ) {

        foreach ( $content as $item_row ) {
                        
            $product_id = !empty( $item_row->var ) ? $item_row->var : $item_row->id;
            
            if ( function_exists( 'wc_get_product' ) ) {
                $item_id = $this->order->add_product( wc_get_product( $product_id ), $item_row->qty );
            } else {
                $item_id = $this->order->add_product( get_product( $product_id ), $item_row->qty );
            }
        }
    }

    /**
     * Check if order has free shipping (coupons)
     * 
     * @since 3.0.0
     */
    private function free_shipping() {

        $free_shipping = false;

        $coupons = !empty( $this->tmp_order->content->coupons ) ? $this->tmp_order->content->coupons : array();
        
        foreach ( $coupons as $coupon ) {

            $coupon = new \WC_Coupon( $coupon );
            $amount = $coupon->get_amount();

            if ( $coupon->is_type( 'percent' ) ) {
                $amount = $this->order->get_total() / 100 * $amount;
            }

            if ( method_exists( $this->order, 'add_item' ) ) {

                $item = new \WC_Order_Item_Coupon();
                
                $item->set_props( array(
                    'code' => $coupon->get_code(),
                    'discount' => $amount,
                    'discount_tax' => 0
                ) );

                $this->order->add_item( $item );
            }
            
            if ( ( method_exists( $coupon, 'get_free_shipping' ) && $coupon->get_free_shipping() ) || $coupon->free_shipping ) {
                $free_shipping = true;
            }
        }

        return $free_shipping;
    }

    /**
     * Creates json response for SCO
     * 
     * @since 3.0.0
     */
    private function create_json_response( $cart_id, $order_id ) {
        
        if ( isset( $_GET["lang1"] ) ) {
            $locale = $_GET["lang1"];
        } else {
            $locale = \MakeCommerce\I18n::get_two_char_locale();
        }

        $response = array(
            'cartId' => $cart_id,
            'reference' => $order_id,
            'locale' => $locale,
            'transactionUrl' => array(
                'returnUrl' => array(
                    'url' => site_url( '/?mc_cart_update=1&lang1='.$locale ),
                    'method' => 'POST'
                ),
                'cancelUrl' => array(
                    'url' => site_url( '/?mc_cart_update=2&lang1='.$locale ),
                    'method' => 'POST'
                ),
                'notificationUrl' => array(
                    'url' => site_url( '/?mc_cart_update=3&lang1='.$locale ),
                    'method' => 'POST'
                ),
            )
        );

        return json_encode($response);
    }

    /**
     * This function is used to calculate shipment cost in SCO
     * Only used for courier and depends on address customers enter
     * 
     * Currently not implemented.
     * 
     * This functionality would work only when we dont send shipping methods and their cost to SCO. When you remove courier price then SCO should come to this function to get shipment price
     * 
     * When some day implemented, also keep in mind the MITM check. Currently it would either fail or not work as intended.
     * 
     * @since 3.0.0
     */
    private function calculate_shipment_cost( $cart_info ) {

        throw new \Exception("Functionality not implemented yet.");

        /*
        Functionality from old code. Returned 2.99 hardcoded.
        $cart_info = json_decode(file_get_contents('php://input'));
        header('Content-type: application/json');
        echo json_encode(array('amount' => 2.99));
        */
    }

    /**
     * Update cart. Checks payment
     * 
     * @since 3.0.0
     */
    private function update_cart() {

        $return_url = \MakeCommerce\Payment::check_payment();
        
        if ( isset( $_GET["lang1"] ) ) {

            $return_url .= "&lang1=".$_GET["lang1"];
            \MakeCommerce\i18n::switch_language( $_GET["lang1"] );
        }

        if ( intval( get_query_var( 'mc_cart_update' ) ) === 3 ) {
            echo json_encode( array( 'redirect' => $return_url ) );
        } else {
            wp_redirect( $return_url );
        }

        exit;
    }

    /**
     * Hides shipping methods from cart if option checked
     * 
     * @since 3.0.0
     */
    private function hide_shipping_methods() {

        if ( !empty( $this->settings['hide_shipping_methods'] ) && $this->settings['hide_shipping_methods'] === 'yes' ) {

            echo '
            <style>
            .shipping,.order-total {
                display:none;
            }
            
            .tax-rate {
                display:none;
            }
            </style>
            ';
        }
    }

    /**
     * Run scripts needed on cart view for SCO
     * 
     * @since 3.0.0
     */
    public function cart_scripts() {

        //remember cart updates in browser history
        wp_enqueue_script( "simplecheckout-cart-scripts", plugin_dir_url(__FILE__) . 'js/cart-scripts.js', array( 'jquery' ), MAKECOMMERCE_VERSION );

        //hide shipping methods if needed
		$this->hide_shipping_methods();
	}
    
    /**
     * Overrides checkout from WooCommerce
     * 
     * @since 3.0.0
     */
    public function take_over_checkout( $checkout ) {

        global $woocommerce, $wpdb;

        //exit when guest checkout is enabled but user is not logged in. SCO is not possible in this scenario
        if ( get_option( 'woocommerce_enable_guest_checkout' ) !== 'yes' && !is_user_logged_in() ) {

            wc_add_notice( __( 'You have to log in to continue to checkout', 'wc_makecommerce_domain' ), 'error');
            wp_redirect( $woocommerce->cart->get_cart_url() );

            exit;
        }

        $data = array(
            'order' => array(), 
            'coupons' => $woocommerce->cart->get_applied_coupons(), 
            'discount' => $woocommerce->cart->get_cart_discount_total(),
            'discount_tax' => $woocommerce->cart->get_cart_discount_tax_total()
        );

        //do we have free shipping?
        $free_shipping = false;
        foreach ( $woocommerce->cart->get_applied_coupons() as $coupon ) {

            $coupon = new \WC_Coupon( $coupon );
            if ( ( method_exists( $coupon, 'get_free_shipping' ) && $coupon->get_free_shipping() ) || $coupon->free_shipping ) {
                $free_shipping = true;
            }
        }

        $qty = 0; $amount = 0;
        $cart = $woocommerce->cart->get_cart();
        foreach ( $cart as $cart_item ) {
            
            $data['order'][] = array( 'id' => $cart_item['product_id'], 'qty' => $cart_item['quantity'], 'var' => $cart_item['variation_id'] );
            $qty += $cart_item['quantity'];
            $amount += $cart_item['line_total'] + $cart_item['line_tax'];
        }

        if ( count( $data ) > 0 ) {

            $wpdb->insert( $wpdb->prefix . MAKECOMMERCE_SCO_TABLENAME, array(
                'created' => time(),
                'modified' => time(),
                'status' => 0,
                'content' => json_encode( $data )
            ) );

            $locale = \MakeCommerce\i18n::get_two_char_locale();
            
            $tmp_order_id = $wpdb->insert_id; 
            $cart_to_order_url = site_url( '/?mc_cart_to_order=1&lang1='.$locale );
            $calculate_shipment_url = site_url( '/?mc_calculate_shipment=1&lang1='.$locale );
            
            //set correct tos url.
            if ( !empty( $this->settings['shop_tos_url_'.$locale] ) ) {
                $tos_url = $this->settings['shop_tos_url_'.$locale];
            } else {
                if ( !empty( $this->settings['shop_tos_url'] ) ) {
                    $tos_url = $this->settings['shop_tos_url'];
                } else {
                    $tos_url = site_url();
                }
            }

            $data = array(
                'cartRef' => 'PreOrder '.$tmp_order_id,
                'pluginUrls' => array(
                    'cartToOrder' => $cart_to_order_url,
                    'calculateShipment' => $calculate_shipment_url,
                    'tos' => $tos_url
                ),
                'amount' => sprintf( "%.2f", round( max( $amount, 0.01 ), 2 ) ),
                'currency' => 'EUR',
                'sourceCountry' => WC()->countries->get_base_country(),
                'locale' => $locale,
                'shipmentMethods' => array()
            );

            $package = $woocommerce->cart->get_shipping_packages();

            if ( !empty( $package ) ) {
                $package = $package[0];
            }
            
            $zones = array();

            $zone   = new \WC_Shipping_Zone(0);
            $zones[ $zone->get_id() ] = $zone->get_data();
            $zones[ $zone->get_id() ]['formatted_zone_location'] = $zone->get_formatted_location();
            $zones[ $zone->get_id() ]['shipping_methods'] = $zone->get_shipping_methods();
            $zones = array_merge( $zones, \WC_Shipping_Zones::get_zones() );

            $continents = WC()->countries->get_continents();
            
            $method_country_x = array();
            
            $supported_methods = array(
                'parcelmachine_omniva' => 'APT',
                'parcelmachine_smartpost' => 'APT',
                'parcelmachine_dpd' => 'APT',
                'courier_omniva' => 'COU',
                'courier_smartpost' => 'COU',
                'local_pickup' => 'OTH',
                'flat_rate' => 'COU'
            );

            foreach ( $zones as $zone ) {

                foreach ( $zone['shipping_methods'] as $method_key => $method ) {

                    //ship if shipping method isnt enabled
                    if ( $method->enabled !== 'yes' ) {
                        continue;
                    }

                    $carrier = array( 'countries' => array() );

                    if ( !empty( $supported_methods[$method->id] ) ) {

                        //skip shipping methid if it is not available for a package
                        foreach ( $woocommerce->cart->get_shipping_packages() as $package ) {
                            if ( !$method->is_available( $package ) ) {
                                continue( 2 );
                            }
                        }
                        
                        $method_type = $supported_methods[$method->id];
                        
                        if ( empty( $method_country_x[$method_type] ) ) {
                            $method_country_x[$method_type] = array();
                        }

                        if ( !empty( $method->carrier ) ) {
                            $carrier['carrier'] = mb_strtoupper( $method->carrier );
                        }

                        if ( empty( $zone['zone_locations'] ) ) {
                            $carrier['countries'] = array_keys( WC()->countries->get_allowed_countries() );
                        } else {
                            foreach ( $zone['zone_locations'] as $location ) {

                                if ( $location->type === 'continent' && !empty( $continents[$location->code] ) ) {
                                    $countries = array_diff( $continents[$location->code]['countries'], $method_country_x[$method_type] );
                                    $carrier['countries'] = array_merge( $carrier['countries'], $countries );
                                } else if ( $location->type === 'state' ) {
                                    list( $country, $state ) = explode( ':', $location->code );
                                    $carrier['countries'][] = $country;
                                } else if ( $location->type === 'country' ) {
                                    $carrier['countries'][] = $location->code;
                                }
                            }
                        }

                        $method_country_x[$method_type] = array_merge( $method_country_x[$method_type], $carrier['countries'] );
                        $prices = $method->get_rates_for_package( $package );
                        $price = ($free_shipping && $method->instance_settings["allow_free_shipping_coupons"] == "yes") ? 0.00 : \MakeCommerce\Payment::get_rate_with_taxes( $method->id.':'.$method_key, $prices );
                        $carrier['type'] = strtoupper( $method_type );
                        $carrier['name'] = $method->title;
                        $carrier['methodId'] = $method->id . ':' . $method_key;
                        $carrier['amount'] = sprintf( "%.2f", round( $price, 2 ) );
                        $data['shipmentMethods'][] = $carrier;
                    }
                }
            }

            $cart = $this->MK->createCart( $data );
            $wpdb->update( $wpdb->prefix . MAKECOMMERCE_SCO_TABLENAME, array( 'cart_id' => $cart->id ), array( 'id' => $tmp_order_id ) );

            //add wp meta of the data provided to SCO. This way we can later check if the data values and so on were actually provided by us or the data has been manipulated by a mitm
            $wpdb->insert( $wpdb->prefix . MAKECOMMERCE_SCO_TABLENAME, array(
                'cart_id' => $cart->id."_provided_sco_data",
                'created' => time(),
                'modified' => time(),
                'status' => 0,
                'content' => json_encode( $data )
            ) );

            wp_redirect( $cart->scoUrl );

            exit;
        }

        die('no content');
    }

    /**
     * Loads all form fields specific to this payment gateway
     * 
     * @since 3.0.0
     */
    public function initialize_gateway_type_form_fields() {

        $this->form_fields['scointro'] = array(
            'type' => 'title', 
            'description' => __( 'SimpleCheckout replaces Woocommerce built-in check-out dialog with Makecommerce hosted dialog. It is more convenient and faster for your customers', 'wc_makecommerce_domain').'<br>'.__('See more about SimpleCheckout on: <a target=_blank href="https://makecommerce.net/simplecheckout/">makecommerce.net/simplecheckout</a>', 'wc_makecommerce_domain' ),
        );

        $this->form_fields['active'] = array(
            'title' => __( 'Enable/Disable', 'wc_makecommerce_domain' ),
            'type' => 'checkbox',
            'label' => __( 'Enable SimpleCheckout', 'wc_makecommerce_domain' ),
            'default' => 'yes'
        );

        //get a list of all active languages
        $languages = \MakeCommerce\i18n::get_active_languages();

        //default version with no languages
        if ( empty( $languages ) ) {

            $this->form_fields['shop_tos_url'] = array(
                'title' => __( 'Shop ToS url', 'wc_makecommerce_domain' ),
                'description' => __( 'paste here url of your shop "Terms and Conditions" page', 'wc_makecommerce_domain' ),
                'type'  => 'text',
            );
        } else { //version with different languages

            foreach ( $languages as $language_code=>$language ) {

                $shortLanguageCode = substr( $language_code, 0, 2 );
                $this->form_fields['shop_tos_url_'.$shortLanguageCode] = array(
                    'title' => __( 'Shop ToS url', 'wc_makecommerce_domain' ).sprintf( ' (%s)', $shortLanguageCode ),
                    'description' => __( 'paste the url of your shops "Terms and Conditions" page here', 'wc_makecommerce_domain' ),
                    'type'  => 'text',
                );
            }
        }

        //hide shipping methods block option
        $this->form_fields['hide_shipping_methods'] = array(
            'title' => __( 'Hide shipping methods block', 'wc_makecommerce_domain' ),
            'type' => 'checkbox',
            'label' => __( 'Hide shipping methods block on cart page. This will make it look more clean and simple', 'wc_makecommerce_domain' ),
            'default' => 'no'
        );
    }

    /**
     * Checks whether this payment gateway is enabled
     * returns true or false
     * 
     * @since 3.0.0
     */
    public function enabled() {

        if ( get_option( 'mk_checkout_sco', 'no' ) == "yes" ) {
            return true;
        }

        return false;
    }
}