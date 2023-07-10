<?php
/**
 * Plugin Name:       UBC ePayment(DPP)
 * Description:       Integration with UBC DPP ePayment gateway (uPay).
 * Version:           0.2.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Kelvin Xu, Rich Tape
 * Author URI:        https://ctlt.ubc.ca/
 * Text Domain:       ubc-dpp
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package ubc-dpp
 */

namespace UBC\CTLT\DPP;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

define( 'UBC_DPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UBC_DPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialization of the plugin
 *
 * @since    0.1.0
 */
function init() {

	require_once UBC_DPP_PLUGIN_DIR . 'includes/class-gforms-addon.php';
	require_once UBC_DPP_PLUGIN_DIR . 'includes/class-helper.php';

	\GFAddOn::register( __NAMESPACE__ . '\\GForms_Addon' );

	// If the client hasn't been onboarded with uPay, nothing happens.
	if ( ! Helper::is_upay_onboarded() ) {
		return;
	}

	require_once UBC_DPP_PLUGIN_DIR . 'includes/class-logger.php';
	require_once UBC_DPP_PLUGIN_DIR . 'includes/class-payment-request.php';
	require_once UBC_DPP_PLUGIN_DIR . 'includes/class-gforms-integration.php';
	require_once UBC_DPP_PLUGIN_DIR . 'includes/class-status-listener.php';
	require_once UBC_DPP_PLUGIN_DIR . 'includes/class-redirects.php';
	require_once UBC_DPP_PLUGIN_DIR . 'includes/class-payment-logs.php';

	Payment_Logs::init();
	Gforms_Integration::init();
	Status_Listener::init();
	Redirects::init();

}//end init()

/**
 * Add plugin required rewrite rules.
 */
function add_rewrite_rules() {
	// Add rewrite rule for api response route.
	add_rewrite_rule( '^ubc-upay-template$', 'index.php?payment_amount=$matches[1]&payment_request_number=matches[2]&form_id=matches[3]', 'top' );

	add_rewrite_rule(
		'^ubc-epayment/(\S*)/?$',
		'index.php?upayredirect=$matches[1]',
		'top'
	);

	add_rewrite_tag( '%upayredirect%', '(.*)' );
}//end add_rewrite_rules()

/**
 * Actions upon plugin activation
 *
 * @return void
 */
function activate_plugin() {

	// Dependency checking
	// Make sure the Gravity Forms plugin is activated before we are able to activate the Digital Payments plugin.
	if ( ! class_exists( 'GFCommon' ) ) {
		wp_die( esc_textarea( __( 'Please activate the Gravity Froms plugin before using the Digital Payments plugin.' ), 'ubc-dpp' ) );
	}

	if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
		wp_die( esc_textarea( __( 'Please activate the Gravity Froms plugin before using the Digital Payments plugin.' ), 'ubc-dpp' ) );
	}

	add_rewrite_rules();
	flush_rewrite_rules();
}//end activate_plugin()

/** ------------------------------------------------------------------------------------------------------------------------ */

// Initiate the plugin.
add_action( 'gform_loaded', __NAMESPACE__ . '\\init' );

// Add rewrite rules.
add_action( 'init', __NAMESPACE__ . '\\add_rewrite_rules' );

// Plugin activation check.
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_plugin' );
