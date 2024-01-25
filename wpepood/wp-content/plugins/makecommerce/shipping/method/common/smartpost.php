<?php

namespace MakeCommerce\Shipping\Method\Common;

/**
 * Omniva specific functionality that is used by both courier and parcelmachine
 * 
 * @since 3.0.4
 */

trait Smartpost {

    public $carrier = "Smartpost";
    public $carrier_id = "smartpost";
    public $carrier_title = "Smartpost";
    public $service_name = "eservice.smartpost.ee";
    public $international_number_format = true;

    /**
     * https://api.posti.fi/api-shipments.html
     * 
     * Telephone number (with country code e.g. +3580501234567)
     * 
     * country code => array(
     *      starting number(s) => array( lenght1, lenght2 )
     * )
     */
    //Smartpost does not define any restrictions on phone numbers. Leave $valid_phonenumber_country_codes undefined

    /**
     * Returns invalid phone number error
     * 
     * @since 3.0.4
     */
    public function phone_number_validation_error() {
        return '<strong>' . $this->carrier_title . ' ' . $this->identifierString . '</strong> ' . __(' can be used with an international phone number only. Please specify your phone number with international country code (e.g. +372xxxxxxx)', 'wc_makecommerce_domain' );
    }

    /**
     * Loads API key field for SmartPost
     * 
     * @since 3.2.1
     */
    public function initialize_smartpost_api_field() {

        $this->form_fields['api_key'] = array(
            'title'            =>  __( 'SmartPost API Key', 'wc_makecommerce_domain' ),
            'type'             => 'text',
            'default'          => ''
        );
    }

    /**
     * Updates API key field for SmartPost
     * Only called when the plugin has been updated, while the mc_version is not yet updated and the old version is < 3.3.0
     * Only runs once, not called after the client has a version 3.3.0 or older
     * 
     * @since 3.3.0
     */
    public function v3_2_2_api_key_migration() {

        $optionName = 'woocommerce_' . $this->id . '_settings';

        $options = get_option( $optionName );
        // If api key is not already set or the value of it is empty
        if ( empty( $options['api_key'] ) ) {
            // If the service password is set and it is not empty
            if ( !empty( $options['service_password'] ) ) {
                $options['api_key'] = $options['service_password'];
            }
        }
        // Regardless of the api_key, delete the user and password
        unset( $options['service_password'] );
        unset( $options['service_user'] );

        update_option( $optionName, $options );
    }

}
