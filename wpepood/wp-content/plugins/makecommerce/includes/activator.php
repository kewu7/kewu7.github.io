<?php

namespace MakeCommerce;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      3.0.0
 * @package    Makecommerce
 * @subpackage Makecommerce/includes
 * @author     Maksekeskus AS <support@maksekeskus.ee>
 */

 class Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    3.0.0
	 */
	public static function activate() {
		
		self::create_tables();
		
	}

	/**
	 * Creates db tables.
	 * 
	 * @since 3.0.12
	 */
	public static function create_tables() {
		global $wpdb;
		
		//Create table if it doesn't already exist
		$wpdb->query
		("
			CREATE TABLE IF NOT EXISTS 
				`" . $wpdb->prefix . MAKECOMMERCE_TABLENAME . "`
				(
					`id` mediumint(9) NOT NULL AUTO_INCREMENT, 
					`type` varchar(10) NOT NULL, 
					`country` char(2) NOT NULL, 
					`name` varchar(25) NOT NULL, 
					`url` varchar(250) NOT NULL, 
					`logo_url` varchar(250), 
					`channel` varchar(250),
					`display_name` varchar(250),
					`min_amount` mediumint(9), 
					`max_amount` mediumint(9), 
					PRIMARY KEY `id` (`id`)
				) 
			" . $wpdb->get_charset_collate() . ";
		");
		
	}

}
