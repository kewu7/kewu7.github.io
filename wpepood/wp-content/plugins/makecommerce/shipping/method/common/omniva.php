<?php

namespace MakeCommerce\Shipping\Method\Common;

/**
 * Omniva specific functionality that is used by both courier and parcelmachine
 * 
 * @since 3.0.4
 */

trait Omniva {

    public $carrier = "Omniva";
    public $carrier_id = "omniva";
    public $carrier_title = "Omniva";
    public $service_name = "Omniva web services";
    public $international_number_format = false;

    /**
     * https://www.omniva.ee/public/files/failid/manual_xml_dataexchange_eng.pdf
     * 
     * Mobile phone number rules (does not include country codes)
     * 
     * Estonia – has to start with number 5 or 8 and allowed length is
     * 7(only if 5 is first number) to 8 numbers.
     * 
     * Latvia - has to start with number 2 and allowed length is 8 numbers
     * 
     * Lithuania - has to start with number 6 and allowed length is 8
     * numbers OR start with numbers 86 allowed length is 9 numbers
     * 
     * country code => array(
     *      starting number(s) => array( lenght1, lenght2 )
     * )
     */
    public array $valid_phonenumber_country_codes = array(
        "372" => array( 
            "5" => array( "7", "8" ),
            "8" => array( "8" )
        ),
        "371" => array(
            "2" => array( "8" )
        ),
        "370" => array(
            "6" => array( "8" ),
            "86" => array( "9" )
        )
    );

    /**
     * Returns invalid phone number error
     * 
     * @since 3.0.4
     */
    public function phone_number_validation_error() {
        return '<strong>' . $this->carrier_title . ' ' . $this->identifierString . '</strong> ' . __(' can only be used with Estonian, Latvian and Lithuanian numbers that are able to receive SMS (mobile phone).', 'wc_makecommerce_domain' );
    }
}
