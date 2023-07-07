<?php
/**
 * The file builds integration between Gravity Forms and uPay logic.
 *
 * @package ubc-dpp
 * @since 0.1.0
 */

namespace UBC\CTLT\DPP;

/**
 * UBC uPay Gravity forms integration.
 */
class Gforms_Integration {

	/**
	 * Gforms Integration init.
	 */
	public static function init() {
		add_action( 'gform_validation', array( __CLASS__, 'validate_payment_amount' ), 10, 1 );
		add_action( 'gform_pre_submission', array( __CLASS__, 'disabled_cbm' ), 9, 4 );
		add_action( 'gform_after_submission', array( __CLASS__, 'after_submission' ), 10, 2 );
		add_filter( 'template_redirect', array( __CLASS__, 'render_api_response_html' ), 10, 1 );
		add_filter( 'query_vars', array( __CLASS__, 'add_rewrite_query_vars' ) );
		add_filter( 'gform_pre_process', array( __CLASS__, 'change_confirmations' ), 10, 1 );
		add_filter( 'gform_entry_meta', array( __CLASS__, 'update_entry_meta' ), 10, 2 );

		// Local development only.
		if ( defined( 'DPP_DEV' ) && true === constant( 'DPP_DEV' ) ) {
			add_filter( 'https_ssl_verify', '__return_false' );
		}
	}//end init()

	public static function disabled_cbm( $form ) {
		// ************************* Payment form check ************************* //
		if ( ! Helper::is_payment_form( $form ) ) {
			return;
		}

		global $ubc_cbm_admin;
		remove_action( 'gform_pre_submission_' . $form['id'], array( $ubc_cbm_admin, 'pre_submission'), 10, 4 );
	}

	/**
	 * Validate the payment amount, show erro message if payment amount is 0.
	 *
	 * @since 0.1.0
	 *
	 * @param  array $validation_result array that includes validation result and form object.
	 * @return array
	 */
	public static function validate_payment_amount( $validation_result ) {
		$form  = $validation_result['form'];
		$entry = \GFFormsModel::get_current_lead();

		// ************************* Payment form check ************************* //
		if ( ! Helper::is_payment_form( $form ) ) {
			return $validation_result;
		}

		$payment_amount = \GFCommon::get_order_total( $form, $entry );

		// If the order total is 0, do not proceed to ePayment gateway.
		// phpcs:ignore
		if ( $payment_amount < 0.01 ) {
			$validation_result['is_valid'] = false;

			foreach ( $form['fields'] as &$field ) {
				if ( 'product' === $field['type'] ) {
					$field->failed_validation  = true;
					$field->validation_message = __( 'In order to proceed, the total must be greater 0. Please select at least one item.', 'ubc-dpp' );
				}
			}
			return $validation_result;
		}

		return $validation_result;
	}//end validate_payment_amount()

	/**
	 * Redirect user to the request payment template.
	 *
	 * @since 0.1.0
	 *
	 * @param Entry $entry The entry that was just created.
	 * @param Form  $form The current form.
	 *
	 * @return void
	 */
	public static function after_submission( $entry, $form ) {

		// ************************* Payment form check ************************* //
		if ( ! Helper::is_payment_form( $form ) ) {
			return;
		}

		$payment_amount = \GFCommon::get_order_total( $form, $entry );

		// If the order total is 0, do not proceed to ePayment gateway.
		// phpcs:ignore
		if ( (double) 0 === $payment_amount ) {
			return;
		}

		$prefix                 = defined( 'DPP_PREFIX' ) ? constant( 'DPP_PREFIX' ) : 'ubc';
		$payment_request_number = sanitize_title( is_multisite() ? $prefix . '-' . get_current_blog_id() . '-' . $form['id'] . '-' . $entry['id'] : $prefix . '-' . $form['id'] . '-' . $entry['id'] );

		// Save payment_request_number as entry meta.
		gform_update_meta( $entry['id'], 'payment_request_number', $payment_request_number );
		// Set payment status as Pending.
		\GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Pending' );
		// Set payment amount.
		\GFAPI::update_entry_property( $entry['id'], 'payment_amount', $payment_amount );

		// Redirect to the pre-defined url and pass the payment amount and payment request number.
		if ( wp_safe_redirect( get_site_url() . '/ubc-upay-template/?payment_amount=' . $payment_amount . '&payment_request_number=' . $payment_request_number . '&form_id=' . $form['id'] ) ) {
			exit();
		}
	}//end after_submission()

	/**
	 * Render the template file for payment request.
	 *
	 * @since 0.1.0
	 *
	 * @param string $template current template path for the route.
	 *
	 * @return string modified template path.
	 */
	public static function render_api_response_html( $template ) {
		global $wp_query;

		if ( ! array_key_exists( 'payment_amount', $wp_query->query_vars ) || ! array_key_exists( 'payment_request_number', $wp_query->query_vars ) || ! array_key_exists( 'form_id', $wp_query->query_vars ) ) {
			return $template;
		}

		include_once UBC_DPP_PLUGIN_DIR . 'includes/views/api-response.php';
		exit;
	}//end render_api_response_html()

	/**
	 * Add query vars.
	 *
	 * @since 0.1.0
	 *
	 * @param array $vars router query vars.
	 *
	 * @return array modified query vars.
	 */
	public static function add_rewrite_query_vars( $vars ) {
		$vars[] = 'upay_action';
		$vars[] = 'payment_amount';
		$vars[] = 'payment_request_number';
		$vars[] = 'form_id';

		return $vars;
	}//end add_rewrite_query_vars()

	/**
	 * Disabled the default notification email for payment forms
	 *
	 * @since 0.1.0
	 *
	 * @param  object $form The form object.
	 * @return object
	 */
	public static function change_confirmations( $form ) {

		// ************************* Payment form check ************************* //
		if ( ! Helper::is_payment_form( $form ) ) {
			return $form;
		}

		unset( $form['notifications'] );
		return $form;
	}//end change_confirmations()

	/**
	 * Update the form entry meta list.
	 *
	 * @since 0.1.0
	 *
	 * @param  array $entry_meta entry meta list.
	 * @param  int   $form_id current form id.
	 *
	 * @return array
	 */
	public static function update_entry_meta( $entry_meta, $form_id ) {

		// ************************* Payment form check ************************* //
		if ( ! Helper::is_payment_form( $form_id ) ) {
			return $entry_meta;
		}

		$entry_meta['payment_amount'] = array(
			'is_default_column' => true,
		);

		$entry_meta['payment_status'] = array(
			'is_default_column' => true,
		);

		$entry_meta['upay_environment'] = array(
			'label'                      => 'Environment',
			'is_numeric'                 => false,
			'update_entry_meta_callback' => function ( $key, $entry, $form ) {
				$gforms_addon = GForms_Addon::get_instance();
				$settings     = $gforms_addon->get_form_settings( $form );
				return $settings['ubc_upay_form_environment'];
			},
			'is_default_column'          => true,
		);

		$entry_meta['payment_request_number'] = array(
			'label'                      => 'Payment Request Number',
			'is_numeric'                 => false,
			'update_entry_meta_callback' => function ( $key, $entry, $form ) {
				return gform_get_meta( $entry['id'], 'payment_request_number' );
			},
			'is_default_column'          => false,
		);

		$entry_meta['transaction_id'] = array(
			'label'                      => 'Transaction ID',
			'is_numeric'                 => false,
			'update_entry_meta_callback' => function ( $key, $entry, $form ) {
				return gform_get_meta( $entry['id'], 'transaction_id' );
			},
			'is_default_column'          => true,
		);

		return $entry_meta;
	}//end update_entry_meta()
}
