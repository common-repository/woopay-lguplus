<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayLGUPlusPayment' ) ) {
	class WooPayLGUPlusPayment extends WooPayLGUPlus {
		public $title_default;
		public $desc_default;
		public $default_checkout_img;
		public $allowed_currency;
		public $allow_other_currency;
		public $allow_testmode;

		function __construct() {
			parent::__construct();

			$this->method_init();
			$this->init_settings();
			$this->init_form_fields();

			$this->get_woopay_settings();

			// Actions
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'pg_scripts' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_virtual_information' ) );
			add_action( 'woocommerce_view_order', array( $this, 'get_virtual_information' ), 9 );

			if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
				if ( ! $this->allow_other_currency ) {
					$this->enabled = 'no';
				}
			}

			if ( ! $this->testmode ) {
				if ( $this->mertid == '' || $this->mertkey == '' ) {
					$this->enabled = 'no';
				}
			} else {
				$this->title		= __( '[Test Mode]', $this->woopay_domain ) . " " . $this->title;
				$this->description	= __( '[Test Mode]', $this->woopay_domain ) . " " . $this->description;
			}
		}

		public function method_init() {
		}

		public function pg_scripts() {
			if ( is_checkout() ) {
				if ( $this->testmode ) {
					if ( $this->site_ssl() ) {
						$script_url = 'https://xpay.uplus.co.kr:7443/xpay/js/xpay_crossplatform.js';
					} else {
						$script_url = 'http://xpay.uplus.co.kr:7080/xpay/js/xpay_crossplatform.js';
					}
				} else {
					if ( $this->site_ssl() ) {
						$script_url = 'https://xpay.uplus.co.kr/xpay/js/xpay_crossplatform.js';
					} else {
						$script_url = 'http://xpay.uplus.co.kr/xpay/js/xpay_crossplatform.js';
					}
				}

				wp_register_script( 'lguplus_script', $script_url, array( 'jquery' ), null, false );
				wp_enqueue_script( 'lguplus_script' );
			}
		}

		public function receipt( $orderid ) {
			$order = new WC_Order( $orderid );

			if ( $this->checkout_img ) {
				echo '<div class="p8-checkout-img"><img src="' . $this->checkout_img . '"></div>';
			}

			echo '<div class="p8-checkout-txt">' . str_replace( "\n", '<br>', $this->checkout_txt ) . '</div>';

			if ( $this->show_chrome_msg == 'yes' ) {
				if ( $this->get_chrome_version() >= 42 && $this->get_chrome_version() < 45 ) {
					echo '<div class="p8-chrome-msg">';
					echo __( 'If you continue seeing the message to install the plugin, please enable NPAPI settings by following these steps:', $this->woopay_domain );
					echo '<br>';
					echo __( '1. Enter <u>chrome://flags/#enable-npapi</u> on the address bar.', $this->woopay_domain );
					echo '<br>';
					echo __( '2. Enable NPAPI.', $this->woopay_domain );
					echo '<br>';
					echo __( '3. Restart Chrome and refresh this page.', $this->woopay_domain );
					echo '</div>';
				}
			}

			$currency_check = $this->currency_check( $order, $this->allowed_currency );

			if ( $currency_check ) {
				echo $this->woopay_form( $orderid );
			} else {
				$currency_str = $this->get_currency_str( $this->allowed_currency );

				echo sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', $this->woopay_domain ), get_post_meta( $order->id, '_order_currency', true ), $currency_str );
			}
		}

		function get_woopay_args( $order ) {
			$orderid = $order->id;

			$this->billing_phone = $order->billing_phone;

			if ( sizeof( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					if ( $item[ 'qty' ] ) {
						$item_name = $item[ 'name' ];
					}
				}
			}

			if ( ! $this->check_mobile() ) {
				$woopay_args =
					array(
						'CST_PLATFORM'					=> ( $this->testmode ) ? 'test' : 'service',
						'CST_MID'						=> ( $this->testmode ) ? 'tlgdacomxpay' : $this->mertid,
						'CST_WINDOW_TYPE'				=> $this->window_type,
						'LGD_MID'						=> ( $this->testmode ) ? 'tlgdacomxpay' : $this->mertid,
						'LGD_OID'						=> $order->id,
						'LGD_BUYER'						=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'LGD_PRODUCTINFO'				=> sanitize_text_field( $item_name ),
						'LGD_AMOUNT'					=> $order->order_total,
						'LGD_BUYEREMAIL'				=> $order->billing_email,
						'LGD_CUSTOM_SKIN'				=> $this->form_style,
						'LGD_CUSTOM_LOGO'				=> $this->site_logo,
						'LGD_WINDOW_VER'				=> '2.5',
						'LGD_CUSTOM_PROCESSTYPE'		=> 'TWOTR',
						'LGD_TIMESTAMP'					=> get_post_meta( $orderid, '_' . $this->woopay_api_name . '_timestamp', true ),
						'LGD_HASHDATA'					=> get_post_meta( $orderid, '_' . $this->woopay_api_name . '_hashdata', true ),
						'LGD_PAYKEY'					=> '',
						'LGD_VERSION'					=> 'PHP_XPay_2.5',
						'LGD_BUYERIP'					=> $this->get_client_ip(),
						'LGD_BUYERID'					=> '',
						'LGD_CASNOTEURL'				=> $this->get_api_url( 'cas_response' ),
						'LGD_RETURNURL'					=> $this->get_api_url( 'return' ),
						'LGD_CUSTOM_USABLEPAY'			=> $this->method,
						'LGD_PAYTYPE'					=> $this->method,
						'LGD_CUSTOM_SWITCHINGTYPE'		=> '',
						'LGD_RESPCODE'					=> '',
						'LGD_RESPMSG'					=> '',
						'LGD_CLOSEDATE'					=> ( $this->method == 'SC0040' ) ? $this->get_expirytime( $this->expiry_time, 'Ymd' ) : '',
						'LGD_ESCROW_USEYN'				=> ( $this->escw_yn ) ? 'Y' : 'N',
						'LGD_ESCROW_ZIPCODE'			=> $order->billing_postcode,
						'LGD_ESCROW_ADDRESS1'			=> $order->billing_address_1,
						'LGD_ESCROW_ADDRESS2'			=> $order->billing_address_2,
						'LGD_ESCROW_BUYERPHONE'			=> $order->billing_phone,
						'LGD_GOODINFO'					=> $this->get_good_info( $order ),
						'LGD_ENCODING'					=> 'UTF-8',
					);
			} else {
				$woopay_args =
					array(
						'CST_PLATFORM'					=> ( $this->testmode ) ? 'test' : 'service',
						'CST_WINDOW_TYPE'				=> 'submit', //$this->window_type,
						'LGD_CUSTOM_USABLEPAY'			=> $this->method,
						'LGD_PAYTYPE'					=> $this->method,
						'CST_MID'						=> ( $this->testmode ) ? 'tlgdacomxpay' : $this->mertid,
						'LGD_MID'						=> ( $this->testmode ) ? 'tlgdacomxpay' : $this->mertid,
						'LGD_OID'						=> $order->id,
						'LGD_PRODUCTINFO'				=> sanitize_text_field( $item_name ),
						'LGD_AMOUNT'					=> $order->order_total,
						'LGD_BUYER'						=> $this->get_name_lang( $order->billing_first_name, $order->billing_last_name ),
						'LGD_BUYEREMAIL'				=> $order->billing_email,
						'LGD_BUYERPHONE'				=> $order->billing_phone,
						'LGD_BUYERADDRESS'				=> $order->billing_address_1.$order->billing_address_2,
						'LGD_CUSTOM_SKIN'				=> $this->form_style,
						'LGD_CUSTOM_LOGO'				=> $this->site_logo,
						'LGD_CUSTOM_PROCESSTYPE'		=> 'TWOTR',
						'LGD_TIMESTAMP'					=> get_post_meta( $orderid, '_' . $this->woopay_api_name . '_timestamp', true ),
						'LGD_HASHDATA'					=> get_post_meta( $orderid, '_' . $this->woopay_api_name . '_hashdata', true ),
						'LGD_RETURNURL'					=> $this->get_api_url( 'response' ),
						'LGD_VERSION'					=> 'PHP_SmartXPay_1.0',
						'LGD_CUSTOM_ROLLBACK'			=> 'N',
						'LGD_KVPMISPAUTOAPPYN'			=> ( $this->method == 'SC0010' ) ? ( ( $this->check_ios() ) ? 'N' : 'A' ) : '',
						'LGD_KVPMISPWAPURL'				=> '',
						'LGD_KVPMISPCANCELURL'			=> '',
						'LGD_MTRANSFERAUTOAPPYN'		=> ( $this->method == 'SC0030' ) ? ( ( $this->check_ios() ) ? 'N' : 'A' ) : '',
						'LGD_MTRANSFERWAPURL'			=> '',
						'LGD_MTRANSFERCANCELURL'		=> '',
						'LGD_CASNOTEURL'				=> $this->get_api_url( 'cas_response' ),
						'LGD_ENCODING'					=> 'UTF-8',
						'LGD_RESPCODE'					=> '',
						'LGD_RESPMSG'					=> '',
						'LGD_PAYKEY'					=> '',
						'LGD_CLOSEDATE'					=> ( $this->method == 'SC0040' ) ? $this->get_expirytime( $this->expiry_time, 'Ymd' ) : '',
						'LGD_ESCROW_USEYN'				=> ( $this->escw_yn ) ? 'Y' : 'N',
						'LGD_ESCROW_ZIPCODE'			=> $order->billing_postcode,
						'LGD_ESCROW_ADDRESS1'			=> $order->billing_address_1,
						'LGD_ESCROW_ADDRESS2'			=> $order->billing_address_2,
						'LGD_ESCROW_BUYERPHONE'			=> $order->billing_phone,
						'LGD_GOODINFO'					=> $this->get_good_info( $order ),
						'LGD_ENCODING'					=> 'UTF-8',
					);
			}

			if ( $this->site_logo == '' ) {
				unset( $woopay_args[ 'LGD_CUSTOM_LOGO' ] );
			}

			$woopay_args = apply_filters( 'woocommerce_woopay_args', $woopay_args );

			return $woopay_args;
		}

		function get_good_info( $order ) {
			$seq = 1;
			$good_info = '';
			foreach( $order->get_items() as $item ) {
				if ( $seq > 1 ) {
					$good_info .= chr(30) . 'LGD_ESCROW_GOODID' . chr(31) . $order->id . '_' . substr( '0000' . $seq, -4 );
					$good_info .= chr(30) . 'LGD_ESCROW_GOODNAME' . chr(31) . $item[ 'name' ];
					$good_info .= chr(30) . 'LGD_ESCROW_GOODCODE' . chr(31) . $item[ 'product_id' ];
					$good_info .= chr(30) . 'LGD_ESCROW_UNITPRICE' . chr(31) . ( $item[ 'line_total' ] / $item[ 'qty' ] );
					$good_info .= chr(30) . 'LGD_ESCROW_QUANTITY' . chr(31) . $item[ 'qty' ];
				} else {
					$good_info .= 'LGD_ESCROW_GOODID' . chr(31) . $order->id . '_' . substr( '0000' . $seq, -4 );
					$good_info .= chr(30) . 'LGD_ESCROW_GOODNAME' . chr(31) . $item[ 'name' ];
					$good_info .= chr(30) . 'LGD_ESCROW_GOODCODE' . chr(31) . $item[ 'product_id' ];
					$good_info .= chr(30) . 'LGD_ESCROW_UNITPRICE' . chr(31) . ( $item[ 'line_total' ] / $item[ 'qty' ] );
					$good_info .= chr(30) . 'LGD_ESCROW_QUANTITY' . chr(31) . $item[ 'qty' ];
				}
				$seq++;
			}

			return $good_info;
		}

		function woopay_form( $orderid ) {
			$order = new WC_Order( $orderid );

			$woopay_args = $this->get_woopay_args( $order );

			$woopay_args_array = array();

			foreach ( $woopay_args as $key => $value ) {
				$woopay_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" id="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			$woopay_form = "<form method='post' id='LGD_PAYINFO' name='LGD_PAYINFO'>" . implode( '', $woopay_args_array ) . " </form>";

			if ( ! $this->check_mobile() ) {
				$woopay_script_url = $this->woopay_plugin_url . 'assets/js/woopay.js';
			} else {
				$woopay_script_url = $this->woopay_plugin_url . 'assets/js/woopay-mobile.js';
			}

			wp_register_script( $this->woopay_api_name . 'woopay_script', $woopay_script_url, array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog' ), null, true );
			wp_enqueue_style( 'wp-jquery-ui-dialog' );

			$translation_array = array(
				'testmode'			=> $this->testmode,
				'checkoutURL'		=> WC()->cart->get_checkout_url(),
				'responseURL'		=> $this->get_api_url( 'response' ),
				'is_mobile'			=> $this->check_mobile(),
				'lguplus_payment'	=> __( 'LG U+ Payment', $this->woopay_domain ),
				'testmode_msg'		=> __( 'Test mode is enabled. Continue?', $this->woopay_domain ),
				'cancel_msg'		=> __( 'You have cancelled your transaction. Returning to cart.', $this->woopay_domain ),
			);

			wp_localize_script( $this->woopay_api_name . 'woopay_script', 'woopay_string', $translation_array );
			wp_enqueue_script( $this->woopay_api_name . 'woopay_script' );

			if ( $this->window_type == 'iframe' ) {
				wp_register_script( $this->woopay_api_name . '_iframe', $this->woopay_plugin_url . 'assets/js/iframe.js', array( 'jquery' ), null, true );
				wp_localize_script( $this->woopay_api_name . '_iframe', 'woopay_string', $translation_array );
				wp_enqueue_script( $this->woopay_api_name . '_iframe' );
			}

			return $woopay_form;
		}

		public function process_payment( $orderid ) {
			$order = new WC_Order( $orderid );

			$this->woopay_start_payment( $orderid );

			if ( $this->testmode ) {
				wc_add_notice( __( '<strong>Test mode is enabled!</strong> Please disable test mode if you aren\'t testing anything.', $this->woopay_domain ), 'error' );
				$this->make_conf_file( 'lgdacomxpay', '95160cce09854ef44d2edb2bfb05f9f3' );
			} else {
				$this->make_conf_file( $this->mertid, $this->mertkey );
			}

			$configPath		= $this->woopay_plugin_basedir . '/bin/lib';
			$CST_PLATFORM	= ( $this->testmode ) ? 'test' : 'service';
			$LGD_MID		= ( $this->testmode ) ? 'tlgdacomxpay' : $this->mertid;
			$LGD_KEY		= ( $this->testmode ) ? '95160cce09854ef44d2edb2bfb05f9f3' : $this->mertkey;
			$LGD_OID		= $orderid;
			$LGD_AMOUNT		= $order->order_total;
			$LGD_TIMESTAMP	= $this->get_timestamp( 'YmdHis' );

			$check_hash = array(
				'LGD_MID'				=> $LGD_MID,
				'LGD_OID'				=> $LGD_OID,
				'LGD_AMOUNT'			=> $LGD_AMOUNT,
				'LGD_TIMESTAMP'			=> $LGD_TIMESTAMP,
				'LGD_KEY'				=> $LGD_KEY,
			);

			require_once $this->woopay_plugin_basedir . '/bin/lib/XPayClient.php';

			$xpay = new XPayClient( $configPath, $CST_PLATFORM );
			$xpay->Init_TX( $LGD_MID );
			$LGD_HASHDATA = md5( $check_hash[ 'LGD_MID' ] . $check_hash[ 'LGD_OID' ] . $check_hash[ 'LGD_AMOUNT' ] . $check_hash[ 'LGD_TIMESTAMP' ] . $check_hash[ 'LGD_KEY' ] );
			$LGD_CUSTOM_PROCESSTYPE = 'TWOTR';

			update_post_meta( $order->id, '_' . $this->woopay_api_name . '_hashdata', $LGD_HASHDATA );
			update_post_meta( $order->id, '_' . $this->woopay_api_name . '_timestamp', $LGD_TIMESTAMP );

			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);
		}

		public function process_refund( $orderid, $amount = null, $reason = '' ) {
			$woopay_refund = new WooPayLGUPlusRefund();
			$return = $woopay_refund->do_refund( $orderid, $amount, $reason );

			if ( $return[ 'result' ] == 'success' ) {
				return true;
			} else {
				return false;
			}
		}

		public function admin_options() {
			$currency_str = $this->get_currency_str( $this->allowed_currency );

			echo '<h3>' . $this->method_title . '</h3>';

			$this->get_woopay_settings();
			$hide_form = "";

			if ( ! $this->woopay_check_api() ) {
				echo '<div class="inline error"><p><strong>' . sprintf( __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please check your permalink settings. You must use a permalink structure other than \'General\'. Click <a href="%s">here</a> to change your permalink settings.', $this->woopay_domain ), $this->get_url( 'admin', 'options-permalink.php' ) ) . '</p></div>';

				$hide_form = "display:none;";
			} else {
				if ( ! $this->testmode ) {
					if ( $this->mertid == '' ) {
						echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please select your Merchant ID.', $this->woopay_domain ). '</p></div>';
					} else if ( $this->mertkey == '' ) {
						echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) . '</strong>: ' . __( 'Please enter your Merchat Key.', $this->woopay_domain ). '</p></div>';
					}
				} else {
					echo '<div class="inline error"><p><strong>' . __( 'Test mode is enabled!', $this->woopay_domain ) . '</strong> ' . __( 'Please disable test mode if you aren\'t testing anything.', $this->woopay_domain ) . '</p></div>';
				}

				if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
					if ( ! $this->allow_other_currency ) {
						echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', $this->woopay_domain ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', $this->woopay_domain ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
					} else {
						echo '<div class="inline notice notice-info"><p><strong>' . __( 'Please Note', $this->woopay_domain ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not recommended by this payment method. This payment method recommeds the following currency: %s.', $this->woopay_domain ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
					}
				}
			}

			echo '<div id="' . $this->woopay_plugin_name . '" style="' . $hide_form . '">';
			echo '<table class="form-table ' . $this->id . '">';
			$this->generate_settings_html();
			echo '</table>';
			echo '</div>';
		}

		public function init_form_fields() {
			// General Settings
			$general_array = array(
				'general_title' => array(
					'title' => __( 'General Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'enabled' => array(
					'title' => __( 'Enable/Disable', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable this method.', $this->woopay_domain ),
					'default' => 'yes'
				),
				'testmode' => array(
					'title' => __( 'Enable/Disable Test Mode', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable test mode.', $this->woopay_domain ),
					'description' => '',
					'default' => 'no'
				),
				'log_enabled' => array(
					'title' => __( 'Enable/Disable Logs', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Enable logging.', $this->woopay_domain ),
					'description' => __( 'Logs will be automatically created when in test mode.', $this->woopay_domain ),
					'default' => 'no'
				),
				'log_control' => array(
					'title' => __( 'View/Delete Log', $this->woopay_domain ),
					'type' => 'log_control',
					'description' => '',
					'desc_tip' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Title', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Title that users will see during checkout.', $this->woopay_domain ),
					'default' => $this->title_default,
				),
				'description' => array(
					'title' => __( 'Description', $this->woopay_domain ),
					'type' => 'textarea',
					'description' => __( 'Description that users will see during checkout.', $this->woopay_domain ),
					'default' => $this->desc_default,
				),
				'mertid' => array(
					'title' => __( 'Merchant ID', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Merchant ID.', $this->woopay_domain ),
					'default' => ''
				),
				'mertkey' => array(
					'title' => __( 'Merchant Key', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Please enter your Merchant Key.', $this->woopay_domain ),
					'default' => ''
				),
				'expiry_time' => array(
					'title' => __( 'Expiry time in days', $this->woopay_domain ),
					'type'=> 'select',
					'class' => 'wc-enhanced-select',
					'description' => __( 'Select the virtual account transfer expiry time in days.', $this->woopay_domain ),
					'options'	=> array(
						'1'			=> __( '1 day', $this->woopay_domain ),
						'2'			=> __( '2 days', $this->woopay_domain ),
						'3'			=> __( '3 days', $this->woopay_domain ),
						'4'			=> __( '4 days', $this->woopay_domain ),
						'5'			=> __( '5 days', $this->woopay_domain ),
						'6'			=> __( '6 days', $this->woopay_domain ),
						'7'			=> __( '7 days', $this->woopay_domain ),
						'8'			=> __( '8 days', $this->woopay_domain ),
						'9'			=> __( '9 days', $this->woopay_domain ),
						'10'		=> __( '10 days', $this->woopay_domain ),
					),
					'default' => ( '5' ),
				),
				'escw_yn' => array(
					'title' => __( 'Escrow Settings', $this->woopay_domain ),
					'type' => 'checkbox',
					'description' => __( 'Force escrow settings.', $this->woopay_domain ),
					'default' => 'no',
				),
			);

			// Refund Settings
			$refund_array = array(
				'refund_title' => array(
					'title' => __( 'Refund Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'refund_btn_txt' => array(
					'title' => __( 'Refund Button Text', $this->woopay_domain ),
					'type' => 'text',
					'description' => __( 'Text for refund button that users will see.', $this->woopay_domain ),
					'default' => __( 'Refund', $this->woopay_domain ),
				),
				'customer_refund' => array (
					'title' => __( 'Refundable Satus for Customer', $this->woopay_domain ),
					'type' => 'multiselect',
					'class' => 'chosen_select',
					'description' => __( 'Select the order status for allowing refund.', $this->woopay_domain ),
					'options' => $this->get_status_array(),
				)
			);

			// Design Settings
			$design_array = array(
				'design_title' => array(
					'title' => __( 'Design Settings', $this->woopay_domain ),
					'type' => 'title',
				),
				'window_type' => array(
					'title' => __( 'Form Type', $this->woopay_domain ),
					'type' => 'select',
					'class' => 'wc-enhanced-select',
					'description' => __( 'Select the form type for your LG U+ form.', $this->woopay_domain ),
					'options' => array(
						'iframe' => __( 'IFrame', $this->woopay_domain ),
						'popup' => __( 'Popup', $this->woopay_domain ),
						'submit' => __( 'Submit', $this->woopay_domain ),
					),
					'default' => 'iframe'
				),
				'form_style' => array(
					'title' => __( 'Skin Type', $this->woopay_domain ),
					'type' => 'select',
					'class' => 'wc-enhanced-select',
					'description' => __( 'Select the skin type for your LG U+ form.', $this->woopay_domain ),
					'options' => array(
						'red' => __( 'Red', $this->woopay_domain ),
						'purple' => __( 'Purple', $this->woopay_domain ),
						'yellow' => __( 'Yellow', $this->woopay_domain ),
					)
				),
				'site_logo' => array(
					'title' => __( 'Logo Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your logo. The size should be 100*21. You can use GIF/JPG.', $this->woopay_domain ),
					'default' => '',
					'btn_name' => __( 'Select/Upload Logo', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Logo', $this->woopay_domain ),
					'default_btn_url' => ''
				),
				'checkout_img' => array(
					'title' => __( 'Checkout Processing Image', $this->woopay_domain ),
					'type' => 'img_upload',
					'description' => __( 'Please select or upload your image for the checkout processing page. Leave blank to show no image.', $this->woopay_domain ),
					'default' => $this->woopay_plugin_url . 'assets/images/' . $this->default_checkout_img . '.png',
					'btn_name' => __( 'Select/Upload Image', $this->woopay_domain ),
					'remove_btn_name' => __( 'Remove Image', $this->woopay_domain ),
					'default_btn_name' => __( 'Use Default', $this->woopay_domain ),
					'default_btn_url' => $this->woopay_plugin_url . 'assets/images/' . $this->default_checkout_img . '.png',
				),	
				'checkout_txt' => array(
					'title' => __( 'Checkout Processing Text', $this->woopay_domain ),
					'type' => 'textarea',
					'description' => __( 'Text that users will see on the checkout processing page. You can use some HTML tags as well.', $this->woopay_domain ),
					'default' => __( "<strong>Please wait while your payment is being processed.</strong>\nIf you see this page for a long time, please try to refresh the page.", $this->woopay_domain )
				),
				'show_chrome_msg' => array(
					'title' => __( 'Chrome Message', $this->woopay_domain ),
					'type' => 'checkbox',
					'label' => __( 'Show steps to enable NPAPI for Chrome users.', $this->woopay_domain ),
					'description' => '',
					'default' => 'yes'
				)
			);

			if ( $this->id == 'lguplus_pro_virtual' ) {
				$general_array = array_merge( $general_array,
					array(
						'send_mail' => array(
							'title' => __( 'Add Virtual Bank Information', $this->woopay_domain ),
							'type' => 'checkbox',
							'description' => __( 'Add virtual bank information to customer e-mail notification.', $this->woopay_domain ),
							'default' => 'yes',
						),
						'callback_url' => array(
							'title' => __( 'Callback URL', $this->woopay_domain ),
							'type' => 'txt_info',
							'txt' => $this->get_api_url( 'cas_response' ),
							'description' => __( 'Callback URL used for payment notice from LG U+.', $this->woopay_domain )
						)
					)
				);
			}

			if ( ! $this->allow_testmode ) {
				$general_array[ 'testmode' ] = array(
					'title' => __( 'Enable/Disable Test Mode', $this->woopay_domain ),
					'type' => 'txt_info',
					'txt' => __( 'You cannot test this payment method.', $this->woopay_domain ),
					'description' => '',
				);
			}

			if ( $this->id == 'lguplus_pro_card' || $this->id == 'lguplus_pro_mobile' ) {
				unset( $general_array[ 'escw_yn' ] );
			}

			if ( $this->id != 'lguplus_pro_virtual' ) {
				unset( $general_array[ 'expiry_time' ] );
			}

			if ( ! in_array( 'refunds', $this->supports ) ) {
				unset( $refund_array[ 'refund_btn_txt' ] );
				unset( $refund_array[ 'customer_refund' ] );

				$refund_array[ 'refund_title' ][ 'description' ] = __( 'This payment method does not support refunds. You can refund each transaction using the merchant page.', $this->woopay_domain );
			}

			$form_array = array_merge( $general_array, $refund_array );
			$form_array = array_merge( $form_array, $design_array );

			$this->form_fields = $form_array;

			$lguplus_mid_bad_msg = __( 'This Merchant ID is not from Planet8. Please visit the following page for more information: <a href="http://www.planet8.co/woopay-lguplus-change-mid/" target="_blank">http://www.planet8.co/woopay-lguplus-change-mid/</a>', $this->woopay_domain );

			if ( is_admin() ) {
				if ( $this->id != '' ) {
					wc_enqueue_js( "

					" );
				}
			}
		}
	}

	return new WooPayLGUPlusPayment();
}