<?php

namespace MakeCommerce\Shipping\Method\ParcelMachine;

/**
 * LP Express parcelmachine shipping method.
 * Defines all LP Express specific options.
 * 
 * @since 3.2.0
 */

class LPExpress extends \MakeCommerce\Shipping\Method\ParcelMachine {

    use \MakeCommerce\Shipping\Method\Common\LPExpress;

    public $default_price = "5.00";
    public $default_max_weight = "30";
    
    /**
     * Loads all form fields specific to this shipping method
     * 
     * @since 3.2.0
     */
    public function initialize_method_form_fields() {
        
        //This is needed for our API. It changes behaviour depending on the country your contract has been signed in
        $this->form_fields['service_carrier'] = array(
            'type' => 'select',
            'title' => __( 'Integration country', 'wc_makecommerce_domain' ),
            'options' => array(
                "LP_EXPRESS_LT" => __( 'Lithuania', 'wc_makecommerce_domain' ),
                ),
            'default' => "lt",
            'description' => __( "Which country's carrier gave you the credentials", 'wc_makecommerce_domain' ),
        );

        $presetsArray = $this->get_lp_express_presets();

        $this->form_fields['mk_lpexpress_template'] = array(
            'type' => 'select',
            'title' => __( 'Default LP Express template', 'wc_makecommerce_domain' ),
            'options' => $presetsArray,
            'default' => 63,
            'description' => __(
                "All new orders will have this template as a default but can be individually changed in the order view. <br>" .
                "When changing the template size in the order view, the packing slip will also change.", 
                'wc_makecommerce_domain' 
            ),
        );
    }

    /**
     * Adds more address fields to LP Express
     * 
     * @since 3.2.0
     */
    public function initialize_return_address_form_fields() {

        parent::initialize_return_address_form_fields();

        // Override shop_address_country to only contain LT
        $this->form_fields['shop_address_country'] = array(
            'type' => 'select',
            'title' => __( 'Shop address country', 'wc_makecommerce_domain' ),
            'options' => array(
                'LT' => 'LT',
            ),
            'default' => 'LT',
        );
        
        $this->form_fields['shop_building'] = array(
            'type' => 'text',
            'title' => __( 'Shop building', 'wc_makecommerce_domain' ),
            'class' => 'input-text regular-input',
        );

        $this->form_fields['shop_apartment'] = array(
            'type' => 'text',
            'title' => __( 'Shop apartment', 'wc_makecommerce_domain' ),
            'class' => 'input-text regular-input',
        );
    }

    /**
     * Gets template presets from API for LP Express
     * 
     * @since 3.2.0
     */
    public static function get_lp_express_presets() {

        $MK = \MakeCommerce::get_api();
		
		if ( !$MK ) {
            // Fallback for default
			return array( "63" => __( 'Parcel to Parcel size XL', 'wc_makecommerce_domain' ) );
		}

		$presetsArray = array();

		try {
			
			$presets = $MK->getLPExpressPresets();

			foreach ( $presets as $preset ) {
				$presetsArray[$preset->value] = $preset->label;
			}
		} catch ( \Exception $e ) {
			//do nothing, something is wrong with the credentials or the shop is disabled
	    }
        return $presetsArray;
    }
}