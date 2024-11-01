<?php

/**
 *
 * @link              http://www.widgilabs.com
 * @since             1.0.0
 * @package           Woocommerce_Gateway_Payger
 *
 * @wordpress-plugin
 * Plugin Name:       Woocommerce Gateway Payger
 * Plugin URI:        http://www.widgilabs.com
 * Description:       Payger Payment Gateway for Woocommerce
 * Version:           1.0.0
 * Author:            Payger
 * Author URI:        https://payger.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-gateway-payger
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WOOCOMERCE_GATEWAY_PAYGER', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woocommerce-payger-activator.php
 */
function woo_payger_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woocommerce-payger-activator.php';
	Woocommerce_Payger_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woocommerce-payger-deactivator.php
 */
function woo_payger_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woocommerce-payger-deactivator.php';
	Woocommerce_Payger_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'woo_payger_activate' );
register_deactivation_hook( __FILE__, 'woo_payger_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woocommerce-payger.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */

function woo_payger_run() {

	new Woocommerce_Payger();

}

woo_payger_run();