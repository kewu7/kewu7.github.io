<?php

namespace MakeCommerce\Shipping\Method\Common;

/**
 * LP Express specific functionality that is used by parcel machines
 * 
 * @since 3.2.0
 */

trait LPExpress {

    public $carrier = "LP Express";
    public $carrier_id = "lp_express_lt";
    public $carrier_title = "LP Express";
    public $service_name = "LP Express";
    public $international_number_format = false;

    public array $valid_phonenumber_country_codes = array(
        "370" => array(
            "*" => array( "8" ),
        )
    );

    /**
     * Returns invalid phone number error
     * 
     * @since 3.2.0
     */
    public function phone_number_validation_error() {
        return '<strong>' . $this->carrier_title . ' ' . $this->identifierString . '</strong> ' . __(' can be used with a Lithuanian phone number only. Please specify your phone number with Lithuanian country code (e.g. +370xxxxxxxx)', 'wc_makecommerce_domain' );
    }
}