<?php

/**
 * Cron functions
 *
 * @link       https://makecommerce.net/
 * @since      3.0.0
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/includes
 */

namespace MakeCommerce;

/**
 * Fired by cron
 *
 * This class defines all code necessary for plugins cron functions.
 *
 * @since      3.0.0
 * @package    Makecommerce
 * @subpackage Makecommerce/includes
 * @author     Maksekeskus AS <support@maksekeskus.ee>
 */
class Cron {

    /**
     * Initialize cron
     * 
     * @since    3.0.0
     */
    public function __construct() {

        // Assign schedule if not set, update compatible no need to reactivate, format('U') php 5.2 compatible
        // Assigns task at 23.59.59 by local time, task runs if time has passed and somebody navigates the site
        if ( !wp_next_scheduled( 'mc_banklinks_update_cron' ) ) {

            $date = new \DateTime();
            $date->setTime( rand( 0, 23 ), rand( 0, 59 ), 00 );
            $timestamp = intval( $date->format( 'U' ) );
  
            wp_schedule_event( $timestamp, 'twicedaily', 'mc_banklinks_update_cron' );
        }
    }

	/**
	 * Define query variables.
	 *
	 * @since    3.0.0
	 */
	public function query_vars( $vars ) {

        $vars[] = 'mk-action';
		$vars[] = 'mk-shop-id';
        $vars[] = 'mk-test-shop-id';
        
    	return $vars;
    }

    /**
     * Update banklinks if all conditions are met
     * 
     * @since    3.0.0
     */
    public function parse_update_vars( $wp ) {

        if (
                array_key_exists('mk-action', $wp->query_vars) && 
                $wp->query_vars['mk-action'] == 'mk-update' && 
                (
                    array_key_exists('mk-shop-id', $wp->query_vars) || 
                    array_key_exists('mk-test-shop-id', $wp->query_vars) 
                ) 
            ) {

            if (
                    (
                        strlen($wp->query_vars['mk-shop-id']) && 
                        $wp->query_vars['mk-shop-id'] == get_option('mk_shop_id', false)
                    ) || 
                    (
                        strlen($wp->query_vars['mk-test-shop-id']) && 
                        $wp->query_vars['mk-test-shop-id'] == get_option('mk_test_shop_id', false)
                    )
            ) {
                
                Payment::update_banklinks();
                exit();
            }
        }
    }

    /**
     * Updates banklinks via cron
     * 
     * @since 3.0.0
     */
    public function update_banklinks() {

        Payment::update_banklinks();
    }
}
