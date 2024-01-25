<?php

namespace MakeCommerce\Payment\Gateway;

trait Subscription {

    /**
     * Subscription payment, called by hooks woocommerce_scheduled_subscription_payment_[id] and scheduled_subscription_payment_[id]
     * 
     * @since 3.0.0
     */
    public function process_subscription_payment_start( $amount_to_charge, $renewal_order, $product_id = '' ) {

        $result = $this->process_subscription_payment( $renewal_order, $amount_to_charge );
        
        if ( is_wp_error( $result ) ) {
            \WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $renewal_order, $product_id );
        } else {
            \WC_Subscriptions_Manager::process_subscription_payments_on_order( $renewal_order );
        }
    }

    /**
     * Process subscription payment itself
     * 
     * @since 3.0.0
     */
    public function process_subscription_payment( $order, $amount = 0 ) {

        if ( 0 === $amount ) {
            $order->payment_complete();

            return true;
        }
        
        if ( 'processing' === $order->get_status() || 'completed' === $order->get_status() ){
            return true;
        }

        $order_id = \WC_Subscriptions_Renewal_Order::get_parent_order_id( $order );

        $parent_order = wc_get_order( $order_id );
        $payment_token = $parent_order->get_meta( '_makecommerce_payment_token', true );
        $payment_token_valid_until = $parent_order->get_meta( '_makecommerce_payment_token_valid_until', true );

        error_log( $payment_token.'=>'. $payment_token_valid_until.'=>'. $order->get_status() );

        $body = array(
            'transaction' => array(
                'amount' => ( string )sprintf( "%.2f", $amount ),
                'currency' => method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->currency,
                'reference' => $order->get_order_number(),
            ),
            'customer' => array(
                'email' => $order->get_billing_email(),
                'ip' => $order->get_customer_ip_address(),
                'country' => strtolower( $order->get_billing_country() ),
                'locale' => strtolower( \MakeCommerce\i18n::get_two_char_locale() ),
            )
        );

        $transaction = $this->MK->createTransaction( $body );

        $paymentRequest = array(
            'amount' => (string )sprintf( "%.2f", $transaction->amount ), 
            'currency' => $transaction->currency,
            'token' => $payment_token
        );

        try {
        
            $payment = $this->MK->createPayment( $transaction->id, $paymentRequest );
        } catch ( \Exception $e ) {

            error_log( 'Payment failed ['.$e->getMessage().']' );
            $order->add_order_note( __( 'Unable to renew subscription', 'wc_makecommerce_domain' )."\r\n".$e->getMessage() );

            return new \WP_Error( 'makecommerce_payment_declined', __( 'Renewal payment was declined', 'wc_makecommerce_domain' ) );
        }

        $orderNote = array();
        $orderNote[] = __( 'Transaction ID', 'wc_makecommerce_domain' ) . ': <a target=_blank href="'.$this->MK->getEnvUrls()->merchantUrl.'merchant/shop/deals/detail.html?id='. $transaction->id .'">'.$transaction->id.'</a>';
        $orderNote[] = __( 'Payment option', 'wc_makecommerce_domain' ) . ': ' . $order->get_meta( '_makecommerce_preselected_method', true );

        $order->add_order_note( implode( "\r\n", $orderNote ) );
        $order->payment_complete( $transaction->id );

        return true;
    }
    
}
