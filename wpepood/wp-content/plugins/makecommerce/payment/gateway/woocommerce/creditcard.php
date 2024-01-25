<?php

namespace MakeCommerce\Payment\Gateway\WooCommerce;

trait Creditcard {

    /**
     * Credit card pending payment page.
     * 
     * @since 3.0.0
     */
    public function receipt_page( $orderId ) {

        $order = wc_get_order( $orderId );
        if ( substr( $order->get_meta( '_makecommerce_preselected_method', true ), 0, 5 ) == 'card_' && $order->get_status() == 'pending' ) {

            echo "<br>".__( 'The order is still awaiting your payment', 'wc_makecommerce_domain' )."<br>";
            echo $this->generateCardForm( $order );
        }
    }

    /**
     * Generates credit card payment form
     * 
     * @since 3.0.0
     */
    public function generateCardForm( $orderId ) {

        $order = wc_get_order( $orderId );

        $has_subscription = function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order );
        $transactionId = $order->get_meta( '_makecommerce_transaction_id', true );

        $idReference = $order->get_order_number();

        $jsParams = array(
            'key' => $this->MK->getPublishableKey(),
            'transaction' => $transactionId,
            'amount' => sprintf( "%.2F", $order->get_total() ),
            'locale' => \MakeCommerce\i18n::get_two_char_locale(),
            'openonload' => 'true',
            'clientname' => ( $this->settings['cc_pass_cust_data'] == 'yes' ? ( string ) ( ( method_exists( $order, 'get_billing_first_name' )  ? $order->get_billing_first_name() : $order->billing_first_name ) . ' ' . ( method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name ) ) : '' ),
            'email' => ( $this->settings['cc_pass_cust_data'] == 'yes' ? ( string ) ( method_exists( $order, 'get_billing_email' )  ? $order->get_billing_email() : $order->billing_email ) : '' ),
            'name' => get_option( 'mc_shop_name', '' ),
            'description' => __( 'Order', 'woocommerce' ) . ' ' . ( string ) $idReference,
            'completed' => 'makecommerce_cc_complete',
            'currency' => method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->currency, 
            'backdropclose' => 'false',
        );

        if ( $has_subscription ) {

            $jsParams['recurringrequired'] = 'true';
            $jsParams['recurringtitle'] = __( 'Pay for subscription', 'wc_makecommerce_domain' );
            $jsParams['recurringdescription'] = __( 'This order contains subscriptions', 'wc_makecommerce_domain' );
            $jsParams['recurringconfirmation'] = __( 'I agree that my card will be recurringly billed by this store', 'wc_makecommerce_domain' );
        }

        \MakeCommerce::mc_enqueue_script( 
            'MC_CC_COMPLETE', 
            dirname( __FILE__ ) . '/js/mc_cc_complete.js', 
            [
                'payment_return_url' => $this->payment_return_url,
                'transaction_id' => $transactionId
            ], 
        );

        \MakeCommerce::mc_enqueue_script( 
            'MC_MAKECOMMERCE_CC_CHECKOUT', 
            $this->MK->getEnvUrls()->checkoutjsUrl.'checkout.js', 
            [], 
            [],
            true
        );

        \MakeCommerce::mc_enqueue_script( 
            'MC_CC_INIT', 
            dirname( __FILE__ ) . '/js/mc_cc_init.js', 
            $jsParams, 
        );
        
        $html = '
            <button type="button" class="btn btn-primary" aria-label="' . __( 'Pay with credit card', 'wc_makecommerce_domain' ) . '" onclick="init_mc_cc_form();">' . __( 'Pay with credit card', 'wc_makecommerce_domain' ) . '</button> 
            <div class="mc-processing-message"><img src="' . plugins_url( '/images/loading.png', __FILE__ ) . '"/>' . __( 'Please wait, processing payment...', 'wc_makecommerce_domain' ) . '</div>
        ';

        return $html;
    }
}