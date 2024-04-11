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
		add_action( 'gform_pre_submission', array( __CLASS__, 'disabled_cbm' ), 9, 4 );
		add_action( 'gform_after_submission', array( __CLASS__, 'after_submission' ), 10, 2 );
		add_filter( 'template_redirect', array( __CLASS__, 'render_api_response_html' ), 10, 1 );
		add_filter( 'query_vars', array( __CLASS__, 'add_rewrite_query_vars' ) );
		add_filter( 'gform_disable_notification', array( __CLASS__, 'disabled_notification_on_form_submit' ), 10, 5 );
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
			// Save payment_request_number as entry meta.
			gform_update_meta( $entry['id'], 'payment_request_number', 'N/A' );
			// Set payment status as Pending.
			\GFAPI::update_entry_property( $entry['id'], 'payment_status', 'N/A' );
			// Set payment amount.
			\GFAPI::update_entry_property( $entry['id'], 'payment_amount', '0' );

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
	 * Disabled_notification_on_form_submit.
	 *
	 * @param  bool  $is_diabled Variable to be filtered. Set it to true to disable admin notifications.
	 * @param  array $notification Current Notification array.
	 * @param  Form  $form Current form.
	 * @param  Entry $entry Current Entry array.
	 * @param  array $data Array of data which can be used in the notifications via the generic {object:property} merge tag. Defaults to empty array. Since: 2.3.6.6.
	 * @return bool
	 */
	public static function disabled_notification_on_form_submit( $is_diabled, $notification, $form, $entry, $data ) {
		return Helper::is_payment_form( $form );
	}//end disabled_notification_on_form_submit()

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
