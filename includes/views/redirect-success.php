<?php
/**
 * Template used when uPay redirects 'successfully'
 *
 * `UPAY_SITE_ID` and `EXT_TRANS_ID` are passed as part of the `$_REQUEST`
 *
 * @since 0.1.0
 * @package ubc-dpp
 */

namespace UBC\CTLT\DPP;

if ( ! isset( $_REQUEST['EXT_TRANS_ID'] ) || ! isset( $_REQUEST['UPAY_SITE_ID'] ) ) {
	wp_die( esc_html__( 'You do not have permission to view this page.', 'ubc-dpp' ) );
}

Redirects::do_confirmaton(
	sanitize_title( wp_unslash( $_REQUEST['EXT_TRANS_ID'] ) ),
	'success'
);
