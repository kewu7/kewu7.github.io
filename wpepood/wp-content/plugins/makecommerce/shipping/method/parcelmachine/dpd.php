<?php

namespace MakeCommerce\Shipping\Method\ParcelMachine;

/**
 * DPD parcelmachine shipping method.
 * Defines all DPD specific options.
 * 
 * @since 3.0.0
 */

class DPD extends \MakeCommerce\Shipping\Method\ParcelMachine {

    use \MakeCommerce\Shipping\Method\Common\DPD;

    public $default_price = "5.00";
    public $default_max_weight = "30";
    
    /**
     * Loads all form fields specific to this shipping method
     * 
     * @since 3.0.0
     */
    public function initialize_method_form_fields() {

        // MakeCommerce TMS or DPD contract
        $this->form_fields['use_mk_contract'] = [
            'type' => 'select',
            'title' => __( 'Contract', 'wc_makecommerce_domain' ),
            'options' => [
                false => __( 'use my own DPD contract', 'wc_makecommerce_domain' ),
                true => __( 'use MakeCommerce transport mediation service', 'wc_makecommerce_domain' ),
            ],
            'default' => false,
            'description' => '',
        ];

        //Verifies if you can use MakeCommerce as shipment mediation service
        $this->form_fields['verify_feature_swc'] = [
            'type' => 'verify_feature_swc',
            'title' => __( 'Verify service status', 'wc_makecommerce_domain' ),
            'description' => __( 'You must enable the Transport mediation service before using it.', 'wc_makecommerce_domain' ),
            'desc_tip' => __( 'This will check if the Transport mediation service has been enabled for your shop.', 'wc_makecommerce_domain' ),
            'placeholder'  => __( 'Verify', 'wc_makecommerce_domain' ),
        ];
        
        //This is needed for our API. It changes behaviour depending on the country your contract has been signed in
        $this->form_fields['service_carrier'] = [
            'type' => 'select',
            'title' => __( 'Integration country', 'wc_makecommerce_domain' ),
            'options' => [
                "DPD" => __( 'Estonia', 'wc_makecommerce_domain' ),
                "DPD_LV" => __( 'Latvia', 'wc_makecommerce_domain' ),
                "DPD_LT" => __( 'Lithuania', 'wc_makecommerce_domain' ),
            ],
            'default' => "ee",
            'description' => __( "Which country's carrier gave you the credentials", 'wc_makecommerce_domain' ),
        ];

        $this->form_fields['credentials_description'] = [
            'type' => 'title',
            'title' => '',
            'description' => sprintf('%s <br>', __( 'You can now use the API Key for a more streamlined integration process. Please note that the traditional username and password authentication method will be deprecated in the near future. We strongly encourage you to switch to API Key authentication as soon as possible.', 'wc_makecommerce_domain' ) ) .
                sprintf('%s <br>', __( 'To obtain the API key please contact your sales manager or DPD:', 'wc_makecommerce_domain' ) ) .
                sprintf('%s <br>', __( 'Estonia: sales@dpd.ee', 'wc_makecommerce_domain' ) ) .
                sprintf('%s <br>', __( 'Latvia: sales@dpd.lv', 'wc_makecommerce_domain' ) ) .
                sprintf('%s <br>', __( 'Lithuania: sales@dpd.lt', 'wc_makecommerce_domain' ) )
        ];

        $options = get_option( 'woocommerce_' . $this->id . '_settings' );

        // Show warning if api key is not already set or the value of it is empty
        if ( empty( $options['api_key'] ) ) {
            $this->form_fields['dpd_apikey_warning'] = [
                'type' => 'title',
                'title' => '',
                'description' => sprintf( '<b>%s</b>', __( 'Please be aware that changing the credentials to API key will affect generating labels for all old shipments. Please generate all labels needed for already created shipments before migrating to new API.', 'wc_makecommerce_domain' ) )
            ];
        }

        $this->initialize_dpd_api_field();
    }
}
