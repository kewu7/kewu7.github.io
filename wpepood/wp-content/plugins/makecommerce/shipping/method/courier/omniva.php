<?php

namespace MakeCommerce\Shipping\Method\Courier;

/**
 * Specifics for Omniva courier shipping method
 * 
 * @since 3.0.0
 */

class Omniva extends \MakeCommerce\Shipping\Method\Courier {

    use \MakeCommerce\Shipping\Method\Common\Omniva;

    public $default_price = "4.95";
    public $default_max_weight = "60";
    
    /**
     * Loads all form fields specific to this shipping method
     * 
     * @since 3.0.0
     */
    public function initialize_method_form_fields() {

        $this->form_fields['registerOnPaymentCode'] = array(
            'title'             => __('Register shipments automatically as', 'wc_makecommerce_domain'),
            'type'              => 'select',
            'label'             => __('register on Payment', 'wc_makecommerce_domain'),
            'description' 	    => __('Shipments will be automatically registered on payment using the selected Omniva service code', 'wc_makecommerce_domain'),
            'options'           => array(
                'QP' => __('QP - handover to Omniva at Post Office', 'wc_makecommerce_domain'),
                'PK' => __('PK - handover to Omniva via Parcel Machine', 'wc_makecommerce_domain')
            ),							
            'default'     => 'QP',
        );
        
        $this->form_fields['service_carrier'] = array(
            'type' => 'select',
            'title' => __('Integration country', 'wc_makecommerce_domain'),
            'options' => array(
                "OMNIVA" => __('Estonia', 'wc_makecommerce_domain'),
                "OMNIVA_LV" => __('Latvia', 'wc_makecommerce_domain'),
                "OMNIVA_LT" => __('Lithuania', 'wc_makecommerce_domain'),
                ),
            'default' => "ee",
            'description' => __("Which country's carrier gave you the credentials", 'wc_makecommerce_domain'),
        );
    }

    /**
     * Set method specific hooks/filters
     * 
     * @since 3.0.0
     */
    public function set_method_hooks() {

        //No fish to hook here yet
    }
}