<?php

/**
 * @link            	  https://makecommerce.net/
 * @since           	  3.0.0
 * @package           	Makecommerce
 *
 * @wordpress-plugin
 * Plugin Name: 	      MakeCommerce
 * Plugin URI:      	  https://makecommerce.net/
 * Description:	    	  Adds MakeCommerce payment gateway and Itella/Omniva/DPD parcel machine shipping methods to WooCommerce checkout
 * Version:     	      3.4.2
 * Author:        		  Maksekeskus AS
 * Author URI:        	  https://makecommerce.net/
 * License:               GPL-2.0+
 * License URI:           http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:           makecommerce
 * Domain Path:           /languages
 * Requires at least:	  5.6.1
 * Requires PHP: 		  7.4
 * WC requires at least:  5.0.0
 * WC tested up to:       8.2.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 3.0.0 and use SemVer - https://semver.org
 */
define( 'MAKECOMMERCE_VERSION', '3.4.2' );
define( 'MAKECOMMERCE_PLUGIN_ID', 'makecommerce' );

//table name for banklinks
define( 'MAKECOMMERCE_TABLENAME', 'mc_banklinks' );

//WC makecommerce version
define( 'WC_MC_VERSION', 2.0 );

//Tracking service link
define( 'MC_TRACKING_SERVICE_URL', 'https://tracking.makecommerce.net/' );

register_activation_hook( __FILE__, 'activate_makecommerce' );
register_deactivation_hook( __FILE__, 'deactivate_makecommerce' );

// Declare HPOS compatibility - true / false
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Check if we are ok to continue (if namesoace and class doesn't already exist)
 */
if ( class_exists( 'MakeCommerce' ) || namespaceExists( 'MakeCommerce' ) ) {

	add_action( 'admin_notices', 'namespace_or_class_already_in_use' );

	deactivate_plugins( plugin_dir_path( __FILE__ ) . '/makecommerce.php' );

	return false;
}

/**
 * Check if a namespace already exists
 * 
 * @since 3.0.0
 */
function namespaceExists( $namespace ) {

    $namespace .= "\\";
	
	foreach ( get_declared_classes() as $name ) {
		
		if ( strpos( $name, $namespace ) === 0 ) {
			return true;
		}
	}

    return false;
}

/**
 * Shows error in admin that we cannot continue
 * 
 * @since 3.0.0
 */
function namespace_or_class_already_in_use() {
	
    ?>
    <div class="error notice">
        <p><?php _e( 'Cannot initiate MakeCommerce, a class and/or namespace called "MakeCommerce" is already in use somewhere else...', 'wc_makecommerce_domain' ); ?></p>
    </div>
    <?php
}

/**
 * Check if WooCommerce exists and is active. Can't do much without it
 */
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	if ( is_multisite() ) {

		include_once( ABSPATH . "wp-admin/includes/plugin.php" );
		if ( !is_plugin_active_for_network( "woocommerce/woocommerce.php" ) ) {
			woocommerce_not_found_or_active();

			return false;
		}
	} else {
		woocommerce_not_found_or_active();

		return false;
	}
}

/**
 * If no WooCommerce is found
 * 
 * @since 3.0.3
 */
function woocommerce_not_found_or_active() {

	add_action( 'admin_notices', 'no_woocommerce_found' );

	deactivate_makecommerce();
}

/**
 * Shows error in admin that we cannot continue
 * 
 * @since 3.0.0
 */
function no_woocommerce_found() {
	
    ?>
    <div class="error notice">
        <p><?php _e( 'Cannot initiate MakeCommerce, there seems to be no WooCommerce present or active...', 'wc_makecommerce_domain' ); ?></p>
    </div>
    <?php
}

/**
 * Disable automatic updates for MakeCommerce plugin
 * 
 * @since 3.0.1
 */
function disable_makecommerce_automatic_updates( $should_update, $plugin ) {

	if ( ! isset( $plugin->plugin, $plugin->new_version ) ) {
		return $should_update;
	}

	if ( 'makecommerce/makecommerce.php' !== $plugin->plugin ) {
		return $should_update;
	}

	return false;
}

add_filter( 'auto_update_plugin', 'disable_makecommerce_automatic_updates', 99, 2 );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-makecommerce-activator.php
 * 
 * @since 3.0.1
 */
function activate_makecommerce() {

	require_once plugin_dir_path( __FILE__ ) . 'includes/activator.php';
	MakeCommerce\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-makecommerce-deactivator.php
 * 
 * @since 3.0.1
 */
function deactivate_makecommerce() {

	require_once plugin_dir_path( __FILE__ ) . 'includes/deactivator.php';
	MakeCommerce\Deactivator::deactivate();
}

/**
 * The core plugin class that is used to define internationalization,
 * payment-specific hooks, and shipping-specific site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/makecommerce.php';

/**
 * Begins execution of the plugin.
 *
 * @since 3.0.0
 */
function run_makecommerce() {

	$makeCommerce = new MakeCommerce();
	$makeCommerce->run();
}

run_makecommerce();