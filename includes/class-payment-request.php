<?php
/**
 * Class to initiate
 *
 * @since 0.1.0
 * @package ubc-dpp
 */

namespace UBC\CTLT\DPP;

/**
 * Class to initiate uPay requests
 */
class Payment_Request {

	/**
	 * Service protocol.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $protocol;

	/**
	 * Service hostname.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $host_name;

	/**
	 * Service port.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $port;

	/**
	 * Service base url.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $base_url;

	/**
	 * A merchant identifier.
	 * This ID is assigned to the Merchant during onboarding and is unique for this Merchant.
	 *
	 * Pattern: ^([A-Za-z0-9]){4,10}$
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $merchant_id;

	/**
	 * A merchant store identifier.
	 * This ID is assigned to the Merchant's Web Store during onboarding and is unique for this Web Store.
	 *
	 * Pattern: ^([0-9]){2}$
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $merchant_store_id;

	/**
	 * Merchant key. Secreat key provided during onboarding process.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $proxy_key;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param int $form_id The ID of the form that submits the payment request.
	 *
	 * @since 0.1.0
	 */
	public function __construct( $form_id ) {
		$this->protocol = defined( 'DPP_PROTOCOL' ) ? constant( 'DPP_PROTOCOL' ) : 'https';
		$this->port     = defined( 'DPP_PORT' ) ? constant( 'DPP_PORT' ) : '443';
		$this->base_url = defined( 'DPP_BASE_URL' ) ? constant( 'DPP_BASE_URL' ) : '/upay/v1';

		$this->gforms_addon = GForms_Addon::get_instance();
		$this->form         = \GFAPI::get_form( (int) $form_id );

		Logger::log( $form_id );
		Logger::log( Helper::get_form_env( $this->form ) );

		if ( 'test' === Helper::get_form_env( $this->form ) ) {
			$this->host_name         = defined( 'DPP_HOST_NAME_TEST' ) ? constant( 'DPP_HOST_NAME_TEST' ) : 'sat.api.ubc.ca';
			$this->merchant_id       = false !== $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_id_test' ) ? sanitize_text_field( $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_id_test' ) ) : '';
			$this->merchant_store_id = false !== $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_store_id_test' ) ? sanitize_text_field( $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_store_id_test' ) ) : '';
			$this->proxy_key         = false !== $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_proxy_key_test' ) ? sanitize_text_field( $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_proxy_key_test' ) ) : '';
		} else {
			$this->host_name         = defined( 'DPP_HOST_NAME_PROD' ) ? constant( 'DPP_HOST_NAME_PROD' ) : 'api.ubc.ca';
			$this->merchant_id       = false !== $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_id_prod' ) ? sanitize_text_field( $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_id_prod' ) ) : '';
			$this->merchant_store_id = false !== $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_store_id_prod' ) ? sanitize_text_field( $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_store_id_prod' ) ) : '';
			$this->proxy_key         = false !== $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_proxy_key_prod' ) ? sanitize_text_field( $this->gforms_addon->get_plugin_setting( 'ubc_upay_merchant_proxy_key_prod' ) ) : '';
		}
	}//end __construct()

	/**
	 * Initiate a payment request for processing by uPay Payment Processor.
	 *
	 * @since 0.1.0
	 *
	 * @param string|float $payment_amount order total amount.
	 * @param string       $payment_request_number unique request number generated based on site_id, form_id, and entry_id.
	 *
	 * @return array|WP_Error The response or WP_Error on failure.
	 */
	public function request_payment( $payment_amount, $payment_request_number ) {
		$validation = $this->validate_payment_request_data( $payment_amount, $payment_request_number );

		if ( is_wp_error( $validation ) ) {
			Logger::log( $validation );
			wp_die( $validation ); // phpcs:ignore
		}

		if ( defined( 'DPP_DEV' ) ) {
			$request_url = trailingslashit( get_site_url() ) . $this->base_url . '/payment-request';
		} else {
			$request_url = $this->protocol . '://' . $this->host_name . ( $this->port ? ':' . $this->port : '' ) . $this->base_url . '/payment-request';
		}

		Logger::log( $response );

		$response = wp_remote_post(
			$request_url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'timeout' => 30,
				'body'    => array_merge(
					array(
						'merchantId'           => $this->merchant_id,
						'merchantStoreId'      => $this->merchant_store_id,
						'paymentRequestAmount' => number_format( (float) filter_var( $payment_amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ), 2, '.', '' ),
						'paymentRequestNumber' => $payment_request_number,
						'proxyHash'            => $this->generate_proxy_hash( $payment_amount, $payment_request_number ),
					),
					$this->generate_workday_account_override_args( number_format( (float) filter_var( $payment_amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ), 2, '.', '' ) )
				),
			)
		);

		Logger::log( $response );

		return $response;
	}//end request_payment()

	/**
	 * Generate Workday Override args.
	 *
	 * @param  string $payment_amount payment amount.
	 * @return array
	 */
	private function generate_workday_account_override_args( $payment_amount ) {
		$settings = $this->gforms_addon->get_form_settings( $this->form );
		$args     = array();

		if ( empty( $settings['ubc_upay_is_workday_override'] ) ) {
			return $args;
		}

		// Check if the settings exists at all.
		if ( ! array_key_exists( 'ubc_upay_ledger_id', $settings ) || count( $settings['ubc_upay_ledger_id'] ) < 1 ) {
			return $args;
		}

		$index_max_limit = 1;
		$index_max       = max( $index_max_limit, count( $settings['ubc_upay_ledger_id'] ) );

		for ( $i = 0; $i < $index_max; $i++ ) {
			$ledger_id           = (float) $settings['ubc_upay_ledger_id'][ $i ];
			$revenue_category_id = esc_attr( $settings['ubc_upay_revenue_category_id'][ $i ] );
			$fund_id             = esc_attr( $settings['ubc_upay_fund_id'][ $i ] );
			$function_id         = esc_attr( $settings['ubc_upay_function_id'][ $i ] );
			$cost_centre_id      = esc_attr( $settings['ubc_upay_cost_centre_id'][ $i ] );
			$program_id          = esc_attr( $settings['ubc_upay_program_id'][ $i ] );
			$project_id          = esc_attr( $settings['ubc_upay_project_id'][ $i ] );

			// Check for all-or-nothing accounting override parameter group.
			if ( ! empty( $program_id ) && ! empty( $project_id ) ) {
				// phpcs:ignore
				wp_die( new \WP_Error( 'Validaton Failed.', 'Pragram ID and Project ID for Workday Override group [' . ($i+1) . '] are manually exclusive and cannot be provided at the same time.' ) );
			}

			if ( $ledger_id < 4000 || $ledger_id > 4999 ) {
				// phpcs:ignore
				wp_die( new \WP_Error( 'Validaton Failed.', 'Ledger ID [' . ($i+1) . '] provided is not valid.' ) );
			}

			if ( false == preg_match( '/^RC([0-9]){4}$/', $revenue_category_id ) ) {
				// phpcs:ignore
				wp_die( new \WP_Error( 'Validaton Failed.', 'Revenue Category ID [' . ($i+1) . '] provided is not valid.' ) );
			}

			if ( false == preg_match( '/^FD([0-9]){3}$/', $fund_id ) ) {
				// phpcs:ignore
				wp_die( new \WP_Error( 'Validaton Failed.', 'Fund ID [' . ($i+1) . '] provided is not valid.' ) );
			}

			if ( false == preg_match( '/^FN([0-9]){3}$/', $function_id ) ) {
				// phpcs:ignore
				wp_die( new \WP_Error( 'Validaton Failed.', 'Function ID [' . ($i+1) . '] provided is not valid.' ) );
			}

			if ( false == preg_match( '/^CC([0-9]){5}$/', $cost_centre_id ) ) {
				// phpcs:ignore
				wp_die( new \WP_Error( 'Validaton Failed.', 'Cost Centre ID [' . ($i+1) . '] provided is not valid.' ) );
			}

			if ( ! empty( $program_id ) && false == preg_match( '/^PM([0-9]){6}$/', $program_id ) ) {
				// phpcs:ignore
				wp_die( new \WP_Error( 'Validaton Failed.', 'Program ID [' . ($i+1) . '] provided is not valid.' ) );
			}

			if ( ! empty( $project_id ) && false == preg_match( '/^PJ([0-9]){6}$/', $project_id ) ) {
				// phpcs:ignore
				wp_die( new \WP_Error( 'Validaton Failed.', 'Project ID [' . ($i+1) . '] provided is not valid.' ) );
			}

			$index_string = str_pad( $i + 1, 2, '0', STR_PAD_LEFT );

			$args = array_merge(
				$args,
				array(
					'acct' . $index_string . 'LedgerId' => $ledger_id,
					'acct' . $index_string . 'RevenueCategoryId' => $revenue_category_id,
					'acct' . $index_string . 'FundId' => $fund_id,
					'acct' . $index_string . 'FunctionId' => $function_id,
					'acct' . $index_string . 'CostCenterId' => $cost_centre_id,
					// Payment amount current set to the full amount since the index_max_limit is 1. Will need to change accordingly once multiple workday account override is allowed.
					'acct' . $index_string . 'PaymentAmount' => $payment_amount,
				),
			);

			if ( ! empty( $program_id ) ) {
				$args[ 'acct' . $index_string . 'ProgramId' ] = $program_id;
			}

			if ( ! empty( $project_id ) ) {
				$args[ 'acct' . $index_string . 'ProjectId' ] = $project_id;
			}
		}

		return $args;
	}

	/**
	 * An MD5 hash value generated using the request data and the Merchant key. This value is required if the paymentRequestAmount is specified in the request.
	 * [YOUR_PROXY_KEY][paymentRequestNumber][paymentRequestAmount]
	 *
	 * For example, given the following values:

	 * Proxy key: 665916ba-1505-41bc-97f4-895ef7
	 * paymentRequestNumber: F-2291099
	 * paymentRequestAmount: 142.90
	 * the resulting string is: 665916ba-1505-41bc-97f4-895ef7F-2291099142.90
	 *
	 * Create an MD5 hash and Base64 encode the binary MD5 hash value.
	 *
	 * @since 0.1.0
	 *
	 * @param string|float $payment_amount order total amount.
	 * @param string       $payment_request_number unique request number generated based on site_id, form_id, and entry_id.
	 *
	 * @return string generate payment request number.
	 */
	private function generate_proxy_hash( $payment_amount, $payment_request_number ) {
		return base64_encode( md5( $this->proxy_key . $payment_request_number . number_format( (float) filter_var( $payment_amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ), 2, '.', '' ), true ) );
	}//end generate_proxy_hash()

	/**
	 * Validate data before process payment request.
	 *
	 * @since 0.1.0
	 *
	 * @param string|float $payment_amount order total amount.
	 * @param string       $payment_request_number unique request number generated based on site_id, form_id, and entry_id.
	 *
	 * @return WP_ERROR|true true on success, WP_ERROR on failure.
	 */
	private function validate_payment_request_data( $payment_amount, $payment_request_number ) {

		if ( ! preg_match( '/^([A-Za-z0-9]){4,10}$/', $this->merchant_id ) ) {
			Logger::log( $this->merchant_id );
			return new \WP_Error( 'Validaton Failed.', 'Merchant ID is not valid.' );
		}

		if ( ! preg_match( '/^([0-9]){2}$/', $this->merchant_store_id ) ) {
			Logger::log( $this->merchant_store_id );
			return new \WP_Error( 'Validaton Failed.', 'Merchant Store ID is not valid.' );
		}

		if ( ! preg_match( '/^[a-f0-9]{30}$/', $this->proxy_key ) ) {
			Logger::log( $this->proxy_key );
			return new \WP_Error( 'Validaton Failed.', 'Proxy Key is not valid.' );
		}

		if ( 0.01 > (float) $payment_amount ) {
			Logger::log( $payment_amount );
			return new \WP_Error( 'Validaton Failed', 'Payment Amount is less than 0.01.' );
		}

		if ( (float) $payment_amount > 99999.99 ) {
			Logger::log( $payment_amount );
			return new \WP_Error( 'Validaton Failed', 'Payment Amount is greater than 99999.99.' );
		}

		if ( 1 > strlen( $payment_request_number ) || strlen( $payment_request_number ) > 250 ) {
			Logger::log( $payment_request_number );
			return new \WP_Error( 'Validaton Failed', 'Payment Request Number length incorrect, currently has length of ' . strlen( $payment_request_number ) . '.' );
		}

		Logger::log( 'Bottom of validate_payment_request_data()' );

		return true;

	}//end validate_payment_request_data()
}
