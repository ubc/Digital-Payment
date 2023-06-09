<?php
/**
 * Handles the redirects after someone comes back from the payment side. There are 3 scenarios:
 * Success: /ubc-epayment/success/
 * Error: /ubc-epayment/error/
 * Cancelled: /ubc-epayment/cancelled/
 *
 * @package ubc-dpp
 * @since 0.1.0
 */

namespace UBC\CTLT\DPP;

/**
 * Handles the redirects after someone finishes their epayments sessions (successfully or otherwise)
 */
class Redirects {

	/**
	 * Initialize our redirect rules, query vars etc.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function init() {

		add_filter( 'query_vars', array( __CLASS__, 'register_redirect_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'register_template_redirects' ) );
		add_filter( 'the_content', array( __CLASS__, 'manage_message_type_redirect_template_content' ) );

	}//end init()

	/**
	 * Registers the query vars that are made available in
	 * register_redirect_rules()
	 *
	 * @since 0.1.0
	 *
	 * @param array $query_vars The to-this-point registered query vars.
	 * @return array $query_vars The registered Query Variables including our custom ones.
	 */
	public static function register_redirect_query_vars( $query_vars ) {

		$query_vars[] = 'upayredirect';

		return $query_vars;

	}//end register_redirect_query_vars()

	/**
	 * Detect when we are calling our upayredirect endpoint and then use the appropriate
	 * template to give the expected response.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function register_template_redirects() {

		global $wp_query;

		// Check that we have a redirect. If not, bail.
		if ( ! isset( $wp_query->query_vars['upayredirect'] ) ) {
			return;
		}

		$redirect_type = sanitize_text_field( $wp_query->query_vars['upayredirect'] ); // should be a list of known redirects.

		Logger::log( $redirect_type );

		// Check that the 'task' we are after is known.
		$allowed_redirects = array(
			'success',
			'error',
			'cancelled',
		);

		if ( ! in_array( $redirect_type, array_values( $allowed_redirects ) ) ) {
			return;
		}

		include_once "views/redirect-$redirect_type.php";

		exit;

	}//end register_template_redirects()

	/**
	 * Handle confirmation after user has been redirected back from Touchnet.
	 *
	 * @since 0.1.0
	 *
	 * @param  string $payment_request_number The unique payment request number.
	 * @param  string $status status of the payment. (success, cancelled, error).
	 * @return void
	 */
	public static function do_confirmaton( $payment_request_number, $status ) {

		Logger::log( $payment_request_number );

		$entries = \GFAPI::get_entries(
			0,
			array(
				'field_filters' => array(
					array(
						'key'   => 'payment_request_number',
						'value' => $payment_request_number,
					),
				),
			),
		);

		Logger::log( $entries );

		if ( count( $entries ) === 0 ) {
			Logger::log( $entries );
			wp_die( esc_html__( 'Unable to locate the entry based on payment_request_number.', 'ubc-dpp' ) );
		}

		$entry   = $entries[0];
		$form_id = (int) $entry['form_id'];
		$form    = \GFAPI::get_form( $form_id );

		// ************************* Payment form check ************************* //
		if ( ! Helper::is_payment_form( $form ) ) {
			Logger::log( $form );
			return;
		}

		Logger::log( $form );

		$default_confirmation_array = array_filter(
			$form['confirmations'],
			function( $confirmation ) {
				return array_key_exists( 'isDefault', $confirmation ) && true === boolval($confirmation['isDefault']);
			}
		);

		$default_confirmation = reset( $default_confirmation_array );

		$nonce = wp_create_nonce( 'ubc-upay-confirmation-redirect' );

		Logger::log( $default_confirmation );

		if ( 'message' === $default_confirmation['type'] || 'cancelled' === $status || 'error' === $status ) {
			$url = add_query_arg(
				array(
					'form_id'                => $form_id,
					'entry_id'               => (int) $entry['id'],
					'payment_request_number' => $payment_request_number,
					'_nonce'                 => $nonce,
					'upay_status'            => $status,
				),
				esc_url_raw( $entry['source_url'] )
			);

			Logger::log( $url );

			if ( wp_safe_redirect( $url, 302 ) ) {
				exit;
			} else {
				wp_die( esc_html__( 'Redirection cannot be processed.', 'ubc-dpp' ) );
			}
		}

		if ( 'page' === $default_confirmation['type'] && 'success' === $status ) {
			$url = get_permalink( (int) $default_confirmation['pageId'] );

			Logger::log( $url );

			if ( wp_safe_redirect( $url ) ) {
				exit;
			} else {
				wp_die( esc_html__( 'Redirection cannot be processed.', 'ubc-dpp' ) );
			}
		}

		if ( 'redirect' === $default_confirmation['type'] && 'success' === $status ) {
			$url = empty( $default_confirmation['queryString'] ) ? esc_url_raw( $default_confirmation['url'] ) : esc_url_raw( $default_confirmation['url'] ) . '?' . \GFCommon::replace_variables( $default_confirmation['queryString'], $form, $entry );

			Logger::log( $url );

			if ( wp_redirect( $url ) ) {
				exit;
			} else {
				wp_die( esc_html__( 'Redirection cannot be processed.', 'ubc-dpp' ) );
			}
		}

	}//end do_confirmaton()

	/**
	 * Render the confirmation message on the page where the form is embeded.
	 * If the default confirmation type is 'message'
	 *
	 * @since 0.1.0
	 *
	 * @param  HTML $content Post content.
	 * @return HTML Confirmation template if requirements meet, original post content otherwise.
	 */
	public static function manage_message_type_redirect_template_content( $content ) {

		if ( ! isset( $_GET['form_id'] ) || ! isset( $_GET['entry_id'] ) || ! isset( $_GET['payment_request_number'] ) || ! isset( $_GET['upay_status'] ) || ! isset( $_GET['_nonce'] ) ) {
			return $content;
		}

		// phpcs:ignore
		if ( ! wp_verify_nonce( $_GET['_nonce'], 'ubc-upay-confirmation-redirect' ) ) {
			return $content;
		}

		// ************************* Payment form check ************************* //
		$form  = \GFAPI::get_form( (int) $_GET['form_id'] );
		$entry = \GFAPI::get_entry( (int) $_GET['entry_id'] );

		if ( ! Helper::is_payment_form( $form ) ) {
			return;
		}

		$payment_request_number = sanitize_title( wp_unslash( $_GET['payment_request_number'] ) );

		if ( 'success' === $_GET['upay_status'] ) {

			$default_confirmation = reset(
				array_filter(
					$form['confirmations'],
					function( $confirmation ) {
						return array_key_exists( 'isDefault', $confirmation ) && true === boolval($confirmation['isDefault']);
					}
				)
			);

			Logger::log( $payment_request_number );
			Logger::log( $default_confirmation );

			return \GFCommon::replace_variables( $default_confirmation['message'], $form, $entry );
		}

		if ( 'cancelled' === $_GET['upay_status'] ) {
			Logger::log( $payment_request_number );
			return 'Your payment (' . $payment_request_number . ') has been cancelled.';
		}

		if ( 'error' === $_GET['upay_status'] ) {
			Logger::log( $payment_request_number );
			return 'Your payment (' . $payment_request_number . ') is not complete due to system outages or other unexpected technical issues with payment processing. Please contact UBC uPay team for more details.';
		}

		return $content;
	}//end manage_message_type_redirect_template_content()

}//end class
