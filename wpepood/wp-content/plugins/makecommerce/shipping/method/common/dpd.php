<?php

namespace MakeCommerce\Shipping\Method\Common;

/**
 * Omniva specific functionality that is used by both courier and parcelmachine
 * 
 * @since 3.0.4
 */

trait DPD {

    public $carrier = "DPD";
    public $carrier_id = "dpd";
    public $carrier_title = "DPD";
    public $service_name = "DPD InterConnect";
    public $international_number_format = true;

    /**
     * https://www.dpd.com/ee/wp-content/uploads/sites/235/2020/04/Interconnector_dokumentatsioon-1.pdf
     * 
     * Recipient phone number, must start with international
     * country code. e.g. +372555555, +37065123456
     * 
     * country code => array(
     *      starting number(s) => array( lenght1, lenght2 )
     * )
     */
    //DPD does not define any restrictions on phone numbers. Leave $valid_phonenumber_country_codes undefined

    /**
     * Returns invalid phone number error
     * 
     * @since 3.0.4
     */
    public function phone_number_validation_error() {
        return '<strong>' . $this->carrier_title . ' ' . $this->identifierString . '</strong> ' . __(' can be used with an international phone number only. Please specify your phone number with international country code (e.g. +372xxxxxxx)', 'wc_makecommerce_domain' );
    }

    /**
     * Loads API key field for DPD
     *
     * @since 3.4.0
     */
    public function initialize_dpd_api_field() {

        $this->form_fields['api_key'] = [
            'title'            =>  __( 'DPD API Key', 'wc_makecommerce_domain' ),
            'type'             => 'text',
            'default'          => ''
        ];
    }
}
