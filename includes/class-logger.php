<?php
/**
 * A Logger class which, when the site puts the DPP plugin into debugging mode, will output
 * info throughout the process. Outputs to wp-content/dpp-debug.log
 *
 * Example usage: \UBC\CTLT\DPP\Logger::log( $some_variable );
 *
 * Or, if you're already within the UBC\CTLT\DPP namespace in your current code, then simply
 *
 * Logger::log( $some_variable );
 *
 * Where $some_variable is the thing you wish to log.
 *
 * We output the line number and file that made the call as well as the type of data, the data
 * itself, the URL of the site doing the logging, and the current time in a readable format.
 *
 * @since 0.2.0
 * @package ubc-dpp
 */

namespace UBC\CTLT\DPP;

/**
 * Helper class implementation.
 */
class Logger {

	/**
	 * Outputs $data_to_log to the wp-content/dpp-debug.log file.
	 *
	 * @param mixed $data_to_log The data to log
	 * @return void
	 */
	public static function log( $data_to_log ) {

		if ( ! self::is_debug_mode_on() ) {
			return;
		}

		// Use debug_backtrace to get details about where this is being called from.
		$bt     = debug_backtrace(); // phpcs:ignore
		$caller = array_shift( $bt );

		$file = $caller['file']; // akin to __FILE__
		$line = $caller['line']; // akin to __LINE__

		$type = gettype( $data_to_log );

		$output = array(
			'__FILE__' => $file,
			'__LINE__' => $line,
			'type'     => $type,
			'data'     => $data_to_log,
			'site'     => $_SERVER['SERVER_NAME'],
			'time'     => date( 'l jS \of F Y h:i:s A' ), // phpcs:ignore
		);

		file_put_contents( WP_CONTENT_DIR . '/dpp-debug.log', print_r( $output, true ), FILE_APPEND ); // phpcs:ignore

	}//end log()

	/**
	 * Determine if debug mode is enabled. This is a setting only visible to super admins.
	 *
	 * @return boolean
	 */
	public static function is_debug_mode_on() {

		$gforms_addon = GForms_Addon::get_instance();

		return ! empty( $gforms_addon->get_plugin_setting( 'ubc_upay_enable_debugging' ) );

	}//end is_debug_mode_on()

}//end class Logger
