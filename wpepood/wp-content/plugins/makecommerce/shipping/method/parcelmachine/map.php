<?php

namespace MakeCommerce\Shipping\Method\ParcelMachine;

/**
 * Google maps functionality for parcel machines
 * 
 * @since 3.3.0
 */

trait Map {

    /**
     * Initializes the map
     * 
     * @since 3.3.0
     */
    public static function initialize_map( $vars ) {

        $vars['path'] = plugin_dir_url(__FILE__) . '/images';

        \MakeCommerce::mc_enqueue_script( 'MC_PARCELMACHINE_MAP_JS', dirname(__FILE__) . '/js/parcelmachinemap.js', $vars, [ 'jquery' ] );
		
        wp_enqueue_style( "mc_parcelmachinemap", plugin_dir_url(__FILE__) . 'css/parcelmachinemap.css', array(), MAKECOMMERCE_VERSION );
    }

    /**
     * Checks if map is enabled in admin settings
     * 
     * @since 3.3.0
     */
    public static function map_enabled() {

        return !empty( get_option( 'mc_google_api_key', '' ) )
            && get_option( 'mc_parcel_machine_map', '' ) === 'yes';
    }

    /**
     * Checks if secret key is enabled in admin settings
     *
     * @since 3.3.0
     */
    public static function geocoding_enabled() {
        return !empty( get_option( 'mc_map_geocoding_api_key', '' ) )
               && get_option( 'mc_map_geocoding', '' ) === 'yes';
    }
}
