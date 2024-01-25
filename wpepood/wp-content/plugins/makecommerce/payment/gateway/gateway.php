<?php

namespace MakeCommerce\Payment;

/**
 * Creates minimal functionality needed for payment gateways
 * 
 * @since 3.0.0
 */

use WC_Payment_Gateway;
use MakeCommerce\Payment\Gateway\Refund;
use MakeCommerce\Payment\Gateway\Subscription;

abstract class Gateway extends WC_Payment_Gateway {
    use Refund;
    use Subscription;

    public $id;
    public $MK;

    /**
     * Defines which WooCommerce options this payment gateway supports
     */
    public $supports = array(
        'subscriptions',
        'subscription_cancellation', 
        'subscription_suspension', 
        'subscription_reactivation',
        'subscription_amount_changes',
        'subscription_date_changes',
        'subscription_payment_method_change',
        'products',
        'refunds'
    );
    
    /**
     * Construct payment gateway
     * 
     * @since 3.0.0
     */
    public function __construct() {

        //exit if the gateway is not enabled
        if ( !$this->enabled() ) {
            return;
        }

        //Set api
        $this->MK = \MakeCommerce::get_api();

        //get settings from \WC_Payment_Gateway
        $this->init_settings();

        //set default settings used by all shipping methods
        $this->set_default_settings();

        if ( is_admin() ) {
            //initialize form fields for admin
            $this->initialize_form_fields();
        }

        //set hooks
        $this->set_hooks();

        //set gateway specific hooks
        if ( $this->active() ) {
            $this->set_gateway_hooks();
        }
    }

    /**
     * Initializes basic fields used by all payment gateways.
     * 
     * @since 3.0.0
     */
    public function initialize_form_fields() {

        //Initialize basic form fields
        $this->initialize_basic_form_fields();

        //Initialize gateway specific form fields
        $this->initialize_gateway_type_form_fields();

    }

    /**
     * Check if the gateway is available
     * Automatically called by \WC_Payment_Gateway
     * 
     * @since 3.0.0
     */
    public function is_available() {

        if ( isset($this->settings['active']) ) {
            if ( !( $this->settings['active'] == "yes" && \MakeCommerce::is_api_set() ) ) {
                return false;
            }
        }

        return parent::is_available();
    }

    /**
     * Sets default settings used by all payment gateways
     * 
     * @since 3.0.0
     */
    final public function set_default_settings() {

        if ( isset( $this->settings['active'] ) ) {
            $this->enabled = $this->settings['active'];
        }
    }

    /**
     * Checks whether the payment gateway is marked as active
     * 
     * @since 3.0.0
     */
    final public function active() {

        if ( isset( $this->settings['active'] ) ) {
            if ( $this->settings['active'] == "yes" || empty( $this->settings['active'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set hooks used by all WooCommerce payment gateways
     * 
     * @since 3.0.0
     */
    final public function set_hooks() {
        
        add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_payment_gateways', array( $this, 'add_payment_gateway' ) );

        global $woocommerce;

		// Woocommerce Multilingual overrides
		if ( \MakeCommerce\i18n::using_language_plugins() ) {
			add_filter( 'woocommerce_gateway_title', array( $this, 'override_payment_method_string' ), 25, 2 );
		}

        //add pay later block to product
        if ( isset( $this->settings["pl_show_on_product_pages"] ) ) {
            if ( $this->settings["pl_show_on_product_pages"] == "yes" && !empty( $this->settings["pl_show_methods"] )) {
                add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_pay_later_block_to_product' ), 10, 0 );
                add_action( 'woocommerce_add_to_cart_redirect', array( $this, 'added_to_cart_via_paylater_redirect' ), 10, 0 );
                add_action( 'woocommerce_after_checkout_form', array( $this, 'autoselect_chosen_paylater_method' ), 10, 1 );
            }
        }
    }

    /**
	 * Overrides payment method title
	 * 
	 * @since 3.0.0
	 */
	public function override_payment_method_string( $title, $gateway_id ) {

        if ($gateway_id === 'makecommerce') {

            $language_code = \MakeCommerce\i18n::get_two_char_locale();

            //default (no language)
            if ( !empty( $this->settings['ui_widget_title'] ) ) {
                $title = $this->settings['ui_widget_title'];
            }

            //if method name setting has been set with current language code
            if ( !empty( $this->settings['ui_widget_title_' . $language_code] ) ) {
                $title = $this->settings['ui_widget_title_' . $language_code];
            }
        }

        return $title;
    }

    /**
     * Adds payment gateway to woocommerce
     * 
     * @since 3.0.0
     */
    final public function add_payment_gateway( $methods ) {

        $methods[] = get_class( $this );

		return $methods;
    }

    /**
     * Initializes basic shared form fields
     * Used by all payment gateways
     * 
     * since 3.0.0
     */
    final public function initialize_basic_form_fields() {

        $this->form_fields = array();
        
        $this->form_fields['logo'] = array( 'type' => 'title', 'description' => \MakeCommerce::get_logo_html() );	
    }

    /**
     * Initializes gateway type form fields
     * 
     * @since 3.0.0
     */
    abstract public function initialize_gateway_type_form_fields();

    /**
     * Force creating a function for setting gateway specific hooks
     * 
     * @since 3.0.0
     */
    abstract public function set_gateway_hooks();

    /**
     * Checks if the gateway is enabled
     * 
     * @since 3.0.0
     */
    abstract public function enabled();
}
