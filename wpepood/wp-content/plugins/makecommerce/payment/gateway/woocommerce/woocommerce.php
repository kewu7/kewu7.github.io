<?php

namespace MakeCommerce\Payment\Gateway;

use MakeCommerce\Payment\Gateway;
use MakeCommerce\Payment\Gateway\WooCommerce\Banklink;
use MakeCommerce\Payment\Gateway\WooCommerce\Creditcard;
use MakeCommerce\Payment\Gateway\WooCommerce\Paylater;

class WooCommerce extends Gateway {
    use Banklink;
    use Creditcard;
    use Paylater;

    public $id = MAKECOMMERCE_PLUGIN_ID;
    public $version = '3.4.2';
    
    public $payment_return_url;
    public $payment_return_url_m2m;
    public $payment_return_url_cancel;

    public $title = 'MakeCommerce';
    public $method_title = 'MakeCommerce';
    public $description = 'Payment and shipping solutions by MakeCommerce';
    
    protected $init;

    /**
     * Payment methods
     */
    private WooCommerce\Methods $methods;

    /**
     * Construct the parent and payment gateway
     * 
     * @since 3.0.0
     */
    public function __construct( $init = false ) {

        $this->init = $init;

        parent::__construct();

        //set return urls
        $this->set_return_urls();

        $this->mc_version_check( $this->version );

        //initialize all payment methods
        $this->methods = new WooCommerce\Methods( $this->id, $this->init, $this->settings );

        $this->set_title_by_language();

        //has_fields enables our methods for WooCommerce
        $this->has_fields = true;

        //add paylater first time notice
        add_action( 'admin_notices', array( $this, 'paylater_first_time_notice' ) );
    }


    /**
     * Set return urls
     * 
     * @since 3.0.0
     */
    private function set_return_urls() {

        //set language
        $langGet = "&lang1=" . \MakeCommerce\i18n::get_two_char_locale();

        //set return url's
        $this->payment_return_url = site_url( '/?makecommerce_return=1'.$langGet );
        $this->payment_return_url_m2m = site_url( '/?makecommerce_return=1&ajax_content=1'.$langGet );
        $this->payment_return_url_cancel = site_url( '/?makecommerce_return=1'.$langGet );
    }

    /**
     * Set title according to language
     * 
     * @since 3.0.0
     */
    private function set_title_by_language( ) {

        //default
        if ( !empty( $this->settings['ui_widget_title'] ) ) {
            $this->title = $this->settings['ui_widget_title'];
        }

        $locale = "";
        
        if ( isset( $_GET["lang1"] ) ) {

            $locale = $_GET["lang1"];

            if ( !empty( $this->settings['ui_widget_title_'.$locale] ) ) {
                $this->title = $this->settings['ui_widget_title_'.$locale];
            }
        } else {

            $locale = \MakeCommerce\i18n::get_two_char_locale();

            //coming from admin, set language according to order wpml_language. This meta is set for both polylang wpml 
            if ( isset( $_POST["meta"] ) ) {
                foreach ( $_POST["meta"] as $key=>$value ) {
                    if ( is_array( $value ) ) {
                        if ( $value["key"] == "wpml_language" ) {
                            $locale = strtolower( substr( $value["value"], 0, 2 ) );
                            
                        }
                    }
                }
            }

            if ( !empty( $this->settings['ui_widget_title_' . $locale] ) ) {
                $this->title = $this->settings['ui_widget_title_' . $locale];
            }
        }
    }

    /**
     * Loads all form fields specific to this payment gateway
     * 
     * @since 3.0.0
     */
    public function initialize_gateway_type_form_fields() {

        $this->form_fields['active'] = array(
            'title' => __( 'Enable/Disable', 'wc_makecommerce_domain' ),
            'type' => 'checkbox',
            'label' => __( 'Enable MakeCommerce payments', 'wc_makecommerce_domain' ),
            'default' => 'no'
        );

        $a = admin_url( 'admin.php?page=wc-settings&tab=api&section=mk_api' );
        if ( \MakeCommerce::new_woo_version() ) {
            $a = admin_url( 'admin.php?page=wc-settings&tab=advanced&section=mk_api' );
        }

        $this->form_fields['api_title'] = array(
            'title' => __( 'MakeCommerce API', 'wc_makecommerce_domain' ),
            'description' => sprintf( __( 'Go to <a href="%s">API settings</a> to fill in the credentials', 'wc_makecommerce_domain' ), $a ),
            'type' => 'title',
        );

        $this->form_fields['ui_title'] = array(
            'title' => '<br>'.__( 'User Interface', 'wc_makecommerce_domain' ),
            'type' => 'title',
            'class' => 'ui-identifier',
        );

        $this->form_fields['ui_open_by_default'] = array(
            'title' => __( 'Set as default selection', 'wc_makecommerce_domain' ),
            'label' => __( 'MakeCommerce payments widget will be selected by default', 'wc_makecommerce_domain' ),
            'type' => 'checkbox',
            'default' => 'yes',
            'class' => 'ui-identifier',
        );

        $this->form_fields['ui_mode'] = array(
            'title' => __( 'Display MC payment channels as', 'wc_makecommerce_domain' ),
            'type' => 'select',
            'default' => 'widget',
            'options' => array(
                'inline' => __( 'List', 'wc_makecommerce_domain' ),
                'widget' => __( 'Grouped to widget', 'wc_makecommerce_domain' ),
            ),
            'class' => 'ui-identifier',
        );

        $languages = \MakeCommerce\i18n::get_active_languages();

        if ( empty( $languages ) ) {

            $this->form_fields['ui_widget_title'] = array(
                'title' => __( 'Payments widget title', 'wc_makecommerce_domain' ),
                'type' => 'text',
                'desc_tip' => __( "Appropriate title may depend on the configuration you have made, i.e. 'pay with bank-link or credit card', 'pay with bank-links' or 'payment methods'", 'wc_makecommerce_domain' ),
                'default' => __( 'Pay with bank-links or credit card', 'wc_makecommerce_domain' ),
                'class' => 'ui-identifier',
            );
        } else {

            foreach ( $languages as $language_code => $language ) {
                $this->form_fields['ui_widget_title_'.$language_code] = array(
                    'title' => __( 'Payments widget title', 'wc_makecommerce_domain' ).sprintf( ' (%s)', $language_code ),
                    'type' => 'text',
                    'desc_tip' => __("Appropriate title may depend on the configuration you have made, i.e. 'pay with bank-link or credit card', 'pay with bank-links' or 'payment methods'", 'wc_makecommerce_domain'),
                    'default' => \MakeCommerce\i18n::get_string_from_mo( 'Pay with bank-links or credit card', 'wc_makecommerce_domain', $language_code ),
                    'class' => 'ui-identifier',
                );
            }
        }

        $this->form_fields['ui_inline_uselogo'] = array(
            'title' => __( 'MC payment channels display style', 'wc_makecommerce_domain' ),
            'type' => 'select',
            'default' => 'logo',
            'options' => array(
                'logo' => __( 'Logo', 'wc_makecommerce_domain' ),
                'text_logo' => __( 'Text & logo', 'wc_makecommerce_domain' ),
                'text' => __( 'Text', 'wc_makecommerce_domain' ),
            ),
            'class' => 'ui-identifier',
        );

        $this->form_fields['ui_widget_logosize'] = array(
            'title' => __( 'Size of payment channel logos', 'wc_makecommerce_domain' ),
            'type' => 'select',
            'default' => 'medium',
            'options' => array(
                'small' => __( 'Small', 'wc_makecommerce_domain' ),
                'medium' => __( 'Medium', 'wc_makecommerce_domain' ),
                'large' => __( 'Large', 'wc_makecommerce_domain' )
            ),
            'class' => 'ui-identifier',
        );

        $this->form_fields['ui_widget_groupcountries'] = array(
            'title' => __( 'Group bank-links by countries', 'wc_makecommerce_domain' ),
            'type' => 'checkbox',
            'default' => 'no',
            'class' => 'ui-identifier',
        );

        $this->form_fields['ui_widget_countries_hidden'] = array(
            'title' => __( 'Hide country selector', 'wc_makecommerce_domain' ),
            'label' => __( 'Do not display country selector (flags) at payment methods', 'wc_makecommerce_domain' ),
            'type' => 'checkbox',
            'default' => 'no',
        );
        
        $this->form_fields['ui_widget_countryselector'] = array(
            'title' => __( 'Country selector style', 'wc_makecommerce_domain' ),
            'type' => 'select',
            'default' => 'flag',
            'options' => array(
                'flag' => __( 'Flag', 'wc_makecommerce_domain' ),
                'dropdown' => __( 'Dropdown', 'wc_makecommerce_domain' ),
            ),
            'class' => 'ui-identifier',
        );

        $this->form_fields['ui_payment_country_order'] = array(
            'title' => __( 'Define custom order of payment countries', 'wc_makecommerce_domain' ),
            'type' => 'text',
            'desc_tip' => __( 'If you want to change default order, insert a comma separated list of 2 char country codes. i.e. - ee, lv, lt, fi (international = other)', 'wc_makecommerce_domain' ),
            'class' => 'ui-identifier',
        );
        
        $this->form_fields['ui_chorder'] = array(
            'title' => __( 'Define custom order of payment channels', 'wc_makecommerce_domain' ),
            'type' => 'text',
            'desc_tip' => __( 'If you want to change default order, put here comma separated list of channels. i,e, - seb,lhv,swedbank. see more on the module home page (link above)', 'wc_makecommerce_domain' ),
            'class' => 'ui-identifier',
        );

        $this->form_fields['ui_javascript'] = array(
            'type' => 'ui_javascript',
        );

        $this->form_fields['cc_title'] = array(
            'title' => '<hr><br>'.__( 'Credit Card Settings', 'wc_makecommerce_domain' ),
            'type' => 'title',
        );

        $this->form_fields['cc_pass_cust_data'] = array(
            'title' => __( 'Prefill Credit Card form with customer data', 'wc_makecommerce_domain' ),
            'type' => 'checkbox',
            'default' => 'yes',
            'desc_tip' => __( 'It will pass user Name and e-mail address to the Credit Card dialog to make the form filling easier', 'wc_makecommerce_domain' ),
        );

        $this->set_pay_later_settings();

        $this->form_fields['adv_title'] = array(
            'title' => '<hr><br>'.__( 'Advanced Settings', 'wc_makecommerce_domain' ),
            'type' => 'title',
        );

        $this->form_fields['disable_cancelled_payment_update'] = array(
            'title' => __( 'Disable automatic cancelled payment status update', 'wc_makecommerce_domain' ),
            'label' => __( 'Disable automatic order status updates for cancelled payments by MakeCommerce', 'wc_makecommerce_domain' ),
            'type' => 'checkbox',
            'default' => 'no',
            'desc_tip' => __( 'Disable MakeCommerce from updating order statuses for cancelled payments and let WooCommerce handle the status change', 'wc_makecommerce_domain' ),
        );

        $this->form_fields['disable_expired_payment_update'] = array(
            'title' => __( 'Disable automatic expired payment status update', 'wc_makecommerce_domain' ),
            'label' => __( 'Disable automatic order status updates for expired payments by MakeCommerce', 'wc_makecommerce_domain' ),
            'type' => 'checkbox',
            'default' => 'no',
            'desc_tip' => __( 'Disable MakeCommerce from updating order statuses for expired payments and let WooCommerce handle the status change', 'wc_makecommerce_domain' ),
        );

        $this->form_fields['reload_links'] = array(
            'type' => 'mc_banklinks_reload',
            'title' => __( 'Update payment methods', 'wc_makecommerce_domain' ),
            'description' => __( 'Update', 'wc_makecommerce_domain' ),
            'desc_tip' => __( 'This will update shop configuration from MakeCommerce servers.', 'wc_makecommerce_domain' ),
        );
    }

    /**
     * Set gateway specific hooks/filters
     * 
     * @since 3.0.0
     */
    public function set_gateway_hooks() {

        add_filter( 'query_vars', array( $this, 'return_trigger' ) );
        add_action( 'template_redirect', array( $this, 'return_trigger_check' ) );

        add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'process_subscription_payment_start' ), 10, 2 );
        add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'process_subscription_payment_start' ), 10, 2 );
        
        if ( $this->init == false ) {
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
            wp_enqueue_script( 'jquery');
            wp_enqueue_style( 'makecommerce', plugins_url( '/css/makecommerce.css', __FILE__ ), array(), $this->version );

            //enqueue scripts for payment methods checkout
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        }

        if ( is_admin() ) {
            add_action( 'wp_ajax_mc_banklinks_reload', array( $this, 'mc_banklinks_reload' ) );
        }
    }

    /**
     * Enqueue scripts for payment methods checkout
     * 
     * @since 3.2.0
     */
    public function enqueue_scripts() {

        //default open
        if ( $this->settings['ui_open_by_default'] == 'yes' ) {

            \MakeCommerce::mc_enqueue_script( 
                'MC_DEFAULT_PM', 
                dirname( __FILE__ ) . '/js/mc_default_pm.js', 
                [ 'id' => $this->id ], 
                [ 'jquery' ]
            );
        }

        //widget/methods logic
        $country = '';
        $pick = true;
        if ( WC()->session->get( 'mc_selected_paylater_method' ) !== NULL ) {
            $country = WC()->session->get( 'mc_selected_paylater_method_country' );
            $pick = false;
        }
        
        \MakeCommerce::mc_enqueue_script( 
            'MC_METHOD_LIST', 
            dirname( __FILE__ ) . '/js/mc_method_list.js', 
            [
                'id' => $this->id,
                'country' => $country,
                'settings' => $this->settings,
                'pick' => $pick
            ], 
            [ 'jquery' ]
        );
    }
    
    /**
     * Javascript for user interface
     * 
     * @since 3.0.0
     */
    public function generate_ui_javascript_html( $key, $data ) {

        \MakeCommerce::mc_enqueue_script( 
            'MC_ADMIN_UI', 
            dirname( __FILE__ ) . '/js/mc_admin_ui.js', 
            [ 'id' => $this->id ], 
            [ 'jquery' ]
        );
    }
    
    /**
     * Overrides \WC_Payment_Gateway payment fields
     * Shows payment methods in checkout
     * 
     * @since 3.0.0
     */
    public function payment_fields() {

        $this->methods->show_methods();
    }

    /**
     * Checks if a payment option has been selected
     * 
     * @since 3.0.0
     */
    public function validate_fields() {

        global $woocommerce;

        $selected = isset( $_POST['PRESELECTED_METHOD_' . $this->id] ) ? sanitize_text_field( $_POST['PRESELECTED_METHOD_' . $this->id] ) : false;

        if ( !$selected ) {
            wc_add_notice( __( 'Please select suitable payment option!', 'wc_makecommerce_domain' ), 'error' );
        } else {
            // is this used?? 
            $woocommerce->session->makecommerce_preselected_method = $selected;
        }

        return true;
    }

    /**
     * Processes payments, called by \WC_Payment_Gateway
     * 
     * @since 3.0.0
     */
    public function process_payment( $orderId ) {

        $order = wc_get_order( $orderId );

        $selected = isset( $_POST['PRESELECTED_METHOD_' . $this->id] ) ? sanitize_text_field( $_POST['PRESELECTED_METHOD_' . $this->id] ) : false;

        if ( !empty( $selected ) ) {
            $order->update_meta_data( '_makecommerce_preselected_method', $selected );
                
            $request_body = array(
                'transaction' => array(
                    'amount' => ( string )sprintf( "%.2f", $order->get_total() ),
                    'currency' => method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->currency,
                    'reference' => $order->get_order_number(),
                    'transaction_url' => array(
                        'return_url' => array(
                            'url' => $this->payment_return_url,
                            'method' => 'POST',
                        ),
                        'cancel_url' => array(
                            'url' => $this->payment_return_url_cancel,
                            'method' => 'POST',
                        ),
                        'notification_url' => array(
                            'url' => $this->payment_return_url_m2m,
                            'method' => 'POST',
                        ),
                    ),
                ),
                'customer' => array(
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'country' => strtolower( $order->get_billing_country() ),
                    'locale' => strtolower( \MakeCommerce\i18n::get_two_char_locale() ),
                ),
            );

            $transaction = $this->MK->createTransaction( $request_body );
            
            if ( isset( $transaction->id ) ) {
                $order->update_meta_data( '_makecommerce_transaction_id', $transaction->id );

                if ( substr( $selected, 0, 5 ) == 'card_' ) {

                    $redirect_url = $order->get_checkout_payment_url( true );
                } else {

                    $redirect_url = false;
                    foreach ( $transaction->payment_methods->banklinks as $banklink ) {
                        if ( $banklink->country.'_'.$banklink->name === $selected ) {
                            $redirect_url = $banklink->url;
                        }
                    }

                    if ( !$redirect_url ) {
                        $redirect_url = $this->_getRedirectUrl( $selected ).$transaction->id;
                    }
                }

                $order->save();

                return array(
                    'result' => 'success',
                    'redirect' => $redirect_url
                );
            }
            // Save in case no transaction id
            $order->save();
        }
        
        wc_add_notice( __( 'An error occured when trying to process payment!', 'wc_makecommerce_domain' ), 'error' );

        return array(
            'result' => 'failure',
        );
    }
    
    /**
     * Return redirect uri for payment method
     * 
     * @since 3.0.0
     */
    protected function _getRedirectUrl( $selected ) {

        foreach ( $this->methods->banklinks as $method ) {

            if ( $selected == $method->country.'_'.$method->name ) {
                return $method->url;
            }
        }

        foreach ( $this->methods->paylater as $method ) {

            if ( $selected == $method->country.'_'.$method->name ) {
                return $method->url;
            }
        }
        
        return false;
    }
    
    /**
     * Variables that trigger return check
     * 
     * @since 3.0.0
     */
    public function return_trigger( $vars ) {

        $vars[] = 'makecommerce_return';
        $vars[] = 'ajax_content';
        $vars[] = 'makecommerce_card_pay';
        $vars[] = 'lang1';

        return $vars;
    }
    
    /**
     * Process return from payment, also handles cart updates among other things
     * 
     * @since 3.0.0
     */
    public function return_trigger_check() {

        if ( intval( get_query_var( 'makecommerce_return' ) ) > 0 ) {

            $return_url = \MakeCommerce\Payment::check_payment( $this->settings );
            
            if ( intval( get_query_var( 'ajax_content' ) ) ) {
                echo json_encode( array( 'redirect' => $return_url ) );
            } else {
                wp_redirect( $return_url );
            }

            exit;
        }
    }

    /**
     * Checks whether this payment gateway is enabled
     * returns true or false
     * 
     * This function always returns true as it is not possible to manually switch off MakeCommerce payment method in API options
     * 
     * @since 3.0.0
     */
    public function enabled() {

        return true;
    }


    /**
	 * Checks for MK version in  wp_options_table
	 * If it does not exist / is outdated, refresh tables and update version
	 * 
	 * @since	3.0.12
	 */
	public static function mc_version_check( $version ) {

		global $wpdb;
		$banklinks_table = $wpdb->prefix . MAKECOMMERCE_TABLENAME;

		$mc_version = get_option( 'mc_version', 'unset' );

        // Smartpost API migration for upgrading versions that are below 3.3.0
        if ( version_compare( $mc_version, '3.3.0', '<' ) ) {
            // Got to migrate the API key 
            $parcel = new \MakeCommerce\Shipping\Method\ParcelMachine\Smartpost();
            $cou = new \MakeCommerce\Shipping\Method\Courier\Smartpost();

            // Run the migration function
            $parcel->v3_2_2_api_key_migration();
            $cou->v3_2_2_api_key_migration();
        }

		if ( $mc_version != $version ) {
			// Get payment methods
			$methods = WooCommerce\Banklink::get_payment_methods();

			// Drop tables
			$sql = "DROP TABLE IF EXISTS `".$banklinks_table."`";
			$wpdb->query( $sql );

			// Create tables
			\MakeCommerce\Activator::activate();

			// Update tables
			$update = WooCommerce\Banklink::insert_payment_methods( $methods );

			// Add version into wp_options
			if ( $mc_version === 'unset' ) {
				add_option( 'mc_version', $version, '', 'yes' );
			} else {
				// Update version in wp_options
				update_option( 'mc_version', $version );
			}
		} 
	}
}