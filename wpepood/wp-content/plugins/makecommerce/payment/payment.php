<?php

/**
 * The payment functionality of the plugin.
 *
 * @link       https://makecommerce.net/
 * @since      3.0.0
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/admin
 */

namespace MakeCommerce;

use Automattic\WooCommerce\Utilities\OrderUtil;
/**
 * The payment functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/payment
 * @author     Maksekeskus AS <support@maksekeskus.ee>
 */
class Payment {

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

		$this->loader->add_action( 'plugins_loaded', $this, 'initialize_payment_gateways' );
		$this->loader->add_action( 'woocommerce_before_checkout_form', $this, 'check_payment_status' );
	}

	/**
	 * Initialize all payment gateways
	 * 
	 * @since 3.0.0
	 */
	public function initialize_payment_gateways() {
		//WooCommerce
		new Payment\Gateway\WooCommerce( true );
	}

	/**
	 * Returns price excluding taxes
	 * 
	 * @since 3.0.0
	 */
	public static function get_rate_without_taxes( $method, $rate ) {

		if ( empty( $rate[$method] ) ) {
			return 0;
		}

		$rate = $rate[$method];
		
		return $rate->cost;
	}
	
	/**
	 * Returns price including taxes
	 * 
	 * @since 3.0.0
	 */
	public static function get_rate_with_taxes( $method, $rate ) {

		if ( empty( $rate[$method] ) ) {
			return 0;
		}

		$rate = $rate[$method];
		$price = $rate->cost;

		if ( empty( $rate->taxes ) ) {
			return $price;
		}

		foreach ( $rate->taxes as $tax ) {
			$price += $tax;
		}

		return $price;
	}

	/**
	 * Update banklinks
	 * 
	 * @since 3.0.0
	 */
    public static function update_banklinks() {

		$obj = new Payment\Gateway\WooCommerce( true );

		$obj->mc_banklinks_reload( true );

	}

	/**
     * Add check for unsuccesful payment redirects
     * Displays notice if something went wrong
     * 
     * @since 3.0.12
     */
    public function check_payment_status()
	{
        if ( isset ( $_GET['mc_payment_status'] ) ) {
            switch( $_GET['mc_payment_status'] ) {
                case 'failed':
                    wc_add_notice( __( 'Payment failed', 'wc_makecommerce_domain' ), 'error' );
                    break;
                case 'cancelled':
                    wc_add_notice( __( 'Payment transaction cancelled', 'wc_makecommerce_domain' ), 'error' );
                    break;
                case 'expired':
                    wc_add_notice( __( 'Payment transaction expired', 'wc_makecommerce_domain' ), 'error' );
                    break;
            }

            // Avoid multiple error messages
            unset($_GET['mc_payment_status']);
        }
    }

	/**
	 * Check payment and process order if completed, canceled
	 * 
	 * @since 3.0.0
	 */
	public static function check_payment( $settings = array() ) {

		global $woocommerce;

		$api = \MakeCommerce::get_api();

		//set the correct language
		if ( isset( $_GET["lang1"] ) ) {
			\MakeCommerce\i18n::switch_language( $_GET["lang1"] );
		}

		$returnUrl = home_url();
		
		$request = stripslashes_deep( $_POST );

		if ( $api->verifyMac( $request ) ) {

			$data = $api->extractRequestData( $request );
			$transactionId = false;
			$reference = false;

			if ( !empty( $data['error'] ) ) {

				//add status and send client back to checkout
				return add_query_arg( 'mc_payment_status', 'failed', wc_get_checkout_url() );
			}

			if ( !empty( $data['message_type'] ) ) {

				if ( $data['message_type'] == 'payment_return' ) {

					$transactionId = $data['transaction'];
					$reference = $data['reference'];
					$paymentStatus = $data['status'];
				}

				if ( $data['message_type'] == 'token_return' ) {
					if ( !empty( $data['transaction']['reference'] ) ) {
						$reference = $data['transaction']['reference'];
					}

					$paymentStatus = $data['transaction']['status'];
					$transactionId = $data['transaction']['id'];
				}
			} 
		}

		// if we didn't find reference to an Order, we send user to shop landing-page. TODO: could be improved	
		if ( empty( $transactionId ) ) {
			return $returnUrl;
		}

		//get the post id using transactionid
		$orderId = self::get_postid_using_metakey( '_makecommerce_transaction_id', $transactionId );

		$order = wc_get_order( $orderId );
		
		// if we didn't find the Order, we send user to shop landing-page. TODO: could be improved
		if ( !$order || !$order->get_id() ) {
			return $returnUrl;
		}

		$returnUrl = $order->get_checkout_order_received_url();

		if ( $paymentStatus == 'EXPIRED' || $paymentStatus == 'CANCELLED' || $paymentStatus == 'COMPLETED' ) {

			//get transaction to update the payment method.
			$transaction = $api->getTransaction( $transactionId );
			
			//make sure the payment method is the same for simplecheckout and normal version.
			$paymentMethod = "";
			if ( isset( $transaction->type ) && isset( $transaction->method ) ) {
				$paymentMethod = $transaction->channel;
			}

			//update _makecommerce_preselected_method
			$order->update_meta_data( '_makecommerce_preselected_method', $paymentMethod );
			$order->save();
		}

		if ( $paymentStatus == 'CANCELLED' || ( $paymentStatus == 'EXPIRED' && $order->get_status() == "pending" ) ) {
			//send client back to checkout
			$returnUrl = wc_get_checkout_url();
		}

		//check if we already processed this status in the past.
		if ( $order->get_meta( '_makecommerce_payment_processed_status', true ) == $paymentStatus ) {
			return $returnUrl;
		}

		switch( $paymentStatus ) {

			case 'CANCELLED':
				// Do not update status when disabled in settings
				if ( ( $settings['disable_cancelled_payment_update'] ?? '' ) !== 'yes' ) {
					// Update automatically
					$order->update_status( 'cancelled' );
					$order->update_meta_data( '_makecommerce_payment_processed_status', $paymentStatus );
				}

				$returnUrl = add_query_arg( 'mc_payment_status', 'cancelled', $returnUrl );

				break;
			case 'EXPIRED':
				//only update if order status is pending payment
				if ( $order->get_status() == "pending" ) {

					// Do not update order status if disabled in settings
					if ( ( $settings['disable_expired_payment_update'] ?? '' ) !== 'yes' ) {
						// Update automatically
						$order->update_status( 'cancelled' );
						$order->update_meta_data( '_makecommerce_payment_processed_status', $paymentStatus );
					}

					$returnUrl = add_query_arg( 'mc_payment_status', 'expired', $returnUrl );
				}

				break;
			case 'COMPLETED':
				$orderNote = array();
				$transactionIdText = __( 'Transaction ID', 'wc_makecommerce_domain' );
				$paymentOptionText = __( 'Payment option', 'wc_makecommerce_domain' );
				$transactionIdText = \MakeCommerce\i18n::get_string_from_mo( 'Transaction ID', 'wc_makecommerce_domain', \MakeCommerce\i18n::get_site_default_language() );
				$paymentOptionText = \MakeCommerce\i18n::get_string_from_mo( 'Payment option', 'wc_makecommerce_domain', \MakeCommerce\i18n::get_site_default_language() );

				$orderNote[] = $transactionIdText . ': <a target=_blank href="'.$api->getEnvUrls()->merchantUrl.'merchant/shop/deals/detail.html?id='. $transactionId .'">'.$transactionId.'</a>';
				$orderNote[] = $paymentOptionText . ': ' . $order->get_meta( '_makecommerce_preselected_method', true );
				$order->add_order_note( implode( "\r\n", $orderNote ) );

				if ( !empty( $data['token'] ) && !empty( $data['token']['multiuse'] ) ) {
					$order->update_meta_data( '_makecommerce_payment_token', $data['token']['id'] );
					$order->update_meta_data( '_makecommerce_payment_token_valid_until', $data['token']['valid_until'] );
				}
				
				if ( isset( $_GET['lang1'] ) ) {
					$order->update_meta_data( 'wpml_language', $_GET["lang1"] );
				}

				$order->payment_complete( $transactionId );
				$woocommerce->cart->empty_cart();
				$order->update_meta_data( '_makecommerce_payment_processed_status', $paymentStatus );

				break;
		}

		// Save all changes to order
		$order->save();

		return $returnUrl;
	}

    /**
     * Returns post_id using transaction id or false if not found
     *
     * @since 3.0.4
     */
    public static function get_postid_using_metakey( $meta_key, $transactionId ) {
        global $wpdb;

        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
            // HPOS
            $results = $wpdb->get_results(
                $wpdb->prepare("
                    SELECT wp_wc_orders.id
                    FROM wp_wc_orders
                    INNER JOIN wp_wc_orders_meta AS meta ON meta.order_id = wp_wc_orders.id AND meta.meta_key = %s
                    WHERE meta.meta_value = %s
                    ",
                    $meta_key,
                    $transactionId
                )
            );

            if ( isset( $results[0]->id ) ) {
                return $results[0]->id;
            }
        } else {
            // Legacy DB
            $wpdb->query("SELECT `post_id` FROM $wpdb->postmeta WHERE `meta_key` = '". $meta_key ."' AND `meta_value` = '" . $transactionId . "'");

            if ( isset( $wpdb->last_result[0]->post_id ) ) {
                return $wpdb->last_result[0]->post_id;
            }
        }

        return false;
    }
}
