<?php

namespace MakeCommerce\Shipping\Method\ParcelMachine;

/**
 * Omniva parcelmachine shipping method.
 * Defines all Omniva specific options.
 * 
 * @since 3.0.0
 */

class Omniva extends \MakeCommerce\Shipping\Method\ParcelMachine {
    
    use \MakeCommerce\Shipping\Method\Common\Omniva;
    
    public $default_price = "5.00";
    public $default_max_weight = "30";

    /**
     * Loads all form fields specific to this shipping method
     * 
     * @since 3.0.0
     */
    public function initialize_method_form_fields() {

        //option if we are using MakeCommerce API or Omniva API for shipment registrations 
        $this->form_fields['use_mk_contract'] = array(
            'type' => 'select',
            'title' => __( 'Contract', 'wc_makecommerce_domain' ),
            'options' => array(
                false => __( 'use my own Omniva contract', 'wc_makecommerce_domain' ),
                true => __( 'use MakeCommerce transport mediation service', 'wc_makecommerce_domain' ),
                ),
            'default' => false,
            'description' => '',
        );
        
        //Verifies if you can use MakeCommerce as shipment mediation service
        $this->form_fields['verify_feature_swc'] = array(
            'type' => 'verify_feature_swc',
            'title' => __( 'Verify service status', 'wc_makecommerce_domain' ),
            'description' => __( 'You must enable the Transport mediation service before using it.', 'wc_makecommerce_domain' ),
            'desc_tip' => __( 'This will check if the Transport mediation service has been enabled for your shop.', 'wc_makecommerce_domain' ),
            'placeholder'  => __( 'Verify', 'wc_makecommerce_domain' ),
        );
        
        $this->form_fields['service_carrier'] = array(
            'type' => 'select',
            'title' => __( 'Integration country', 'wc_makecommerce_domain' ),
            'options' => array(
                "OMNIVA" => __( 'Estonia', 'wc_makecommerce_domain' ),
                "OMNIVA_LV" => __( 'Latvia', 'wc_makecommerce_domain' ),
                "OMNIVA_LT" => __( 'Lithuania', 'wc_makecommerce_domain' ),
                ),
            'default' => "ee",
            'description' => __( "Which country's carrier gave you the credentials", 'wc_makecommerce_domain' ),
        );
    }
}
