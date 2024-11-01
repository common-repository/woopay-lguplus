<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayLGUPlusRefund' ) ) {
	class WooPayLGUPlusRefund extends WooPayLGUPlus {
		public function __construct() {
			parent::__construct();

			$this->init_refund();
		}

		function init_refund() {
			// For Customer Refund
			add_filter( 'woocommerce_my_account_my_orders_actions',  array( $this, 'add_customer_refund' ), 10, 2 );
		}

		public function do_refund( $orderid, $amount = null, $reason = '', $rcvtid = null, $type = null, $acctname = null, $bankcode= null, $banknum = null ) {
			$order			= wc_get_order( $orderid );

			if ( $order == null ) {
				$message = __( 'Refund request received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting refund process.', $this->woopay_domain ), $orderid );

			if ( $amount == null ) {
				$amount = $order->get_total();
			}

			$tid = get_post_meta( $orderid, '_' . $this->woopay_api_name . '_tid', true );

			if ( $tid == '' ) {
				$message = __( 'No TID found.', $this->woopay_domain );
				$this->log( $message, $orderid );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}

			$configPath		= $this->woopay_plugin_basedir . '/bin/lib';
			$CST_PLATFORM	= ( $this->testmode ) ? 'test' : 'service';
			$CST_MID		= ( $this->testmode ) ? 'tlgdacomxpay' : $this->mertid;
			$LGD_MID		= ( $this->testmode ) ? 'tlgdacomxpay' : $this->mertid;
			$LGD_TID		= $tid;

			require_once $this->woopay_plugin_basedir . '/bin/lib/XPayClient.php';

			$xpay = new XPayClient( $configPath, $CST_PLATFORM );
			$xpay->Init_TX( $LGD_MID );

			$xpay->Set( 'LGD_TXNAME', 'Cancel' );
			$xpay->Set( 'LGD_TID', $LGD_TID );
			
		    if ( $xpay->TX() ) {
				$ResultCode = $xpay->Response_Code();
				$ResultMsg = $xpay->Response_Msg();
			} else {
				$ResultCode = '-1';
				$ResultMsg = __( 'Failed to call cancel API.', $this->woopay_domain );
			}

			if ( $type == 'customer' ) {
				$refunder = __( 'Customer', $this->woopay_domain );
			} else {
				$refunder = __( 'Administrator', $this->woopay_domain );
			}

			if ( in_array( $ResultCode, array( '0000', 'AV11', 'RF00', 'RF10', 'RF09', 'RF15', 'RF19', 'RF23', 'RF25' ) ) ) {
				$message = sprintf( __( 'Refund process complete. Refunded by %s. Reason: %s.', $this->woopay_domain ), $refunder, $reason );

				$this->log( $message, $orderid );

				$message = sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() );

				$order->update_status( 'refunded', $message );

				return array(
					'result' 	=> 'success',
					'message'	=> __( 'Your refund request has been processed.', $this->woopay_domain )
				);
			} else {
				$message = __( 'An error occurred while processing the refund.', $this->woopay_domain );

				$this->log( $message, $orderid );
				$this->log( __( 'Result Code: ', $this->woopay_domain ) . $ResultCode, $orderid );
				$this->log( __( 'Result Message: ', $this->woopay_domain ) . $ResultMsg, $orderid );

				$order->add_order_note( sprintf( __( '%s Code: %s. Message: %s. Timestamp: %s.', $this->woopay_domain ), $message, $ResultCode, $ResultMsg, $this->get_timestamp() ) );

				return array(
					'result' 	=> 'failure',
					'message'	=> $message
				);
			}
		}
	}

	return new WooPayLGUPlusRefund();
}