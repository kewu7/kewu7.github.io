<?php

namespace MakeCommerce\Shipping\Method\Courier;

/**
 * Specifics for DPD courier shipping method
 * 
 * @since 3.4.0
 */

class DPD extends \MakeCommerce\Shipping\Method\Courier {

    use \MakeCommerce\Shipping\Method\Common\DPD;

    public $default_price = "4.95";
    public $default_max_weight = "60";

    /**
     * Overrides Method class function
     * Removes loading of user and password fields
     *
     * @since 3.4.0
     */
    public function initialize_method_type_form_fields() {

        //Initialize method specific fields
        $this->initialize_method_form_fields();
    }
    
    /**
     * Loads all form fields specific to this shipping method
     * 
     * @since 3.4.0
     */
    public function initialize_method_form_fields() {

        $this->form_fields['registerOnPaymentCode'] = [
            'title'             => __('Register shipments automatically as', 'wc_makecommerce_domain'),
            'type'              => 'select',
            'label'             => __('register on Payment', 'wc_makecommerce_domain'),
            'description' 	    => __('Shipments will be automatically registered on payment using the selected DPD service code', 'wc_makecommerce_domain'),
            'options'           => [
                'QP' => __('QP - handover to DPD at Post Office', 'wc_makecommerce_domain'),
                'PK' => __('PK - handover to DPD via Parcel Machine', 'wc_makecommerce_domain')
            ],
            'default'     => 'QP',
        ];
        
        $this->form_fields['service_carrier'] = [
            'type' => 'select',
            'title' => __('Integration country', 'wc_makecommerce_domain'),
            'options' => [
                "DPD" => __('Estonia', 'wc_makecommerce_domain'),
                "DPD_LV" => __('Latvia', 'wc_makecommerce_domain'),
                "DPD_LT" => __('Lithuania', 'wc_makecommerce_domain'),
            ],
            'default' => "DPD",
            'description' => __("Which country's carrier gave you the credentials", 'wc_makecommerce_domain'),
        ];

        $this->initialize_dpd_api_field();
    }

    /**
     * Set method specific hooks/filters
     * 
     * @since 3.4.0
     */
    public function set_method_hooks() {

        //No fish to hook here yet
    }
}
