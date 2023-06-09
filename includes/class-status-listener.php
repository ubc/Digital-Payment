<?php
/**
 * A gravity form add-on which integrates with UBC ePayments's new uPay Proxy system
 *
 * @package ubc-dpp
 * @since 0.1.0
 */

namespace UBC\CTLT\DPP;

	/**
	 * Status_listener
	 *
	 * @since 0.1.0
	 */
class Status_Listener {

	/**
	 * Status listener init.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function init() {
		// For now, this is just a logging tool.
		add_action( 'rest_api_init', array( __CLASS__, 'ubc_epayment_upay_register_rest_route' ) );
	}//end init()

	/**
	 * Register the rest route for our domain mappings.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function ubc_epayment_upay_register_rest_route() {

		// Available at: /wp-json/ubc/v1/epayments-upay/.
		register_rest_route(
			'ubc/v1',
			'/epayments-upay/',
			array(
				'methods'  => array( 'POST' ),
				'callback' => array( __CLASS__, 'ubc_epayment_handle_request' ),
			)
		);

	}//end ubc_epayment_upay_register_rest_route()

	/**
	 * Handle and Process the request sent to this rest route.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request The passed in request.
	 * @return WP_REST_Response array of domains.
	 */
	public static function ubc_epayment_handle_request( $request ) {

		$data = $request->get_params();
		Logger::log( $data );

		if ( ! array_key_exists( 'paymentRequestNumber', $data ) ) {
			Payment_Logs::log( 'Status Update Listener', array( 'data' => $data ), 'Parameter paymentRequestNumber is missing.' );
			return new \WP_Error( '400', esc_html__( 'Parameter paymentRequestNumber is missing.', 'ubc-dpp' ), array( 'status' => 400 ) );
		}

		if ( ! array_key_exists( 'merchantUpdateSecret', $data ) ) {
			Payment_Logs::log( 'Status Update Listener', array( 'data' => $data ), 'Parameter merchantUpdateSecret is missing.' );
			return new \WP_Error( '400', esc_html__( 'Parameter merchantUpdateSecret is missing.', 'ubc-dpp' ), array( 'status' => 400 ) );
		}

		$payment_request_number = sanitize_title( $data['paymentRequestNumber'] );

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
			Payment_Logs::log( 'Status Update Listener', array( 'entires' => $entries ), 'Unable to locate the entry based on payment_request_number.' );
			return new \WP_Error( '500', esc_html__( 'Unable to locate the entry based on payment_request_number.', 'ubc-dpp' ), array( 'status' => 500 ) );
		}

		$entry   = $entries[0];
		$form_id = (int) $entry['form_id'];

		Logger::log( $form_id );

		$form = \GFAPI::get_form( $form_id );

		// ************************* Payment form check ************************* //
		if ( ! Helper::is_payment_form( $form ) ) {
			Logger::log( $form );
			Payment_Logs::log( 'Status Update Listener', array( 'form' => $form ), 'The form is not a payment form.' );
			return new \WP_Error( '500', esc_html__( 'The form is not a payment form.', 'ubc-dpp' ), array( 'status' => 500 ) );
		}

		// Check Merchant Update secret.
		$gforms_addon           = GForms_Addon::get_instance();
		$form_env               = Helper::get_form_env( $form );
		$merchant_update_secret = 'test' === $form_env ?
			( false !== $gforms_addon->get_plugin_setting( 'ubc_upay_merchant_update_secret_test' ) ? sanitize_text_field( $gforms_addon->get_plugin_setting( 'ubc_upay_merchant_update_secret_test' ) ) : '' ) :
			( false !== $gforms_addon->get_plugin_setting( 'ubc_upay_merchant_update_secret_prod' ) ? sanitize_text_field( $gforms_addon->get_plugin_setting( 'ubc_upay_merchant_update_secret_prod' ) ) : '' );

		if ( $data['merchantUpdateSecret'] !== $merchant_update_secret ) {
			Payment_Logs::log( 'Status Update Listener', array( 'data' => $data ), 'Merchant Update Secret does not match.' );
			return new \WP_Error( '401', esc_html__( 'Merchant Update Secret does not match.', 'ubc-dpp' ), array( 'status' => 401 ) );
		}

		if ( ! array_key_exists( 'paymentStatus', $data ) ) {
			Payment_Logs::log( 'Status Update Listener', array( 'data' => $data ), 'Parameter paymentStatus is missing.' );
			return new \WP_Error( '400', esc_html__( 'Parameter paymentStatus is missing.', 'ubc-dpp' ), array( 'status' => 400 ) );
		}

		/**
		 * Payment Success.
		 */
		if ( 'success' === $data['paymentStatus'] ) {
			\GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Paid' );

			if ( array_key_exists( 'paymentDate', $data ) ) {
				// ************************* Question for uPay team about timezone ************************* //
				$date = date_create( $data['paymentDate'] );
				\GFAPI::update_entry_property( $entry['id'], 'payment_date', date_format( $date, 'Y-m-d' ) );
			}

			if ( array_key_exists( 'paymentAmount', $data ) ) {
				// removes comma within amount string.
				\GFAPI::update_entry_property( $entry['id'], 'payment_amount', number_format( (float) filter_var( $data['paymentAmount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ), 2, '.', '' ) );
			}

			if ( array_key_exists( 'paymentType', $data ) ) {
				gform_update_meta( $entry['id'], 'payment_type', $data['paymentType'] );
			}

			if ( array_key_exists( 'paymentCardType', $data ) ) {
				gform_update_meta( $entry['id'], 'payment_card_type', $data['paymentCardType'] );
			}

			if ( array_key_exists( 'uPayTrackingId', $data ) ) {
				gform_update_meta( $entry['id'], 'upay_tracking_id', $data['uPayTrackingId'] );
			}

			if ( array_key_exists( 'paymentGatewayReferenceNumber', $data ) ) {
				gform_update_meta( $entry['id'], 'payment_gateway_reference_number', $data['paymentGatewayReferenceNumber'] );
			}
			Logger::log( 'Bottom of Success' );
			return new \WP_REST_Response( array( 'payment_request_number' => $payment_request_number ), 200 );
		}

		/**
		 * Payment Cancelled.
		 */
		if ( 'cancelled' === $data['paymentStatus'] ) {
			\GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Cancelled' );
			Logger::log( 'Bottom of Cancelled' );
			return new \WP_REST_Response( array( 'payment_request_number' => $payment_request_number ), 200 );
		}

		Logger::log( 'Unexpected payment status value' );
		Payment_Logs::log( 'Status Update Listener', array( 'data' => $data ), 'Unexpected payment status value' );

		return new \WP_Error( '400', esc_html__( 'Unexpected payment status value.', 'ubc-dpp' ), array( 'status' => 400 ) );

	}//end ubc_epayment_handle_request()
}
