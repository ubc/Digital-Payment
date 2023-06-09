<?php
/**
 * Helper class
 *
 * @since 0.1.0
 * @package ubc-dpp
 */

namespace UBC\CTLT\DPP;

/**
 * Helper class implementation.
 */
class Helper {
	/**
	 * Is the client onboarded with uPay.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public static function is_upay_onboarded() {
		$gforms_addon = GForms_Addon::get_instance();

		return ! empty( $gforms_addon->get_plugin_setting( 'ubc_upay_onboarding_complete' ) );
	}//end is_upay_onboarded()
	/**
	 * Is the form an ePayment form.
	 *
	 * @since 0.1.0
	 *
	 * @param Object $form form object.
	 * @return bool
	 */
	public static function is_payment_form( $form ) {
		if ( is_int( $form ) ) {
			$form = \GFAPI::get_form( $form );
		}
		$gforms_addon = GForms_Addon::get_instance();
		$settings     = $gforms_addon->get_form_settings( $form );

		return ! empty( $settings['ubc_upay_is_epayment_form'] );
	}//end is_payment_form()

	/**
	 * Get payment form environment.
	 *
	 * @since 0.1.0
	 *
	 * @param Object $form form object.
	 * @return string
	 */
	public static function get_form_env( $form ) {
		if ( class_exists( 'UBC_CBM' ) ) {
			return 'test';
		}

		$gforms_addon = GForms_Addon::get_instance();
		$settings     = $gforms_addon->get_form_settings( $form );

		return 'prod' === $settings['ubc_upay_form_environment'] ? 'prod' : 'test';
	}//end get_form_env()
}
