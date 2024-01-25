<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://makecommerce.net/
 * @since      3.0.0
 *
 * @package    Makecommerce
 * @subpackage Makecommerce/includes
 */

namespace MakeCommerce;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      3.0.0
 * @package    Makecommerce
 * @subpackage Makecommerce/includes
 * @author     Maksekeskus AS <support@maksekeskus.ee>
 */
class Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    3.0.0
	 */
	public static function deactivate() {
		
		//deactivate
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		//remove cronjobs
		if ( wp_next_scheduled( 'mc_banklinks_update_cron' ) ) {
			
			wp_clear_scheduled_hook( 'mc_banklinks_update_cron' );
		}
	}
}
