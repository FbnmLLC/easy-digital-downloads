<?php
/**
 * Plugin Name: 123PAY.IR - Easy Digital Downloads
 * Description: پلاگین پرداخت ، سامانه پرداخت یک دو سه پی برای Easy Digital Downloads
 * Plugin URI: https://123pay.ir
 * Author: تیم فنی یک دو سه پی
 * Author URI: https://123pay.ir
 * Version: 1.0
 **/
@session_start();
function edd_rial( $formatted, $currency, $price ) {
	return $price . 'ریال';
}

add_filter( 'edd_rial_currency_filter_after', 'edd_rial', 10, 3 );

add_filter( 'edd_rial_currency_filter_before', 'edd_rial', 10, 3 );

function add_gateway( $gateways ) {
	$gateways['_123pay'] = array(
		'admin_label'    => 'سامانه پرداخت یک دو سه پی',
		'checkout_label' => 'پرداخت با یک دو سه پی'
	);

	return $gateways;
}

add_filter( 'edd_payment_gateways', 'add_gateway' );

function cc_form() {
	do_action( 'cc_form_action' );
}

add_filter( 'edd__123pay_cc_form', 'cc_form' );

function process_payment( $purchase_data ) {
	global $edd_options;
	$payment_data = array(
		'price'        => $purchase_data['price'],
		'date'         => $purchase_data['date'],
		'user_email'   => $purchase_data['post_data']['edd_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency'     => $edd_options['currency'],
		'downloads'    => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info'    => $purchase_data['user_info'],
		'status'       => 'pending'
	);
	$payment      = edd_insert_payment( $payment_data );
	if ( $payment ) {
		$_SESSION['_123pay_payment'] = $payment;
		$_SESSION['_123pay_fi']      = $payment_data['price'];

		$merchant_id  = $edd_options['_123pay_merchant_id'];
		$amount       = $payment_data['price'];
		$callback_url = urlencode( add_query_arg( 'order', '_123pay', get_permalink( $edd_options['success_page'] ) ) );

		@session_start();

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/create/payment' );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&amount=$amount&callback_url=$callback_url" );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$response = curl_exec( $ch );
		curl_close( $ch );
		$result = json_decode( $response );
		if ( $result->status ) {
			$_SESSION['RefNum'] = $result->RefNum;
			wp_redirect( $result->payment_url );
		} else {
			edd_update_payment_status( $payment, 'failed' );
			edd_insert_payment_note( $payment, $result->message ); ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf8"/>
                <title>---ERROR---</title>
            </head>
            <body>
			<?php echo $result->message; ?>
            </body>
            </html>
			<?php
			exit();
		}
	} else {
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	}
}

add_action( 'edd_gateway__123pay', 'process_payment' );

function verify() {
	global $edd_options;

	$State  = $_REQUEST['State'];
	$RefNum = $_REQUEST['RefNum'];

	if ( $State == 'OK' ) {
		$payment     = $_SESSION['_123pay_payment'];
		$merchant_id = $edd_options['_123pay_merchant_id'];
		$ch          = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/verify/payment' );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&RefNum=$RefNum" );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$response = curl_exec( $ch );
		curl_close( $ch );
		$result = json_decode( $response );

		if ( $result->status && $_SESSION['RefNum'] == $RefNum ) {
			edd_update_payment_status( $payment, 'publish' );
		} else {
			edd_update_payment_status( $payment, 'failed' );
			edd_insert_payment_note( $payment, $result->message );
			wp_redirect( get_permalink( $edd_options['failure_page'] ) );
			exit();
		}
	}
}

add_action( 'init', 'verify' );

function add_settings( $settings ) {
	$_123pay_settings = array(
		array(
			'id'   => '_123pay_settings',
			'name' => '<strong>پیکربندی سامانه پرداخت یک دو سه پی</strong>',
			'desc' => 'پیکربندی یک دو سه پی با تنظیمات فروشگاه',
			'type' => 'header'
		),
		array(
			'id'   => '_123pay_merchant_id',
			'name' => 'کد پذیرنده ',
			'desc' => '',
			'type' => 'text',
			'size' => 'regular'
		)
	);

	return array_merge( $settings, $_123pay_settings );
}

add_filter( 'edd_settings_gateways', 'add_settings' );
?>