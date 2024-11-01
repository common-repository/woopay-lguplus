<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayLGUPlusActions' ) ) {
	class WooPayLGUPlusActions extends WooPayLGUPlus {
		function api_action( $type ) {
			@ob_clean();
			header( 'HTTP/1.1 200 OK' );
			switch ( $type ) {
				case 'check_api' :
					$this->do_check_api( $_REQUEST );
					exit;
					break;
				case 'return' :
					header( 'Content-Type: text/html; charset=utf-8' );
					$this->do_return( $_REQUEST );
					exit;
					break;
				case 'response' :
					$this->do_response( $_REQUEST );
					exit;
					break;
				case 'cas_response' :
					$this->do_cas_response( $_REQUEST );
					exit;
					break;
				case 'notification' :
					$this->do_notification( $_REQUEST );
					exit;
					break;
				case 'wap' :
					$this->do_wap( $_REQUEST );
					exit;
					break;
				case 'refund_request' :
					$this->do_refund_request( $_REQUEST );
					exit;
					break;
				case 'escrow_request' :
					$this->do_escrow_request( $_REQUEST );
					exit;
					break;
				case 'delete_log' :
					$this->do_delete_log( $_REQUEST );
					exit;
					break;
				default :
					exit;
			}
		}

		private function do_check_api( $params ) {
			$result = array(
				'result'	=> 'success',
			);

			echo json_encode( $result );
		}


		private function do_return( $params ) {
			if ( empty( $params[ 'LGD_RESPCODE' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$LGD_RESPCODE	= isset( $params[ 'LGD_RESPCODE' ] ) ? $params[ 'LGD_RESPCODE' ] : '';
			$LGD_RESPMSG	= isset( $params[ 'LGD_RESPMSG' ] ) ? $params[ 'LGD_RESPMSG' ] : '';
			$LGD_PAYKEY		= isset( $params[ 'LGD_PAYKEY' ] ) ? $params[ 'LGD_PAYKEY' ] : '';
			?>
			<html>
				<head>
					<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
					<script type="text/javascript">
					function setLGDResult() {
						var RESP = document.getElementById( 'LGD_RESPCODE' ).value;
						var MSG = document.getElementById( 'LGD_RESPMSG' ).value;
						var LGD_PAYKEY = document.getElementById('LGD_PAYKEY' ).value;

						try {
							opener.payment_return( RESP, MSG, LGD_PAYKEY );
						} catch (e) {
							try {
								parent.payment_return( RESP, MSG, LGD_PAYKEY );
							} catch (e) {
							}
						}
						window.close();
					}
					</script>
				</head>
				<body onload='setLGDResult();'>
				<form method='post' name='LGD_RETURNINFO' id='LGD_RETURNINFO'>
					<input type='hidden' id='LGD_RESPCODE' name='LGD_RESPCODE' value='<?php echo $LGD_RESPCODE; ?>' />
					<input type='hidden' id='LGD_RESPMSG' name='LGD_RESPMSG' value='<?php echo $LGD_RESPMSG; ?>' />
					<input type='hidden' id='LGD_PAYKEY' name='LGD_PAYKEY' value='<?php echo $LGD_PAYKEY; ?>' />
				</form>
				</body>
			</html>
			<?php
		}

		private function do_response( $params ) {
			if ( isset( $params[ 'LGD_RESPCODE' ] ) && ( $params[ 'LGD_RESPCODE' ] == 'S053' ) ) {
				if ( isset( $params[ 'LGD_OID' ] ) ) {
					$orderid = $params[ 'LGD_OID' ];
					$this->woopay_user_cancelled( $orderid );
				}
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}

			if ( empty( $params[ 'LGD_OID' ] ) || empty( $params[ 'LGD_RESPCODE' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'LGD_OID' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Response received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting response process.', $this->woopay_domain ), $orderid );

			$configPath		= $this->woopay_plugin_basedir . '/bin/lib';
			$CST_PLATFORM	= ( $this->testmode ) ? 'test' : 'service';
			$CST_MID		= ( $this->testmode ) ? 'tlgdacomxpay' : $this->mertid;
			$LGD_MID		= ( $this->testmode ) ? 'tlgdacomxpay' : $this->mertid;
			$LGD_PAYKEY		= $params[ 'LGD_PAYKEY' ];

			$LGD_PAYTYPE	= $params[ 'LGD_PAYTYPE' ];

			require_once $this->woopay_plugin_basedir . '/bin/lib/XPayClient.php';

			$xpay = new XPayClient( $configPath, $CST_PLATFORM );
			$xpay->Init_TX( $LGD_MID );

			$xpay->Set( 'LGD_TXNAME', 'PaymentByKey' );
			$xpay->Set( 'LGD_PAYKEY', $LGD_PAYKEY );

			if ( $xpay->TX() ) {
				$LGD_TID		= $xpay->Response( 'LGD_TID', 0 );
				$LGD_RESPCODE	= $xpay->Response( 'LGD_RESPCODE', 0 );
				$LGD_RESPMSG	= $xpay->Response( 'LGD_RESPMSG', 0 );

				$amount			= isset( $params[ 'LGD_AMOUNT' ] ) ? $params[ 'LGD_AMOUNT' ] : 0;

				$this->log( __( 'Result Code: ', $this->woopay_domain ) . $LGD_RESPCODE, $orderid );
				$this->log( __( 'Result Message: ', $this->woopay_domain ) . $LGD_RESPMSG, $orderid );

				$paySuccess = false;

				if ( $LGD_RESPCODE == '0000' ) $paySuccess = true;

				if ( $amount != $order->get_total() ) {
					$paySuccess = false;

					$xpay->Rollback( sprintf( __( 'Integrity check failed. TID: %s, MID: %s, OID: %s.', $this->woopay_domain ), $LGD_TID, $LGD_MID, $orderid ) );

					$this->woopay_payment_integrity_failed( $orderid );
					wp_redirect( WC()->cart->get_cart_url() );
					exit;
				}

				if ( $paySuccess == true ) {
					if ( $LGD_PAYTYPE == 'SC0040' ) {
						$bankname	= $xpay->Response( 'LGD_FINANCENAME', 0 );
						$account	= $xpay->Response( 'LGD_ACCOUNTNUM', 0 );
						$va_date	= $this->get_expirytime( $this->expiry_time, 'YmdHis' );

						$this->woopay_payment_awaiting( $orderid, $LGD_TID, $LGD_PAYTYPE, $bankname, $account, $va_date );
					} else {
						$this->woopay_payment_complete( $orderid, $LGD_TID, $LGD_PAYTYPE );
					}

					WC()->cart->empty_cart();
					wp_redirect( $this->get_return_url( $order ) );
					exit;
				} else {
					$this->woopay_payment_failed( $orderid );
					wp_redirect( WC()->cart->get_cart_url() );
					exit;
				}
			} else {
				$this->woopay_payment_failed( $orderid );
				wp_redirect( WC()->cart->get_cart_url() );
				exit;
			}
		}

		private function do_cas_response( $params ) {
			if ( empty( $params[ 'LGD_RESPCODE' ] ) ) {
				echo 'FAIL';
				exit;
			}

			$orderid		= $params[ 'LGD_OID' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'CAS response received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );
				echo 'FAIL';
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting CAS response process.', $this->woopay_domain ), $orderid );

			$LGD_RESPCODE			= $params[ 'LGD_RESPCODE' ];
			$LGD_RESPMSG			= $params[ 'LGD_RESPMSG' ];
			$LGD_MID				= $params[ 'LGD_MID' ];
			$LGD_OID				= $params[ 'LGD_OID' ];
			$LGD_AMOUNT				= $params[ 'LGD_AMOUNT' ];
			$LGD_TID				= $params[ 'LGD_TID' ];
			$LGD_PAYTYPE			= $params[ 'LGD_PAYTYPE' ];
			$LGD_PAYDATE			= $params[ 'LGD_PAYDATE' ];
			$LGD_HASHDATA			= $params[ 'LGD_HASHDATA' ];
			$LGD_CASFLAG			= $params[ 'LGD_CASFLAG' ];
			$LGD_TIMESTAMP			= $params[ 'LGD_TIMESTAMP' ];
			$LGD_KEY				= ( $this->testmode ) ? '95160cce09854ef44d2edb2bfb05f9f3' : $this->mertkey;

			$check_hash = array(
				'LGD_MID'				=> $LGD_MID,
				'LGD_OID'				=> $LGD_OID,
				'LGD_AMOUNT'			=> $LGD_AMOUNT,
				'LGD_RESPCODE'			=> $LGD_RESPCODE,
				'LGD_TIMESTAMP'			=> $LGD_TIMESTAMP,
				'LGD_KEY'				=> $LGD_KEY,
			);

			$LGD_HASHDATA2 = md5( $check_hash[ 'LGD_MID' ] . $check_hash[ 'LGD_OID' ] . $check_hash[ 'LGD_AMOUNT' ] . $check_hash[ 'LGD_RESPCODE' ] . $check_hash[ 'LGD_TIMESTAMP' ] . $check_hash[ 'LGD_KEY' ] );

			if ( $LGD_HASHDATA != $LGD_HASHDATA2 ) {
				$this->woopay_payment_integrity_failed( $orderid );

				echo 'FAIL';
				exit;
			}

			if ( $LGD_RESPCODE == '0000' ) {
				if ( $LGD_CASFLAG == 'R' ) {
					$message = __( 'Received allocate message.', $this->woopay_domain );
					$this->woopay_add_order_note( $orderid, $message );

					echo 'OK';
					exit;
				} elseif ( $LGD_CASFLAG == 'I' ) {
					$this->woopay_cas_payment_complete( $orderid, $LGD_TID, 'VBANK' );

					echo 'OK';
					exit;
				} elseif ( $LGD_CASFLAG == 'C' ) {
					$message = __( 'Received payment cancel message.', $this->woopay_domain );
					$this->woopay_add_order_note( $orderid, $message );

					echo 'OK';
					exit;
				} else {
					$this->woopay_payment_failed( $orderid, $LGD_RESPCODE, $LGD_RESPMSG, 'CAS' );

					echo 'OK';
					exit;
				}
			} else {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}
		}

		private function do_notification( $params ) {
			if ( empty( $params[ 'LGD_RESPCODE' ] ) ) {
				echo 'FAIL';
				exit;
			}

			$orderid		= $params[ 'LGD_OID' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Notification received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );

				echo 'FAIL';
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting notification process.', $this->woopay_domain ), $orderid );

			$LGD_RESPCODE			= $params[ 'LGD_RESPCODE' ];
			$LGD_RESPMSG			= $params[ 'LGD_RESPMSG' ];
			$LGD_MID				= $params[ 'LGD_MID' ];
			$LGD_OID				= $params[ 'LGD_OID' ];
			$LGD_AMOUNT				= $params[ 'LGD_AMOUNT' ];
			$LGD_TID				= $params[ 'LGD_TID' ];
			$LGD_PAYTYPE			= $params[ 'LGD_PAYTYPE' ];
			$LGD_PAYDATE			= $params[ 'LGD_PAYDATE' ];
			$LGD_HASHDATA			= $params[ 'LGD_HASHDATA' ];
			$LGD_CASFLAG			= $params[ 'LGD_CASFLAG' ];
			$LGD_TIMESTAMP			= $params[ 'LGD_TIMESTAMP' ];
			$LGD_KEY				= ( $this->testmode ) ? '95160cce09854ef44d2edb2bfb05f9f3' : $this->mertkey;

			$check_hash = array(
				'LGD_MID'				=> $LGD_MID,
				'LGD_OID'				=> $LGD_OID,
				'LGD_AMOUNT'			=> $LGD_AMOUNT,
				'LGD_RESPCODE'			=> $LGD_RESPCODE,
				'LGD_TIMESTAMP'			=> $LGD_TIMESTAMP,
				'LGD_KEY'				=> $LGD_KEY,
			);

			$LGD_HASHDATA2 = md5( $check_hash[ 'LGD_MID' ] . $check_hash[ 'LGD_OID' ] . $check_hash[ 'LGD_AMOUNT' ] . $check_hash[ 'LGD_RESPCODE' ] . $check_hash[ 'LGD_TIMESTAMP' ] . $check_hash[ 'LGD_KEY' ] );

			if ( $LGD_HASHDATA != $LGD_HASHDATA2 ) {
				$this->woopay_payment_integrity_failed( $orderid );

				echo 'FAIL';
				exit;
			}

			if ( $LGD_RESPCODE == '0000' ) {
				$this->woopay_payment_complete( $orderid, $LGD_TID, $LGD_PAYTYPE );

				echo 'OK';
				exit;
			} else {
				$this->woopay_payment_failed( $orderid, $LGD_RESPCODE, $LGD_RESPMSG );

				echo 'OK';
				exit;
			}
		}

		private function do_wap( $params ) {
			if ( empty( $params[ 'LGD_RESPCODE' ] ) ) {
				echo 'FAIL';
				exit;
			}

			$orderid		= $params[ 'LGD_OID' ];
			$order			= new WC_Order( $orderid );

			if ( $order == null ) {
				$message = __( 'Notification received, but order does not exist.', $this->woopay_domain );
				$this->log( $message );

				echo 'FAIL';
				exit;
			}

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$this->log( __( 'Starting notification process.', $this->woopay_domain ), $orderid );

			$LGD_RESPCODE			= $params[ 'LGD_RESPCODE' ];
			$LGD_RESPMSG			= $params[ 'LGD_RESPMSG' ];
			$LGD_MID				= $params[ 'LGD_MID' ];
			$LGD_OID				= $params[ 'LGD_OID' ];
			$LGD_AMOUNT				= $params[ 'LGD_AMOUNT' ];
			$LGD_TID				= $params[ 'LGD_TID' ];
			$LGD_PAYTYPE			= $params[ 'LGD_PAYTYPE' ];
			$LGD_PAYDATE			= $params[ 'LGD_PAYDATE' ];
			$LGD_HASHDATA			= $params[ 'LGD_HASHDATA' ];
			$LGD_CASFLAG			= $params[ 'LGD_CASFLAG' ];
			$LGD_TIMESTAMP			= $params[ 'LGD_TIMESTAMP' ];
			$LGD_KEY				= ( $this->testmode ) ? '95160cce09854ef44d2edb2bfb05f9f3' : $this->mertkey;

			$check_hash = array(
				'LGD_MID'				=> $LGD_MID,
				'LGD_OID'				=> $LGD_OID,
				'LGD_AMOUNT'			=> $LGD_AMOUNT,
				'LGD_RESPCODE'			=> $LGD_RESPCODE,
				'LGD_TIMESTAMP'			=> $LGD_TIMESTAMP,
				'LGD_KEY'				=> $LGD_KEY,
			);

			$LGD_HASHDATA2 = md5( $check_hash[ 'LGD_MID' ] . $check_hash[ 'LGD_OID' ] . $check_hash[ 'LGD_AMOUNT' ] . $check_hash[ 'LGD_RESPCODE' ] . $check_hash[ 'LGD_TIMESTAMP' ] . $check_hash[ 'LGD_KEY' ] );

			if ( $LGD_HASHDATA != $LGD_HASHDATA2 ) {
				$this->woopay_payment_integrity_failed( $orderid );

				echo 'FAIL';
				exit;
			}

			if ( $LGD_RESPCODE == '0000' ) {
				$this->woopay_payment_complete( $orderid, $LGD_TID, $LGD_PAYTYPE );

				echo 'OK';
				exit;
			} else {
				$this->woopay_payment_failed( $orderid, $LGD_RESPCODE, $LGD_RESPMSG );

				echo 'OK';
				exit;
			}
		}

		private function do_refund_request( $params ) {
			if ( ! isset( $params[ 'orderid' ] ) || ! isset( $params[ 'tid' ] ) || ! isset( $params[ 'type' ] ) ) {
				wp_die( $this->woopay_plugin_nice_name . ' Failure' );
			}

			$orderid		= $params[ 'orderid' ];
			$tid			= $params[ 'tid' ];

			$woopay_refund = new WooPayLGUPlusRefund();
			$return = $woopay_refund->do_refund( $orderid, null, __( 'Refund request by customer', $this->woopay_domain ), $tid, 'customer' );

			if ( $return[ 'result' ] == 'success' ) {
				wc_add_notice( $return[ 'message' ], 'notice' );
				wp_redirect( $params[ 'redirect' ] );
				exit;
			} else {
				wc_add_notice( $return[ 'message' ], 'error' );
				wp_redirect( $params[ 'redirect' ] );
				exit;
			}
			exit;
		}

		private function do_escrow_request( $params ) {
			exit;
		}

		private function do_delete_log( $params ) {
			if ( ! isset( $params[ 'file' ] ) ) {
				$return = array(
					'result' => 'failure',
				);
			} else {
				$file = trailingslashit( WC_LOG_DIR ) . $params[ 'file' ];

				if ( file_exists( $file ) ) {
					unlink( $file );
				}

				$return = array(
					'result' => 'success',
					'message' => __( 'Log file has been deleted.', $this->woopay_domain )
				);
			}

			echo json_encode( $return );

			exit;
		}
	}
}