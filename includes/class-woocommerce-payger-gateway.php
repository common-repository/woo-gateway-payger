<?php

/**
 * The file that defines the core gateway class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://www.widgilabs.com
 * @since      1.0.0
 *
 * @package    Payger
 * @subpackage Payger/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Payger
 * @subpackage Payger/includes
 * @author     Ana Aires <ana@widgilabs.com>
 */
class Woocommerce_Payger_Gateway extends WC_Payment_Gateway {

	/*
	 * Payger instance so we can process requests
	*/
	protected $payger = null;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( )
	{
		require_once( 'Payger.php' );

		$this->id           = 'payger_gateway';
		$this->icon         = plugin_dir_url( __FILE__ ) . '../assets/icon-payment.png';
		$this->has_fields   = true;
		$this->method_title = __( 'Payger', 'payger' );
		$this->title        = __( 'Payger', 'payger' );
		$this->description  = __( 'Pay with crypto currency (powered by Payger)', 'payger' );


		$key    = $this->get_option( 'key' );
		$secret = $this->get_option( 'secret' );
		$token  = get_option( 'payger_token', '' );

		Payger::setUsername( $key );
		Payger::setPassword( $secret );
		Payger::setToken( $token );

		$token = Payger::connect();
		//this will save new token if needed.
		if( $token ) {
			update_option( 'payger_token', $token );
		}


		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		//This will save our settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		// Receipt page creates POST to gateway or hosts iFrame
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

	}

	/**
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon() {

		$icon = $this->icon ? '<img src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="' . esc_attr( $this->get_title() ) . '" />' : '';

		$icon .= sprintf( '<a href="%1$s" class="about_paypal" target="_blank">' . esc_attr__( 'What is Payger?', 'payger' ) . '</a>', esc_url( 'http://www.payger.com' ) );


		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Tell Woocommerce we have a new payment gateway
	 *
	 * @param    $methods
	 * @return   array
	 * @since    1.0.0
	 * @author   Ana Aires ( ana@widgilabs.com )
	 */
	public function add_payger_gateway_class( $methods ) {

		$methods[] = 'WC_Payger_Gateway';

		return $methods;

	}

	/**
	 * Makes payger gateway available only if there is currencies selected
	 * on settings page.
	 * @return bool
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public function is_available() {
		$currencies = $this->get_option( 'accepted' );
		if ( ! $currencies || empty( $currencies ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Process this plugin admin options for payger gateway
	 *
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'payger' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable payments through Payger', 'payger' ),
				'default' => 'yes'
			),
			'title'       => array(
				'title'       => __( 'Title', 'payger' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'payger' ),
				'default'     => __( 'Payger', 'payger' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'payger' ),
				'type'        => 'text',
				'default'     => __( 'Pay with crypto currency provided by Payger', 'payger' ),
				'description' => __( 'This controls the description which the user sees during checkout.', 'payger' ),
				'desc_tip'    => true,
			),
			'key'         => array(
				'title'       => __( 'Username', 'payger' ),
				'type'        => 'text',
				'description' => __( 'Key provided by Payger when signing the contract.', 'payger' ),
				'desc_tip'    => true,
			),
			'secret'      => array(
				'title'       => __( 'Password', 'payger' ),
				'type'        => 'password',
				'description' => __( 'Secret provided by Payger when signing the contract.', 'payger' ),
				'desc_tip'    => true,
			),
			'advanced' => array(
				'title'       => __( 'Advanced options', 'payger' ),
				'type'        => 'title',
				'description' => '',
			),
			'accepted' => array(
				'title'       => __( 'Accepted Currencies', 'payger' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Choose which are the currencies you will allow users to pay with. This depends on your shop currency choosen on Woocommerce General Options. If no options are available it means your shop currency can\'t be converted to any crypto currency, please choose a different one you want to use Payger. ', 'payger' ),
				'default'     => 'bitcoin',
				'desc_tip'    => true,
				'options'     => $this->get_accepted_currencies_options(),
			),
			'max_expired' => array(
				'title'       => __( 'Max Expired', 'payger' ),
				'type'        => 'number',
				'description' => __( 'Define the number of times an expired order will ask for payment to the user. Default is set to 5.', 'payger' ),
				'default'     => 5,
				'desc_tip'    => true,
			),
			'payment_type' => array(
				'title'       => __( 'Payment Type', 'payger' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Choose which type of paymnet you would like to make available for buyers ', 'payger' ),
				'default'     => 'sync',
				'desc_tip'    => true,
				'options'     => array( 'async' => 'Asynchronous', 'sync'=> 'Synchronous' ),
			),
		);
	}


	/**
	 * Form to output on checkout
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public function payment_fields() {

		$description = $this->description;

		if ( $description ) {
			echo wpautop( wptexturize( trim( $description ) ) );
		}

		$selling_currency = get_option('woocommerce_currency');
		$currency_options = $this->get_option( 'accepted' );
		$options          = '';

		$possible_currencies = get_option('payger_possible_currencies', true );
		
		if ( $currency_options && ! empty( $currency_options ) ) {
			foreach ( $currency_options as $option ) {

				$currency  = $possible_currencies[ $option ];
				$long_name = ( isset( $currency->longName ) ) ? ucfirst( $currency->longName ) : $currency->name;

				$options .= sprintf( '<option value="%1$s">%2$s</option>',
					$option,
					$long_name );
			}
		}


		$order_id = get_query_var( 'order-pay' ) ? absint( get_query_var( 'order-pay' ) ) : 0;

		if( ! empty( $options ) ) {
			printf(
				'<p class="form-row form-row-wide">
					<label for="<?php echo $this->id; ?>">%1$s
						<abbr class="required" title="required">*</abbr>
					</label>
					<select name="%2$s" id="%2$s_coin">
					<option value="0">%4$s</option>
						%3$s
					</select>
					<input type="hidden" class="order_id" value="%11$s">
					<div id="payger_convertion" class="hide">%5$s <span class="payger_amount"></span> <span class="currency"></span><sup>*</sup> %6$s <span class="payger_rate"></span> <span class="currency"></span> = 1 %7$s</div>
				</p>
				<span class="warning hide">%12$s</span>
				<div class="hide" id="dialog" title="Payger Confirmation">
  					<p>%8$s <span class="update_amount"></span> <span class="currency"></span> %9$s <span class="update_rate"></span> <span class="currency"></span> = 1 %7$s %10$s</p>
				<p class="warning hide">%12$s</p>
				</div>',
				__( 'Choose Currency', 'payger' ), //1
				$this->id, //2
				$options, //3
				__( 'Please choose one...' , 'payger' ), //4
				__('You will pay', 'payger'), //5
				__('at rate', 'payger'), //6
				esc_html( $selling_currency ), //7
				__( 'Your currency rate was recently updated. You will pay a total amount of', 'payger' ), //8
				__( 'corresponding to a rate of', 'payger' ), //9
				__( 'Please confirm you want to proceed with your order.', 'payger' ), //10
				esc_attr( $order_id ), //11
				esc_html( __( '*This is an estimate value. Due to crypto currency volatility this rate may change. Please take this into consideration.', 'payger' ) ) //12
			);
		}

		if( 'sync' === $this->get_option( 'payment_type' ) ) {
			require_once plugin_dir_path( __FILE__ ) . '/../public/partials/pay-modal.php';
		}
	}

	/**
	 * Actual function for payment process
	 * @param int $order_id
	 *
	 * @return array
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public function process_payment( $order_id ) {

		//For SCENARIO 2
		if ( 'sync' === $this->get_option( 'payment_type' ) ) {
			$order = new WC_Order( $order_id );

			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);
		}
		// END FOR SCENARIO 2


		//SCENARIO 1
		$order         = new WC_Order( $order_id );

		$selling_currency = get_option('woocommerce_currency');
		$amount           = $order->get_total();
		$asset            = $_POST['payger_gateway'];

		// Get list of items to buy to have a proper description
		$cart_items = $this->get_cart_items_names( $order );

		//check for currency limits
		$args = array (
			'externalId'        => sprintf( '%03d', $order_id ),
			'description'       => $cart_items,
            'paymentCurrency'	=> $asset,
            'productCurrency'   => $selling_currency,
			'productAmount'	    => $amount,
            'source'            => get_bloginfo( 'name' ),
            'buyerName'	        => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
		    'buyerEmailAddress'	=> $order->get_billing_email(),
			'callback'          => array( 'url' => WC()->api_request_url( 'WC_Gateway_Payger' ), 'method' => 'POST' ),
			'ipAddress'         => $_SERVER['REMOTE_ADDR'],
			'metadata'          => array('meta1'=>'value1'),
		);

		$response = Payger::post( 'merchants/payments/', $args );

		$success = self::handle_payment_response( $response, $order_id, $asset, 'on-hold');

		if ( $success ) {
			// Return thankyou redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}
		return;
	}

	/**
	 * Given a response handles info
	 * Updates order with meta so that emails or other elements can use
	 * @param $response
	 * @param $order_id
	 * @param $currency
	 *
	 * @return bool
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public static function handle_payment_response( $response, $order_id, $currency, $order_status = false ) {

		$success = ( 201 === $response['status'] ) ? true : false; //bad response if status different from 201
		$order   = new WC_Order( $order_id );

		if ( $success ) {

			//Gets the newly generated payment
			$payment_id   = $response['data']->content->id;
			$sub_payments = $response['data']->content->subPayments;
			$payment      = $sub_payments[0];
			$qrcode_image = self::generate_qrcode_image( $order_id, $payment );
			$fee          = $response['data']->content->fee->feeInPaymentCurrency->fee;

			//save meta to possible queries and to show information on thank you page or emails
			$order->add_meta_data( 'payger_currency',     $currency, true );
			$order->add_meta_data( 'payger_amount',       $payment->paymentAmount, true );
			$order->add_meta_data( 'payger_qrcode',       $payment->qrCode, true );
			$order->add_meta_data( 'payger_qrcode_image', $qrcode_image, true ); //stores qrcode url so that email can use this.
			$order->add_meta_data( 'payger_payment_id',   $payment_id, true );
			$order->add_meta_data( 'payger_address',      $payment->address, true );
			$order->add_meta_data( 'payger_expired',      0 ); //controls number of expirations
			$order->add_meta_data( 'payger_fee',          $fee);

			// Mark as on-hold ( we're awaiting for the payment )
			if ( $order_status ) {
				$order->update_status( $order_status, __( 'Awaiting Payger payment', 'payger' ) );
			}
			wc_reduce_stock_levels( $order_id );

			$order->save();

			// Remove cart
			wc_empty_cart();

			//schedule event to check this payment status
			wp_schedule_event( time(), 'minute', 'payger_check_payment', array( 'payment_id' => $payment_id, 'order_id' => $order_id ) );

			$result = array(
				'image'   => $qrcode_image,
				'amount'  => $payment->paymentAmount,
				'code'    => $payment->qrCode,
				'address' => $payment->address,
				'fee'     => $fee
			);

			return $result;

		} else {
			error_log( print_r( $response, true ) );
			$error_message = $response['data']->error->message;
			$error_message = apply_filters( 'payger_payment_error_message', $error_message );

			wc_add_notice( __('Payment error: ', 'payger') . $error_message, 'error' );

			return false;
		}
	}

	/**
	 * Given a particular order returns string with cart items name
	 * separareted by comma
	 * @param $order
	 *
	 * @return string
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public static function get_cart_items_names( $order ){

		$items = $order->get_items();
		$cart_items = array();
		if( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				$cart_items[] = $item->get_name();
			}
		}
		return implode( ',', $cart_items );
	}

	/**
	 * Given order id and payment data generates qrCode image and stores on temporary folder
	 * @param $order_id
	 * @param $payment
	 *
	 * @return string
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */

	public static function generate_qrcode_image( $order_id, $payment ) {

		$qrCode      = $payment->qrCode;
		$data        = base64_decode( $qrCode->content );
		$uploads     = wp_upload_dir();
		$upload_path = $uploads['basedir'];
		$filename    = '/payger_tmp/' . $order_id . '.png';

		// create temporary directory if does not exists
		if ( ! file_exists( $upload_path . '/payger_tmp' ) ) {
			mkdir( $upload_path . '/payger_tmp' );
		}

		//always update file so that if qrcode changes for this
		//payment the code is still valid
		file_put_contents( $upload_path . $filename, $data );

		return $uploads['baseurl'] . $filename;
	}

	/**
	 * Gets current woocommerce currency and checks which are the corresponding currencies this
	 * merchant can offer as payment possible currencies.
	 * exchange-rates should filter results based on from currency
	 *
	 * @return array
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public function get_accepted_currencies_options() {

		$selling_currency = get_option('woocommerce_currency');

		$args = array( 'productCurrency' => $selling_currency );

		$response = Payger::get( 'merchants/currencies', $args );

		$currencies_options  = array();
		$accepted_currencies = array();


		if ( 200 !== $response['status'] ) {
			return $currencies_options;
		}

		if ( $rates = $response['data']->content->currencies ) {

			foreach ( $rates as $currency ) {
				$long_name = ( isset($currency->longName ) ) ? ucfirst( $currency->longName ) : $currency->name;
				$currencies_options[ $currency->name ] = $long_name;
				$accepted_currencies[ $currency->name ] = $currency;
			}
		}

		update_option('payger_possible_currencies', $accepted_currencies );

		return $currencies_options;
	}

	/**
	 * Given a crypto currency get it's exchange rates
	 *
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public function get_quote( $choosen_crypto, $order_key = false, $order_id = false ) {

		$selling_currency = get_option('woocommerce_currency');
		$amount           = $this->get_order_total();


		if ( $order_key && ! $order_id && 0 == $amount ) {
			$order_id =  wc_get_order_id_by_order_key( $order_key );
		}

		if( $order_id && 0 == $amount ){
			$order   = new WC_Order( $order_id );
			$amount  = $order->get_total();
		}

		$args = array(
			'productCurrency'   => $selling_currency,
			'paymentCurrencies' => $choosen_crypto,
			'amount'            => $amount
		);
		$response = Payger::get( 'merchants/exchange-rates', $args );

		$success = ( 200 === $response['status'] ) ? true : false; //bad response if status different from 200

		if ( $success ) {
			$result    = $response['data']->content->rates;
			$result    = $result[0]; //I am interested in a single quote
			$precision = $result->precision;
			$rate      = round( $result->rate, $precision );
			$amount    = round( $result->amount, $precision );

			// will store meta info so that we can use it later
			// to process payment
			WC()->session->set( 'crypto_meta', array(
				'currency'  => $choosen_crypto,
				'rate'      => $rate,
				'amount'    => $amount,
				'precision' => $precision //maybe needed but we are already setting the correct precision
			) );
			return array( 'rate' => $rate, 'amount' => $amount );
		} else {
			$error_message = $response['data']->error->message;
			$error_message = apply_filters( 'payger_get_quote_error_message', $error_message );
			wc_add_notice( __('Payment error: ', 'payger') . $error_message, 'error' );
			return;
		}

	}

	/**
	 * Given order id and cancel payment
	 * @param $order_id
	 *
	 * @since 1.0.0
	 * @author Ana Aires ( ana@widgilabs.com )
	 */
	public function cancel_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		$payment_id = $order->get_meta('payger_payment_id', true);

		Payger::delete( 'merchants/payments/' . $payment_id, array() );
	}


	/**
	 * Output redirect or iFrame form on receipt page
	 * This is only necessary for synchronous payment
	 * process.
	 * This will include modal content on payment page
	 *
	 * @access public
	 *
	 * @param $order_id
	 */
	public function receipt_page( $order_id ) {

		$html = '';
		require_once plugin_dir_path( __FILE__ ) . '/../public/partials/pay-modal.php';

		echo $html;

	}

}