<?php

namespace MakeCommerce\Payment\Gateway;

trait Refund {

    /**
     * Processes refunds. Automatically called by \WC_Payment_Gateway
     * 
     * @since 3.0.0
     */
    public function process_refund( $order_id, $amount = null, $comment = '' ) {

        if ( $this->MK ) {

            try {

                $order = wc_get_order( $order_id );
                $transactionId = $order->get_transaction_id();

                if ( empty( $transactionId ) ) {
                    $transactionId = $order->get_meta( '_makecommerce_transaction_id', true );
                }
                
                if ( $response = $this->MK->createRefund( $transactionId, array( 'amount' => sprintf( "%.2f", $amount ), 'comment' => ( $comment ? : 'refund' ) ) ) ) {
                    if ( $status = ( string )$response->transaction->status ) {
                        
                        switch ( $status ) {
                            case "REFUNDED": 
                                $order->add_order_note( sprintf( __( 'Refund completed for amount %s', 'wc_makecommerce_domain' ), $amount ) );
                                return true;
                                break;
                            case "PART_REFUNDED": 
                                $order->add_order_note( sprintf( __( 'Partial refund completed for amount %s', 'wc_makecommerce_domain' ), $amount ) );
                                return true;
                                break;
                        }
                    }
                }

                return false;
                
            } catch ( \Exception $e ) {
                if ( strval( $e->getCode() ) === '1045' ) {
                    return new \WP_Error( 
                        'makecommerce_refund_error',
                        __( 'Could not create refund: payment is still being processed, try to refund later', 'wc_makecommerce_domain' )
                    );
                }
                return new \WP_Error( 'makecommerce_refund_error', $e->getMessage() );
            }

            return false;
        }

        return false;
    }
}