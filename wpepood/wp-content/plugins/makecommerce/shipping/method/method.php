<?php

namespace MakeCommerce\Shipping;

/**
 * Creates minimal functionality needed for WooCommerce shipping method
 * 
 * @since 3.0.0
 */

use WC_Shipping_Method;
use MakeCommerce\Shipping\Method\ShippingClasses;

abstract class Method extends WC_Shipping_Method {
    use ShippingClasses;

    public $id;
    public $instance_id;

    public $supports = array(
        'shipping-zones',
        'instance-settings',
        'instance-settings-modal',
        'settings'
    );
    
    /**
     * Construct WooCommerce shipping method
     * 
     * @since 3.0.0
     */
    public function __construct( $instance_id = 0 ) {

        //set required properties
        $this->instance_id  = absint( $instance_id );
        $this->id           = $this->identifier . '_' . $this->carrier_id;
        $this->ext          = $this->carrier_id;
        $this->title        = $this->return_method_title();
        $this->method_title = $this->title;
        $this->name_ext     = $this->title;

        //get settings from WC_Shipping_Method
        $this->init_settings();

        //set default settings used by all shipping methods
        $this->set_default_settings();

        //initialize form fields for admin
        $this->initialize_form_fields();

        //set hooks
        $this->set_hooks();

        //initilize method type
        $this->initialize();

        $this->fields_validation( $this->settings );
    }

    /**
     * Set hooks used by all WooCommerce shipping methods
     * 
     * @since 3.0.0
     */
    final public function set_hooks() {
        
        //update options
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
        add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'fields_validation' ) );
        
        //Woocommerce Multilingual overrides (change some titles)
        if ( \MakeCommerce\i18n::using_language_plugins() ) {
            add_filter( 'woocommerce_package_rates', array( $this, 'override_label_translation' ), 50 );
            add_filter( 'woocommerce_order_get_items', array( $this, 'override_shipping_title_translation' ), 50, 2 );
        }

        //check if a parcelmachine has been chosen in checkout
        add_action( 'woocommerce_checkout_process', array( $this, 'check_checkout_fields' ) );
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_checkout_fields' ) );
    }

    /**
     * Add check for shipping settings
     * Checks if certain fields are valid
     * 
     * @since 3.2.0
     */
    function fields_validation( $fields ) {
        // Phone not set, can not validate
        if ( !isset( $fields['shop_phone'] ) ) {
            return $fields;
        }
        $fields['shop_phone'] = $this->sanitize_phone_number( $fields['shop_phone'] );
        if ( $fields['shop_phone'] != '' && !$this->valid_phone_number($fields['shop_phone']) ) {
            add_action( 'admin_notices', array( $this, 'invalid_phone_number_notice' ) );
        }
        return $fields;
    }

    /**
     * Add notice for invalid phone number
     * Adds shipping method specific notice to wp admin
     * 
     * @since 3.2.0
     */
    function invalid_phone_number_notice() {

        // keep track of the added notices
        static $added = [];

        $user_id = get_current_user_id();

        $unique_id = 'mc_phone_number_notice_dismissed_' . $this->id;
        $dismiss_href = '?' . $unique_id;

        if ( isset( $_GET ) && !empty( $_GET ) ) {
            $dismiss_href = '&' . $unique_id;
        }
        // dismiss button pressed, add to db
        if ( isset( $_GET[$unique_id] ) ) {
            add_user_meta( $user_id, $unique_id, 'true', true );
        }
        // value is dismissed in db, do not display error
        if ( get_user_meta( $user_id, $unique_id ) ) {
            return;
        }
        // error already displayed, do not add duplicate
        if ( in_array( $unique_id, $added ) ) {
            return;
        } else {
            // add value to array to eliminate duplicates
            array_push( $added, $unique_id );
            // display error
            echo '
            <div class="notice notice-error">
                <p>
                    ' . $this->phone_number_validation_error() . '
                    <a href="admin.php?page=wc-settings&tab=shipping&section='. $this->id . '">' . __('Update here', 'wc_makecommerce_domain' ) . '</a>
                    <a style="float: right; " href="' . $dismiss_href . '">' . __( 'Dismiss', 'wc_makecommerce_domain' ) . '</a>
                </p>
            </div>';
        }
    }

    /**
     * Add check for parcelmachine selectbox
     * Checks if something is chosen
     * 
     * @since 3.0.4
     */
    public function check_checkout_fields() {

        //those checks are needed, otherwise the notification will be added more than once
        static $added1 = false;
        static $added2 = false;

        $shipping_method = !empty( $_POST['shipping_method'] ) ? $_POST['shipping_method'] : false;

        if ( !empty( $shipping_method[0] ) ) {
            $shipping_method_ext = explode( ':', $shipping_method[0] );
        }

        if ( !$added1 && $shipping_method_ext[0] === $this->id && empty( $_POST[$shipping_method_ext[0]] ) && $this->type == "apt" ) {
            wc_add_notice( __( '<strong>Parcel machine</strong> is a required field.', 'wc_makecommerce_domain' ), 'error' );
            $added1 = true;
        }

        $shipping_phone_number = null;
        if ( isset( $_POST['ship_to_different_address'] ) && isset( $_POST['shipping_phone'] ) ) {
            $_POST['shipping_phone'] = $this->sanitize_phone_number( $_POST['shipping_phone'] );
            $shipping_phone_number = $_POST['shipping_phone'];
        } else {
            $_POST['billing_phone'] = $this->sanitize_phone_number( $_POST['billing_phone'] );
            $shipping_phone_number = $_POST['billing_phone'];
        }
        
        if ( $shipping_phone_number !== null ) {
            if ( !$added2 && $shipping_method_ext[0] === $this->id && !$this->valid_phone_number( $shipping_phone_number ) ) {
                wc_add_notice( $this->phone_number_validation_error(), 'error' );
                $added2 = true;
            }
        }
    }

    /**
     * Sanitize phone number
     * 
     * @since 3.0.4
     */
    public function sanitize_phone_number( $phone_number ) {
        
        $phone_number = filter_var ( $phone_number, FILTER_SANITIZE_NUMBER_INT );
        $phone_number = trim( $phone_number, '-' );
        //convert first 2 zeros to + if needed
        if ( substr( $phone_number, 0, 2 ) == "00" ) {
            $phone_number = '+' . substr( $phone_number, 2 );
        }

        return $phone_number;
    }

    /**
     * Check if provided phonenumber is valid
     * 
     * @since 3.0.4
     */
    public function valid_phone_number( $phone_number ) {

        //could be that all that we need to check is international format
        if ( $this->international_number_format ) {
            return $this->check_international_number( $phone_number );
        }

        //allow all numbers if validation is not defined
        if ( !isset( $this->valid_phonenumber_country_codes ) ) {
            return true;
        }

        //remove + signs
        $phone_number = trim( $phone_number, '+' );
        
        //check if it contains country code
        foreach ( $this->valid_phonenumber_country_codes as $country_code=>$valid_numbers ) {

            //country code matched
            if ( substr( $phone_number, 0, strlen( $country_code ) ) == $country_code ) {
                return $this->check_number( substr( $phone_number, strlen( $country_code ) ), $valid_numbers );
            }
        }

        //country code didn't match, there is nothing we can do but check if its valid in any of the provided countries
        foreach ( $this->valid_phonenumber_country_codes as $country_code=>$valid_numbers ) {
            if ( $this->check_number( $phone_number, $valid_numbers ) === true ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if phone number is international format
     * 
     * This function could be improved. Either by using https://github.com/google/libphonenumber or creating our own library
     * 
     * @since 3.0.4
     */
    public function check_international_number( $number ) {

        //https://en.wikipedia.org/wiki/E.164 (Solomon islands has 8)
        $min = 8;
        $max = 15;
        
        //without going into any more detail we are just going to check if the number starts with a + and has a minimum amount of numbers and a maximum
        if ( substr( $number, 0, strlen( '+' ) ) && strlen( $number ) >= ( $min + 1 ) && strlen( $number ) <= ( $max + 1 ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check if number is valid according to an array
     * 
     * @since 3.0.4
     */
    public function check_number( $number, $valid_numbers ) {

        foreach ( $valid_numbers as $start_digits=>$lenghts ) {
            foreach ( $lenghts as $lenght ) {
                // If the starting digit(s) matches OR it is '*' for any starting number 
                // AND the length matches
                if ( ( substr( $number, 0, strlen( $start_digits ) ) == $start_digits || $start_digits === '*' ) && strlen( $number ) == $lenght ) {
                    return true;
                }
            }
        }

        return false;
    }
	
    /**
     * Label translation override for WPML (woocommerce_package_rates)
     * 
     * @since 3.0.0
     */
    public function override_label_translation( $rates ) {

        $id_instance = $this->id . ':' . $this->instance_id;
        
        if ( array_key_exists( $id_instance, $rates ) ) {
            $rates[$id_instance]->label = $this->title;
        }

        return $rates;
    }

    /**
     * Title translation override for WPML (woocommerce_order_get_items)
     * 
     * @since 3.0.0
     */
    public function override_shipping_title_translation( $items, $order ) {

        foreach ( $items as $key => $item ) {

            if ( $item['type'] == 'shipping' ) {
                foreach ( $item['item_meta_array'] as $meta ) {

                    if ( $meta->key === 'method_id' ) {

                        $tmp = explode( ':', $meta->value );
                        
                        if ( $tmp[0] == $this->id ) {
                            $items[$key]['name'] = $this->title;
                        }
                    }
                }
            }
        }

        return $items;
    }
    
    /**
     * Sets default settings used by all shipping methods
     * 
     * @since 3.0.0
     */
    final public function set_default_settings() {

        $this->enable                   = !empty( $this->settings['active'] ) ?  $this->settings['active'] : null;
        $this->availability             = 'specific';
        $this->order_country            = 'unknown';
        $this->maximum_weight           = !empty( $this->settings['maximum_weight']) ? ( double )$this->settings['maximum_weight'] : 0;
        $this->free_shipping_min_amount = !empty( $this->settings['free_shipping_min_amount'] ) ? $this->settings['free_shipping_min_amount'] : 0;
        $this->countries                = !empty( $this->settings['countries'] ) ? $this->settings['countries'] : array();

        //Overrides title if its set in settings already
        $this->override_title();
    }

    /**
     * Check if the shipping method is available (automatically called by parent \WC_Shipping_Method)
     * 
     * @since 3.0.0
     */
    public function is_available( $package ) {

        //check if cart weight exceeds max weight
        if ( WC()->cart->cart_contents_weight > $this->maximum_weight ) {
            return false;
        }

        $is_available = $this->enable === 'yes';

        if ( !$is_available || !\MakeCommerce::is_api_set() ) {
            return false;
        }

        $this->order_country = $package['destination']['country'];

        return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package );
    }

    /**
     * Calculate price for shipping
     * 
     * @since 3.0.0
     */
    public function calculate_shipping( $package = array() ) {

        $price = isset( $this->instance_settings['price'] ) ? $this->instance_settings['price'] : ( isset( $this->settings['price_'.$package['destination']['country']] ) ? $this->settings['price_'.$package['destination']['country']] : 0 );

        $rate = array(
            'id' 	=> $this->get_rate_id(),
            'label' => $this->title,
            'cost' 	=> $price,
            'package' => $package,
            'calc_tax' => 'per_order',
        );
        
        //check shippingclasses
        $rate = $this->calculate_shipping_class_price( $rate, $package );

        //check all free shipping options:
        $free_shipping_min_amount = $this->instance_settings['free_shipping_min_amount'];

        if ( $free_shipping_min_amount && $package['contents_cost'] >= $free_shipping_min_amount ) {
            $rate["cost"] = 0;
        }

        //check only for parcelmachines
        if ( $this->type == "apt" ) {

            $free_shipping = true;
            foreach ( $package['contents'] as $line ) {
                $free_shipping = get_post_meta( $line['product_id'], '_no_shipping_cost', true ) === 'yes' ? $free_shipping : false;
            }

            if ( $free_shipping ) {
                $rate["cost"] = 0;
            }
        }

        //check if there is a free shipping coupon (if it's free then allow free shipping)
        if ( isset( $package['applied_coupons'] ) && $this->instance_settings["allow_free_shipping_coupons"] === "yes" ) {
            foreach ( $package['applied_coupons'] as $coupon_code ) {

                $coupon = new \WC_Coupon( $coupon_code );
                if ( $coupon->get_free_shipping() === true ) {
                    $rate["cost"] = 0;
                    break;
                }
            }
        }
        
        $this->add_rate( $rate );
    }

    /**
     * This function overrides shipping method title
     * if you have set language specific titles in admin.
     * 
     * @since 3.0.0
     */
    final public function override_title() {

        $language_code = \MakeCommerce\i18n::get_two_char_locale();

        //default (no language)
        if ( !empty( $this->settings['method_name'] ) ) {
            $this->title = $this->settings['method_name'];
        }

        //if method name setting has been set with current language code
        if ( !empty( $this->settings['method_name_' . $language_code] ) ) {
            $this->title = $this->settings['method_name_' . $language_code];
        }
    }

    /**
     * Initializes basic fields used by all shipping methods.
     * 
     * @since 3.0.0
     */
    public function initialize_form_fields() {

        //Initialize instance form fields first
        $this->initialize_instance_form_fields();

        if ( isset ( $_GET['section'] ) && $_GET['section'] == $this->id ) {
            
            //Initialize basic form fields
            $this->initialize_basic_form_fields();

            //Initialize method specific form fields
            $this->initialize_method_type_form_fields();

            //Initialize return address fields
            $this->initialize_return_address_form_fields();
        }
    }

    /**
     * Initializes basic shared form fields
     * Used by all shipping methods
     * 
     * @since 3.0.0
     */
    final public function initialize_basic_form_fields() {

        $this->form_fields = array();

        $this->form_fields['logo'] = array(
                'type'          => 'title', 
                'description'   => \MakeCommerce::get_logo_html()
        );
        
        $this->form_fields['generic'] = array(
                'type' 			=> 'title', 
                'title'			=> __( 'Generic and pricing options', 'wc_makecommerce_domain' ),
        );

        $this->form_fields['active'] = array(
                'title'         => __( 'Enable', 'wc_makecommerce_domain' ),
                'type'          => 'checkbox',
                'label'         => __( 'enabled', 'wc_makecommerce_domain' ),
                'default'       => 'no',
        );

        $this->form_fields['maximum_weight'] = array(
                'title'         => sprintf( __( 'Maximum weight allowed for shipping (%s)', 'wc_makecommerce_domain' ), get_option( 'woocommerce_weight_unit' ) ),
                'type'          => 'text',
                'default'       => $this->default_max_weight
        );
        
        $this->form_fields['look_and_feel'] = array(
                'type' => 'title',
                'title' => '<hr><br>'.__( 'Look and feel options', 'wc_makecommerce_domain' ),
                'description' => __( 'Options for presentation on check-out page', 'wc_makecommerce_domain' )
        );

        //get a list of all active languages
        $languages = \MakeCommerce\i18n::get_active_languages();

        if ( empty( $languages ) ) {
            
            $this->form_fields['method_name'] = array(
                'title' => __( 'Shipping Method Title', 'wc_makecommerce_domain' ),
                'type' => 'text',
                'default' => $this->carrier_title . " " . \MakeCommerce\i18n::get_string_from_mo( $this->identifier, 'wc_makecommerce_domain', \MakeCommerce\i18n::get_two_char_locale() )
            );
        } else {
            foreach ( $languages as $language_code => $language ) {
                $this->form_fields['method_name_'.substr( $language_code, 0, 2 )] = array(
                    'title' => __('Shipping Method Title', 'wc_makecommerce_domain').sprintf(' (%s)', substr($language_code, 0, 2)),
                    'type' => 'text',
                    'default' => $this->carrier_title . " " . \MakeCommerce\i18n::get_string_from_mo( $this->identifier, 'wc_makecommerce_domain', substr( $language_code, 0, 2 ) )
                );
            }
        }

        $this->initialize_method_type_checkout_fields();

        if ( \MakeCommerce::new_woo_version() ) {
            $a = 'admin.php?page=wc-settings&tab=advanced&section=mk_api';
        } else {
            $a = 'admin.php?page=wc-settings&tab=api&section=mk_api';
        }

        $this->form_fields['api_access'] = array(
            'type' => 'title',
            'title' => '<hr><br>'.__( 'API access for', 'wc_makecommerce_domain' ).' '.$this->carrier,
            'description' => sprintf(__('You can automatically create shipments into %s system and print the out the package labels right here, at the shop orders view. <br> Please set your %s web services account credentials below here. <br>(see more on <a href="https://makecommerce.net/en/integration-modules/makecommerce-woocommerce-payment-plugin/#carriers-integration">MakeCommerce plugin page</a>. Don\'t forget to enable also <a href="%s">MC API keys</a>!)', 'wc_makecommerce_domain' ), $this->carrier, $this->carrier, admin_url( $a ) )
        );
    }

    /**
     * Initializes basic instance fields used by all shipping methods.
     * Automatically called by \WC_Shipping_Method
     * 
     * @since 3.0.0
     */
    final public function initialize_instance_form_fields() {

        $this->instance_form_fields = array();
        
        $this->instance_form_fields['logo'] = array(
            'type'        => 'title',
            'description' => \MakeCommerce::get_logo_html()
        );
        
        $this->instance_form_fields['price'] = array(
            'title'       => __('Shipping price', 'wc_makecommerce_domain'),
            'type'        => 'text',
            'default'     => $this->default_price
        );
        
        $this->instance_form_fields['free_shipping_min_amount'] = array(
            'title'       => __('Free shipping amount', 'wc_makecommerce_domain'),
            'type'        => 'number',
            'default'     => '0',
            'desc_tip'    => __( '(0 means no free shipping)', 'wc_makecommerce_domain' ),
        );
        
        $this->instance_form_fields['allow_free_shipping_coupons'] = array(
            'title'       => __( 'Free shipping coupons', 'wc_makecommerce_domain' ),
            'type'        => 'checkbox',
            'default'     => 'no',
            'desc_tip'	  => __( 'Allow using free shipping coupons to be used with this method', 'wc_makecommerce_domain' ),
        );

        $this->instance_form_fields = $this->add_form_fields( $this->instance_form_fields );
    }

    /**
     * Initializes return address form fields
     * Used by all shipping methods
     * 
     * @since 3.0.0
     */
    public function initialize_return_address_form_fields() {

        $this->form_fields['return_address'] = array(
            'type' => 'title',
            'title' => __( 'Return address', 'wc_makecommerce_domain' ),
            'description' => sprintf( __( 'Please define return address for %s shipments.<br><b>All fields are required.</b>', 'wc_makecommerce_domain' ), $this->carrier )
        );
        
        $this->form_fields['shop_name'] = array(
            'type' => 'text',
            'title' => __( 'Shop name', 'wc_makecommerce_domain' ),
            'class' => 'input-text regular-input',
        );
        
        $this->form_fields['shop_phone'] = array(
            'type' => 'text',
            'title' => __( 'Shop phone (mobile)', 'wc_makecommerce_domain' ),
            'class' => 'input-text regular-input',
        );
        
        $this->form_fields['shop_email'] = array(
            'type' => 'text',
            'title' => __( 'Shop email', 'wc_makecommerce_domain' ),
            'class' => 'input-text regular-input',
        );
        
        $this->form_fields['shop_address_country'] = array(
            'type' => 'select',
            'title' => __( 'Shop address country', 'wc_makecommerce_domain' ),
            'options' => array(
                'EE' => 'EE',
                'LV' => 'LV',
                'LT' => 'LT',
            ),
            'default' => 'EE',
        );
        
        $this->form_fields['shop_address_city'] = array(
            'type' => 'text',
            'title' => __( 'Shop address city', 'wc_makecommerce_domain' ),
            'class' => 'input-text regular-input',
        );		
        
        $this->form_fields['shop_postal_code'] = array(
            'type' => 'text',
            'title' => __( 'Shop postal code', 'wc_makecommerce_domain' ),
            'class' => 'input-text regular-input',
        );
        
        $this->form_fields['shop_address_street'] = array(
            'type' => 'text',
            'title' => __( 'Shop address street', 'wc_makecommerce_domain' ),
            'class' => 'input-text regular-input',
        );					
    }

    /**
     * Initializes method type specific form fields
     * Loads method specific fields 
     * 
     * @since 3.0.0
     */
    public function initialize_method_type_form_fields() {

        //Initialize method specific fields
        $this->initialize_method_form_fields();

        $this->form_fields['service_user'] = array(
            'title'            => sprintf( __( '%s web services username', 'wc_makecommerce_domain' ), $this->service_name ),
            'type'             => 'text',
            'default'          => ''
        );
        
        $this->form_fields['service_password'] = array(
            'title'            => sprintf( __( '%s web services password', 'wc_makecommerce_domain' ), $this->service_name ),
            'type'             => 'text',
            'default'          => ''
        );
    }

    /**
     * Initializes method type, required function for all method types
     * 
     * @since 3.0.0
     */
    abstract public function initialize();

    /**
     * Sets title of the method
     * 
     * @since 3.0.0
     */
    abstract public function return_method_title();

    /**
     * Returns phone validation error
     * 
     * @since 3.0.4
     */
    abstract public function phone_number_validation_error();
}
