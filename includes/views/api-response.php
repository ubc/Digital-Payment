<?php
/**
 * Template to render request payment response HTML.
 *
 * @since 0.1.0
 * @package ubc-dpp
 */

namespace UBC\CTLT\DPP;

require_once UBC_DPP_PLUGIN_DIR . 'includes/class-payment-request.php';

$payment_amount = isset( $_GET['payment_amount'] ) ? (float) $_GET['payment_amount'] : 0;
$form_id        = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0;
// phpcs:ignore
$payment_request_number = isset( $_GET['payment_request_number'] ) ? sanitize_title( $_GET['payment_request_number'] ) : '';

$request  = new Payment_Request( $form_id );
$response = $request->request_payment( $payment_amount, $payment_request_number );

if ( is_wp_error( $response ) ) {
	$output = $response->get_error_message();
} else {
	$output = $response['body'];
}

// phpcs:ignore
echo $output;
