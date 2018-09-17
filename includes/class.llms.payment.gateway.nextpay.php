<?php
/**
 * Nextpay Payment Gateway for LifterLMS
 * @since    1.0.0
 * @version  1.0.0
 * @author   Nextpay
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LLMS_Payment_Gateway_Nextpay extends LLMS_Payment_Gateway {



	const TOKEN_URL = 'https://api.nextpay.org/gateway/token.wsdl';

	const VERIFY_URL = 'https://api.nextpay.org/gateway/verify.wsdl';

	const REDIRECT_URL = 'https://api.nextpay.org/gateway/payment/';

	const MIN_AMOUNT = 100;

	public $api_key = '';



	/**
	 * Constructor
	 * @since    1.0.0
	 * @version  1.0.0
	 */
	public function __construct() {

		$this->id = 'Nextpay';
		$this->icon = '<a href="https://www.nextpay.ir"  onclick="javascript:window.open(\'https://www.nextpay.ir\',\'WINextpay\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;"><img src="https://nextpay.ir/wp-content/themes/nextpay.ir/images/logo-2.png" border="0" alt="Nextpay Logo"></a>';
		$this->admin_description = __( 'Allow customers to purchase courses and memberships using Nextpay.', 'lifterlms-Nextpay' );
		$this->admin_title = 'Nextpay';
		$this->title = 'Nextpay';
		$this->description = __( 'Pay via Nextpay', 'lifterlms-Nextpay' );

		$this->supports = array(
			'single_payments' => true,
		);


		// add Nextpay specific fields
		add_filter( 'llms_get_gateway_settings_fields', array( $this, 'settings_fields' ), 10, 2 );

		// output Nextpay account details on confirm screen
		add_action( 'lifterlms_checkout_confirm_after_payment_method', array( $this, 'after_payment_method_details' ) );
	}


	public function after_payment_method_details() {

		$key = isset( $_GET['order'] ) ? $_GET['order'] : '';

		$order = llms_get_order_by_key( $key );
		if ( ! $order || 'nextpay' !== $order->get( 'payment_gateway' ) ) {
			return;
		}

		echo '<input name="llms_nextpay_token" type="hidden" value="' . $_POST['trans_id'] . '">';

	}

	/**
	 * Output some information we need on the confirmation screen
	 * @return   void
	 * @since    1.0.0
	 * @version  1.0.0
	 */
	public function confirm_pending_order( $order ) {

		$trans_id = isset( $_POST['llms_nextpay_token'] ) ? $_POST['llms_nextpay_token'] : '';

		if ( ! $order || 'Nextpay' !== $order->get( 'payment_gateway' ) ) {
			return;
		}

		$this->log( 'Nextpay `after_payment_method_callback()` started', $order, $_POST );

		$client = new SoapClient('https://api.nextpay.org/gateway/verify.wsdl', array('encoding' => 'UTF-8'));
		$result = $client->PaymentVerification(
				array(
					'api_key' 	=> self::get_api_key(),
					'amount' 	=> $order->get_price( 'total', array(), 'float' ),
					'order_id' 	=> $order->get( 'order_key' ),
					'trans_id' 	=> $trans_id
				)
		);


		$result = $result->PaymentVerificationResult;

		if ($result->code == 0) {
				$txn_data = array();
				$txn_data['amount'] = $order->get_price( 'total', array(), 'float' );
				$txn_data['transaction_id'] = $trans_id;
				$txn_data['status'] = 'llms-txn-succeeded';
				$txn_data['payment_type'] = 'single';
				$txn_data['source_description'] = isset( $_POST['card_holder'] ) ? $_POST['card_holder'] : '';

				$order->record_transaction( $txn_data );

				$this->log( $order, 'Nextpay `confirm_pending_order()` finished' );


				$order->add_note('Success Transaction : ' . $result->code );

				$this->complete_transaction( $order );

		}else{
			$this->log( $order, 'Nextpay `confirm_pending_order()` finished with error : ' . $result->code );
			$order->add_note('Faild Transaction : ' . $result->code );

			wp_safe_redirect( llms_cancel_payment_url() );
			exit();

		}

	}

	/**
	 * Get api_key option
	 * @return   string
	 * @since    1.0.0
	 * @version  1.0.0
	 */
	public function get_api_key() {
		return $this->get_option( 'api_key' );
	}


	/**
	 * Handle a Pending Order
	 * Called by LLMS_Controller_Orders->create_pending_order() on checkout form submission
	 * All data will be validated before it's passed to this function
	 *
	 * @param   obj       $order   Instance LLMS_Order for the order being processed
	 * @param   obj       $plan    Instance LLMS_Access_Plan for the order being processed
	 * @param   obj       $person  Instance of LLMS_Student for the purchasing customer
	 * @param   obj|false $coupon  Instance of LLMS_Coupon applied to the order being processed, or false when none is being used
	 * @return  void
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function handle_pending_order( $order, $plan, $person, $coupon = false ) {

		$this->log( 'Nextpay `handle_pending_order()` started', $order, $plan, $person, $coupon );

		// do some gateway specific validation before proceeding
		$total = $order->get_price( 'total', array(), 'float' );
		if ( $total < self::MIN_AMOUNT ) {
			return llms_add_notice( sprintf( __( 'Nextpay cannot process transactions for less than %s', 'lifterlms-Nextpay' ), self::MIN_AMOUNT ), 'error' );
		}

		$client = new SoapClient(self::TOKEN_URL, array('encoding' => 'UTF-8'));
    $result = $client->TokenGenerator(
        array(
            'api_key' 	=> self::get_api_key(),
            'amount' 	=> $order->get_price( 'total', array(), 'float' ),
            'order_id' 	=> $order->get( 'order_key' ),
            'callback_uri' 	=> llms_confirm_payment_url( $order->get( 'order_key' ) )
        )
    );
    $result = $result->TokenGeneratorResult;
    //Redirect to Nextpay
    if(intval($result->code) == -1)
    {
				$this->log( $r, 'Nextpay `handle_pending_order()` finished' );
				do_action( 'lifterlms_handle_pending_order_complete', $order );
				$order->add_note('transaction ID : ' . $result->trans_id );
				wp_redirect( self::REDIRECT_URL . $result->trans_id );
				exit();
    }
    else
    {
				$this->log( $r, 'Nextpay `handle_pending_order()` finished with error code : ' );
				return llms_add_notice( 'Cannot connect to bank : ' . $result->code , 'error' );
    }

	}




	/**
	 * Output custom settings fields on the LifterLMS Gateways Screen
	 * @param    array     $fields      array of existing fields
	 * @param    string    $gateway_id  id of the gateway
	 * @return   array
	 * @since    1.0.0
	 * @version  1.0.0
	 */
	public function settings_fields( $fields, $gateway_id ) {

		// don't add fields to other gateways!
		if ( $this->id !== $gateway_id ) {
			return $fields;
		}

		$fields[] = array(
			'type'  => 'custom-html',
			'value' => '
				<h4>' . __( 'Nextpay Settings', 'lifterlms-Nextpay' ) . '</h4>
				<p>' . __( 'Enter your Nextpay API Key to process transactions via Nextpay.', 'lifterlms-Nextpay' ) . '</p>
			',
		);

		$settings = array(
			'api_key' => __( 'API Key', 'lifterlms-Nextpay' ),
		);
		foreach( $settings as $k => $v ) {
			$fields[] = array(
				'id'            => $this->get_option_name( $k ),
				'default'       => $this->{'get_' . $k}(),
				'title'         => $v,
				'type'          => 'text',
			);
		}


		return $fields;

	}

}
