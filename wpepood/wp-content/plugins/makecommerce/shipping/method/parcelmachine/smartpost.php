<?php

namespace MakeCommerce\Shipping\Method\ParcelMachine;

/**
 * Smartpost parcelmachine shipping method.
 * Defines all Smartpost specific options.
 * 
 * @since 3.0.0
 */

class Smartpost extends \MakeCommerce\Shipping\Method\ParcelMachine {
    
    use \MakeCommerce\Shipping\Method\Common\Smartpost;

    public $default_price = "5.00";
    public $default_max_weight = "35";

    /**
     * Overrides Method class function
     * Removes loading of user and password fields
     * 
     * @since 3.3.0
     */
    public function initialize_method_type_form_fields() {

        //Initialize method specific fields
        $this->initialize_method_form_fields();
    }

    /**
     * Loads all form fields specific to this shipping method
     * 
     * @since 3.0.0
     */

    public function initialize_method_form_fields() {

        $this->initialize_smartpost_api_field();
        
        //option to disable white parcel machines, if only machines capable of recipient authentication are required.
        $this->form_fields['use_white_apts'] = array(
            'type' => 'select',
            'title' => __( 'Use authenticable APTs', 'wc_makecommerce_domain' ),
            'options' => array(
                false => __( 'use all APTs', 'wc_makecommerce_domain' ),
                true => __( 'use only non-white auth-capable APTs', 'wc_makecommerce_domain' ),
                ),
            'default' => false,
            'description' => '',
        );

    }
}
