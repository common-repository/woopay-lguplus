<?php
if ( ! class_exists( 'WooPayCore' ) ) {
	abstract class WooPayCore extends WC_Payment_Gateway {
		public $core_version = '1.1.2';

		// Check SSL
		public function site_ssl() {
			if ( isset( $_SERVER[ 'HTTP_X_FORWARDED_PROTO' ] ) && $_SERVER[ 'HTTP_X_FORWARDED_PROTO' ] == 'https' ) {
				return true;
			} else {
				if ( is_ssl() ) {
					return true;
				} else {
					return false;
				}
			}
		}

		public function get_port( $type = null ) {
			$home_url = get_home_url();

			$url_array = explode( '/', $home_url );

			if ( sizeof( $url_array ) > 2 ) {
				if ( strpos( $url_array[ 2 ], ':' ) > 0 ) {
					$port_array = explode( ':', $url_array[ 2 ] );
					if ( sizeof( $port_array ) > 0 ) {
						$port = $port_array[ 1 ];
					} else {
						$port = '80';
					}
				} else {
					$port = '80';
				}
			}

			if ( $type == 'custom' ) {
				$port = get_option( 'wordpress-https_ssl_port', '443' );
			}

			return $port;
		}

		public function get_url( $type, $url, $http = null ) {
			$ssl = $this->site_ssl();

			if ( $http ) {
				$ssl = false;
			}

			if ( $ssl ) {
				if ( $this->get_port() == '443' ) {
					if ( $type == 'admin' ) {
						$rtn_url = admin_url( $url, 'https' );
					} else {
						$rtn_url = home_url( $url, 'https' );
					}
				} else {
					if ( $type == 'admin' ) {
						$rtn_url = admin_url( $url, 'https' );
					} else {
						$rtn_url = home_url( $url, 'https' );
					}

					$url_array = explode( '/', $rtn_url );

					if ( sizeof( $url_array ) > 2 ) {
						if ( strpos( $url_array[ 2 ], ':' ) > 0 ) {
							$domain = explode( ':', $url_array[ 2 ] );
							$url_array[ 2 ] = $domain[ 0 ];
						}
					}

					$rtn_url = '';
					$i = 0;

					foreach ( $url_array as $key => $value ) {
						if ( $i == 2 ) {
							$rtn_url .= $value . ':' . $this->get_port( 'custom' ) . '/';
						} else {
							if ( $i + 1 == sizeof( $url_array ) ) {
								$rtn_url .= $value;
							} else {
								$rtn_url .= $value . '/';
							}
						}

						$i++;
					}
				}
			} else {
				if ( $this->get_port() == '80' ) {
					if ( $type == 'admin' ) {
						$rtn_url = admin_url( $url, 'http' );
					} else {
						$rtn_url = home_url( $url, 'http' );
					}
				} else {
					if ( $type == 'admin' ) {
						$rtn_url = admin_url( $url, 'http' );
					} else {
						$rtn_url = home_url( $url, 'http' );
					}

					$url_array = explode( '/', $rtn_url );

					if ( sizeof( $url_array ) > 2 ) {
						if ( strpos( $url_array[ 2 ], ':' ) > 0 ) {
							$domain = explode( ':', $url_array[ 2 ] );
							$url_array[ 2 ] = $domain[ 0 ];
						}
					}

					$rtn_url = '';
					$i = 0;

					foreach ( $url_array as $key => $value ) {
						if ( $i == 2 ) {
							$rtn_url .= $value . ':' . $this->get_port() . '/';
						} else {
							if ( $i + 1 == sizeof( $url_array ) ) {
								$rtn_url .= $value;
							} else {
								$rtn_url .= $value . '/';
							}
						}
						$i++;
					}
				}
			}

			return $rtn_url;
		}

		function get_domain_url( $sURL, $scheme = true ) {
			$asParts = parse_url( $sURL );

			if ( ! $asParts ) {
				return $sURL;
			}

			$sScheme = $asParts[ 'scheme' ];
			$nPort   = isset( $asParts[ 'port' ] ) ? $asParts[ 'port' ] : '80';
			$sHost   = $asParts[ 'host' ];
			$nPort   = 80 == $nPort ? '' : $nPort;
			$nPort   = 'https' == $sScheme AND 443 == $nPort ? '' : $nPort;
			$sPort   = ! empty( $sPort ) ? ( ':' . $nPort ) : '';
			$sReturn = $sHost . $sPort;

			if ( $scheme ) {
				return $sScheme . '://' . $sHost . $sPort;
			} else {
				return $sHost . $sPort;
			}
		}

		// Settings Link
		public function generate_settings_link( $links ) {
			$section = $this->woopay_section;
			$settings_url = $this->get_url( 'admin', 'admin.php?page=wc-settings&tab=checkout&section=' . $section );
			$settings_link = '<a href="' . $settings_url . '">' . __( 'Settings', $this->woopay_domain ) . '</a>'; 

			array_unshift( $links, $settings_link ); 
			return $links; 
		}

		// WooPay Logger
		public function log( $message, $orderid = null ) {
			if ( $this->log_enabled || $this->testmode ) {
				if ( empty( $this->log ) ) {
					$this->log = new WooPayLogger();
				}

				if ( $orderid != null ) {
					$message = '[#' . $orderid . '] - ' . $message;
				}

				$random = get_option( $this->woopay_api_name . '_logfile' );

				if ( $random == '' ) {
					$random = $this->get_random_string( 'log' );
					update_option( $this->woopay_api_name . '_logfile', $random );
				}

				$this->log->add( $this->woopay_plugin_name, $message, $random );
			}
		}

		// Custom Field Types
		public function generate_log_control_html( $key, $value ) {
			ob_start();

			$logfile = $this->woopay_plugin_name . '-' . get_option( $this->woopay_api_name . '_logfile' ) . '.log';

			if ( file_exists( trailingslashit( WC_LOG_DIR ) . $logfile ) ) {
				$view_url = $this->get_url( 'admin', 'admin.php?page=wc-status&tab=logs&log_file=' . $logfile, $this->site_ssl() );
				$delete_url = 'javascript:deleteLog(\'' . __( 'Would you like to continue deleting the log files?', $this->woopay_domain ) . '\', \'' . $this->get_url( 'home', '/wc-api/' . $this->woopay_api_name . '_delete_log' ) . '\', \'' . $logfile . '\');';
			} else {
				$view_url = 'javascript:alert(\'' . __( 'There is no log file to view.', $this->woopay_domain ) . '\');';
				$delete_url = 'javascript:alert(\'' . __( 'There is no log file to delete.', $this->woopay_domain ) . '\');';
			}
			?>
			<tr valign="top">
				<th class="titledesc" scope="row">
					<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value[ 'title' ] ); ?></label>
				</th>
				<td class="forminp">
					<a class="button" href="<?php echo $view_url; ?>"><?php echo __( 'View Log', $this->woopay_domain ); ?></a>
					<a class="button" href="<?php echo $delete_url; ?>" id="<?php echo esc_attr( $key ); ?>_delete_log"><?php echo __( 'Delete Log', $this->woopay_domain ); ?></a>
					<br/>
					<?php echo $this->get_description_html( $value ); ?>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		public function generate_txt_info_html( $key, $value ) {
			ob_start();
			?>
			<tr valign="top">
				<th class="titledesc" scope="row">
					<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value[ 'title' ] ); ?></label>
				</th>
				<td class="forminp"><?php echo esc_html( $value[ 'txt' ] ); ?> 
					<p class="description">
					<?php echo esc_html( $value[ 'description' ] ); ?>
					</p>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		public function generate_img_upload_html( $key, $value ) {
			$img_url = $this->$key;
			ob_start();
			?>
			<script language="JavaScript">
			jQuery( document ).ready( function() {
				jQuery('#<?php echo esc_attr( $key ); ?>_button').click(function() {
					formfield = jQuery( '#<?php echo esc_attr( $key ); ?>' ).attr( 'name' );

					tb_show( '<?php echo esc_attr( $value[ "title" ] ); ?>', 'media-upload.php?type=image&TB_iframe=true' );

					window.send_to_editor = function( html ) {
						imgurl = jQuery( 'img', html ).attr( 'src' );
						jQuery( '#<?php echo esc_attr( $key ); ?>' ).val( imgurl );
						jQuery( '#<?php echo esc_attr( $key ); ?>_preview_img' ).attr( 'src', imgurl );
						jQuery( '#<?php echo esc_attr( $key ); ?>_preview_tr' ).show();
						jQuery( '#<?php echo esc_attr( $key ); ?>_remove_button' ).show();
						tb_remove();
					}
					return false;
				});

				jQuery( '#<?php echo esc_attr( $key ); ?>_remove_button' ).click(function() {
					jQuery( '#<?php echo esc_attr( $key ); ?>' ).val( '' );
					jQuery( '#<?php echo esc_attr( $key ); ?>_preview_tr' ).hide();
					jQuery( '#<?php echo esc_attr( $key ); ?>_remove_button' ).hide();
					return false;
				});

				jQuery( '#<?php echo esc_attr( $key ); ?>_default_button' ).click(function() {
					jQuery( '#<?php echo esc_attr( $key ); ?>' ).val( '<?php echo $value[ "default_btn_url" ]; ?>' );
					jQuery( '#<?php echo esc_attr( $key ); ?>_preview_img' ).attr( 'src', '<?php echo $value[ "default_btn_url" ]; ?>' );
					jQuery( '#<?php echo esc_attr( $key ); ?>_preview_tr' ).show();
					jQuery( '#<?php echo esc_attr( $key ); ?>_remove_button' ).show();
					return false;
				});
			});
			</script>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value[ 'title' ] ); ?></label>
				</th>
				<td>
					<input id="<?php echo esc_attr( $key ); ?>" type="text" size="36" name="<?php echo esc_attr( $key ); ?>" value="<?php echo $img_url; ?>" />
					<button id="<?php echo esc_attr( $key ); ?>_button" class="button" /><?php echo esc_attr( $value[ 'btn_name' ] ); ?></button>
					<button id="<?php echo esc_attr( $key ); ?>_remove_button" class="button" <?php echo ( $img_url ) ? '' : 'style="display:none;"'; ?> /><?php echo esc_attr( $value[ 'remove_btn_name' ] ); ?></button>
					<?php
					if ( $key == 'checkout_img' ) {
					?>
					<button id="<?php echo esc_attr( $key ); ?>_default_button" class="button" /><?php echo esc_attr( $value[ 'default_btn_name' ] ); ?></button>
					<?php
					}
					?>
					<p class="description"><?php echo esc_html( $value[ 'description' ] ); ?></p>
				</td>
			</tr>
			<tr valign="top" id="<?php echo esc_attr( $key ); ?>_preview_tr" <?php echo ( $img_url ) ? '' : 'style="display:none;"'; ?>>
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $key ); ?>_preview"><?php echo __( 'Preview', $this->woopay_domain ); ?></label>
				</th>
				<td>
					<img id="<?php echo esc_attr( $key ); ?>_preview_img" src="<?php echo $img_url; ?>">
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		public function validate_img_upload_field( $key ) {
			if ( isset( $_REQUEST[ esc_attr( $key ) ] ) ) {
				return $_REQUEST[ esc_attr( $key ) ];
			} else {
				return false;
			}
		}

		public function generate_keyfile_upload_html( $key, $value ) {
			ob_start();
			$field    = $this->plugin_id . $this->id . '_' . $key;
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $value[ 'title' ] ); ?></label>
				</th>
				<td class="forminp">
					<input id="<?php echo esc_attr( $field ); ?>" type="file" size="36" name="<?php echo esc_attr( $field ); ?>" />
					<?php echo $this->get_description_html( $value ); ?>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		public function validate_keyfile_upload_field( $key ) {
			$field    = $this->plugin_id . $this->id . '_' . $key;

			if ( empty( $_FILES[ $field ] ) && ! isset( $_FILES[ $field ] ) ) {
				return; 
			}

			if ( ! file_exists( $this->woopay_upload_dir . '/tmp' ) ) {
				$old = umask( 0 ); 
				mkdir( $this->woopay_upload_dir . '/tmp', 0777, true );
				umask( $old );
			}

			if ( isset( $_FILES[ $field ][ 'tmp_name' ] ) && ! empty( $_FILES[ $field ][ 'tmp_name' ] ) ) {
				$movefile = move_uploaded_file( $_FILES[ $field ][ 'tmp_name' ], $this->woopay_upload_dir . '/tmp/' . $_FILES[ $field ][ 'name' ] );
				if ( $movefile ) {
					WP_Filesystem();
					$filepath = pathinfo( $this->woopay_upload_dir . '/tmp/' . $_FILES[ $field ][ 'name' ] );
					$unzipfile = unzip_file( $this->woopay_upload_dir . '/tmp/' . $_FILES[ $field ][ 'name' ], $this->woopay_upload_dir . '/key/' . $filepath[ 'filename' ] );

					$this->init_form_fields();

					if ( ! is_wp_error( $unzipfile ) ) {
						if ( ! $unzipfile )  {
							echo '<div id="message" class="error fade"><p><strong>' . __( 'The keyfile you have uploaded is not correct.', $this->woopay_domain ) . '</strong></p></div>';
							$this->deleteDirectory( $this->woopay_upload_dir . '/tmp/' );
							return false;
						}

						$dir = $this->woopay_upload_dir . '/key/' . $filepath[ 'filename' ];
						$tmpfiles = scandir( $dir );

						foreach ( $tmpfiles as $key => $value ) {
							if ( $value == '.' || $value == '..' ) continue;

							if ( ! in_array( $value, array( 'readme.txt', 'keypass.enc', 'mpriv.pem', 'mcert.pem' ) ) ) {
								$this->deleteDirectory( $dir );
								$this->deleteDirectory( $this->woopay_upload_dir . '/tmp/' );
								echo '<div id="message" class="error fade"><p><strong>' . __( 'The keyfile you have uploaded is not correct.', $this->woopay_domain ) . '</strong></p></div>';
								return false;
							}
						}
						echo '<div id="message" class="updated fade"><p><strong>' . __( 'Keyfile upload success.', $this->woopay_domain ) . '</strong></p></div>';
						$this->deleteDirectory( $this->woopay_upload_dir . '/tmp/' );
						return true;
					}
				} else {
					return false;
				}
			}
		}

		public function generate_multiselect_adaptive_html( $key, $data ) {
			$field    = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
				'options'           => array()
			);

			$data  = wp_parse_args( $data, $defaults );
			$value = (array) $this->get_option( $key, array() );

			ob_start();
			?>
			<tr valign="top" class="<?php echo $key;?>">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
					<?php echo $this->get_tooltip_html( $data ); ?>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
						<select multiple="multiple" class="multiselect <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field ); ?>[]" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?>>
							<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
								<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( in_array( $option_key, $value ), true ); ?>><?php echo esc_attr( $option_value ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php echo $this->get_description_html( $data ); ?>
					</fieldset>
				</td>
			</tr>
			<?php

			return ob_get_clean();
		}

		public function validate_multiselect_adaptive_field( $key ) {
			if ( isset( $_POST[ $this->plugin_id . $this->id . '_' . $key ] ) ) {
				$value = array_map( 'wc_clean', array_map( 'stripslashes', (array) $_POST[ $this->plugin_id . $this->id . '_' . $key ] ) );
			} else {
				$value = '';
			}

			return $value;
		}

		public function generate_select_adaptive_html( $key, $data ) {
			$field    = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
				'options'           => array()
			);

			$data = wp_parse_args( $data, $defaults );

			ob_start();
			?>
			<tr valign="top" class="<?php echo $key;?>">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
					<?php echo $this->get_tooltip_html( $data ); ?>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
						<select class="select <?php echo esc_attr( $data['class'] ); ?> <?php echo $key;?>" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?>>
							<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
								<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, esc_attr( $this->get_option( $key ) ) ); ?>><?php echo esc_attr( $option_value ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php echo $this->get_description_html( $data ); ?>
					</fieldset>
				</td>
			</tr>
			<?php

			return ob_get_clean();
		}

		public function validate_select_adaptive_field( $key ) {
			$value = $this->get_option( $key );

			if ( isset( $_POST[ $this->plugin_id . $this->id . '_' . $key ] ) ) {
				$value = wc_clean( stripslashes( $_POST[ $this->plugin_id . $this->id . '_' . $key ] ) );
			}

			return $value;
		}

		// Styles and Scripts
		public function woopay_frontend_scripts() {

		}

		public function woopay_frontend_styles() {
			if ( is_checkout() ) {
				wp_register_style( 'WooPayFrontend' . $this->woopay_plugin_name, $this->woopay_plugin_url . 'assets/css/frontend.css' );
				wp_enqueue_style( 'WooPayFrontend' . $this->woopay_plugin_name );
			}
		}

		public function woopay_admin_scripts() {
			wp_enqueue_script( 'media-upload' );
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_script( 'jquery' );

			wp_enqueue_style( 'wp-color-picker' ); 
			wp_enqueue_script( $this->woopay_api_name . 'color_picker', $this->woopay_plugin_url . 'assets/js/color-picker.js', array( 'wp-color-picker' ), false, true ); 

			wp_register_script( 'WooPayAdmin' . $this->woopay_plugin_name, $this->woopay_plugin_url . 'assets/js/admin.js', array( 'jquery' ), false );
			wp_enqueue_script( 'WooPayAdmin' . $this->woopay_plugin_name );
		}

		public function woopay_admin_styles() {
			wp_enqueue_style( 'thickbox' );
			wp_register_style( 'WooPayAdmin', $this->woopay_plugin_url . 'assets/css/admin.css' );
			wp_enqueue_style( 'WooPayAdmin' );
		}

		public function woopay_refund_scripts() {
			wp_register_script( 'WooPayRefund' . $this->woopay_plugin_name, $this->woopay_plugin_url . 'assets/js/refund.php?woopay_plugin_name=' . $this->woopay_plugin_name, array( 'jquery' ), false );
			wp_enqueue_script( 'WooPayRefund' . $this->woopay_plugin_name );			
		}

		// Custom Order Status
		public function woopay_register_order_status() {
			register_post_status( 'wc-on-delivery', array(
				'label'                     => __( 'On Delivery', $this->woopay_domain ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( __( 'On delivery <span class="count">(%s)</span>', $this->woopay_domain ), __( 'On delivery <span class="count">(%s)</span>', $this->woopay_domain ) )
			) );
			register_post_status( 'wc-decline', array(
				'label'                     => __( 'Decline Delivery', $this->woopay_domain ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( __( 'Decline delivery <span class="count">(%s)</span>', $this->woopay_domain ), __( 'Decline delivery <span class="count">(%s)</span>', $this->woopay_domain ) )
			) );
			register_post_status( 'wc-awaiting', array(
				'label'                     => __( 'Awaiting Payment', $this->woopay_domain ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( __( 'Awaiting payment <span class="count">(%s)</span>', $this->woopay_domain ), __( 'Awaiting payment <span class="count">(%s)</span>', $this->woopay_domain ) )
			) );
		}

		public function woopay_add_order_statuses( $order_statuses ) {
			$new_order_statuses = array();

			foreach ( $order_statuses as $key => $status ) {
				$new_order_statuses[ $key ] = $status;

				if ( 'wc-processing' === $key ) {
					$new_order_statuses[ 'wc-on-delivery' ]			= __( 'On Delivery', $this->woopay_domain );
					$new_order_statuses[ 'wc-decline' ]				= __( 'Decline Delivery', $this->woopay_domain );
					$new_order_statuses[ 'wc-awaiting' ]			= __( 'Awaiting Payment', $this->woopay_domain );
				}
			}
			return $new_order_statuses;
		}

		public function woopay_add_order_statuses_for_payment_complete( $order_statuses, $order ) {
			$order_statuses[] = 'awaiting';

			return $order_statuses;
		}

		// Payment Related
		public function get_currency() {
			if ( isset( WC()->session->client_currency ) ) {
				$currency = WC()->session->client_currency;
			} else {
				$currency = get_woocommerce_currency();
			}

			return $currency;
		}

		public function get_api_url( $api, $type = 'pretty', $args = array() ) {
			if ( ! empty ( $args ) ) {
				if ( $type == 'default' ) {
					$query = array_merge( array( 'wc-api' => $this->woopay_api_name . '_' . $api ), $args );
					return add_query_arg(
						$query,
						$this->get_url( 'home', '/' )
					);
				} else {
					return add_query_arg(
						$args,
						$this->get_url( 'home', '/wc-api/' . $this->woopay_api_name . '_' . $api )
					);
				}
			} else {
				if ( $type == 'default' ) {
					return $this->get_url( 'home', '?wc-api=' . $this->woopay_api_name . '_' . $api );
				} else {
					return $this->get_url( 'home', '/wc-api/' . $this->woopay_api_name . '_' . $api );
				}
			}
		}

		public function get_api_url_http( $api, $type = 'pretty', $args = array() ) {
			if ( ! empty ( $args ) ) {
				if ( $type == 'default' ) {
					$query = array_merge( array( 'wc-api' => $this->woopay_api_name . '_' . $api ), $args );
					return add_query_arg(
						$query,
						$this->get_url( 'home', '/', true )
					);
				} else {
					return add_query_arg(
						$args,
						$this->get_url( 'home', '/wc-api/' . $this->woopay_api_name . '_' . $api, true )
					);
				}
			} else {
				if ( $type == 'default' ) {
					return $this->get_url( 'home', '?wc-api=' . $this->woopay_api_name . '_' . $api, true );
				} else {
					return $this->get_url( 'home', '/wc-api/' . $this->woopay_api_name . '_' . $api, true );
				}
			}
		}

		public function woopay_start_payment( $orderid ) {
			$order		= new WC_Order( $orderid );

			$message	= sprintf( __( 'Starting payment process. Payment method: %s.', $this->woopay_domain ), $this->get_paymethod_txt( $this->method ) );

			update_post_meta( $orderid, '_order_currency', $this->get_currency() );

			$order->update_status( 'pending' );

			$this->add_mobile_str( $orderid );
			$this->log( $message, $orderid );
			$order->add_order_note( sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() ) );
		}

		public function woopay_payment_complete( $orderid, $tid, $method ) {
			$order		= new WC_Order( $orderid );
			$message	= __( 'Payment complete.', $this->woopay_domain );

			update_post_meta( $order->id, '_' . $this->woopay_api_name . '_tid', $tid );

			$order->payment_complete( $tid );

			$this->log( $message, $orderid );
			$order->add_order_note( sprintf( __( '%s Payment method: %s. TID: %s. Timestamp: %s.', $this->woopay_domain ), $message, $this->get_paymethod_txt( $method ), $tid, $this->get_timestamp() ) );
		}

		public function woopay_cas_payment_complete( $orderid, $tid, $method ) {
			$order		= new WC_Order( $orderid );

			if ( $order->status == 'awaiting' ) {
				$message	= __( 'CAS notification received. Payment complete.', $this->woopay_domain );

				$order->update_status( 'pending' );

				$order->payment_complete( $tid );

				$this->log( $message, $orderid );
				$order->add_order_note( sprintf( __( '%s Payment method: %s. TID: %s. Timestamp: %s.', $this->woopay_domain ), $message, $this->get_paymethod_txt( $method ), $tid, $this->get_timestamp() ) );
			} else {
				$message	= __( 'CAS notification received, but payment cannot be completed.', $this->woopay_domain );

				$this->log( $message, $orderid );
				$order->add_order_note( sprintf( __( '%s Payment method: %s. TID: %s. Timestamp: %s.', $this->woopay_domain ), $message, $this->get_paymethod_txt( $method ), $tid, $this->get_timestamp() ) );
			}
		}

		public function woopay_payment_awaiting( $orderid, $tid, $method, $bankname, $bankaccount, $expirydate ) {
			$order		= new WC_Order( $orderid );
			$message	= __( 'Waiting for payment.', $this->woopay_domain );

			update_post_meta( $order->id, '_' . $this->woopay_api_name . '_tid', $tid );
			update_post_meta( $order->id, '_' . $this->woopay_api_name . '_bankname', $bankname );
			update_post_meta( $order->id, '_' . $this->woopay_api_name . '_bankaccount', $bankaccount );
			update_post_meta( $order->id, '_' . $this->woopay_api_name . '_expirydate', $expirydate );

			$orderid		= $order->id;

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$order->update_status( 'awaiting' );

			$this->log( $message, $orderid );
			$order->add_order_note( sprintf( __( '%s Payment method: %s. Bank Name: %s. Bank Account: %s. Due Date: %s. TID: %s. Timestamp: %s.', $this->woopay_domain ), $message, $this->get_paymethod_txt( $method ), $bankname, $bankaccount, $this->get_dateformat( $expirydate, 'Y-m-d' ), $tid, $this->get_timestamp() ) );
		}

		public function woopay_payment_failed( $orderid, $resultcode = null, $resultmsg = null, $type = null ) {
			$order		= new WC_Order( $orderid );

			if ( $type == 'CAS' ) {
				$message		= sprintf( __( 'CAS notification received, but failed to complete the payment. Code: %s. Message %s.', $this->woopay_domain ), $resultcode, $resultmsg );
			} else {
				if ( $order->status == 'completed' ) {
					$message		= __( 'Payment request received but order is already completed.', $this->woopay_domain );
					$resultcode		= null;
					$resultmsg		= null;
				} elseif ( $order->status == 'processing' ) {
					$message		= __( 'Payment request received but order is in processing.', $this->woopay_domain );
					$resultcode		= null;
					$resultmsg		= null;
				} else {
					$message = __( 'Payment failed.', $this->woopay_domain );
				}
			}

			wc_add_notice( $message, 'error' );
			$this->log( $message, $orderid );

			if ( $resultcode != null ) {
				$this->log( __( 'Result Code: ', $this->woopay_domain ) . $resultcode, $orderid );
			}

			if ( $resultmsg != null ) {
				$this->log( __( 'Result Message: ', $this->woopay_domain ) . $resultmsg, $orderid );
			}

			if ( $order->status == 'pending' ) {
				$order->update_status( 'failed', sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() ) );
			} else {
				$order->add_order_note( sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() ) );
			}
		}

		public function woopay_payment_integrity_failed( $orderid ) {
			$order		= new WC_Order( $orderid );
			$message	= __( 'Failed to verify integrity of payment.', $this->woopay_domain );

			$order->update_status( 'on-hold' );

			$this->log( $message, $orderid );
			wc_add_notice( $message, 'error' );
			$order->add_order_note( sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() ) );
		}

		public function woopay_user_cancelled( $orderid ) {
			$order		= new WC_Order( $orderid );
			$message	= __( 'User cancelled.', $this->woopay_domain );

			$this->log( $message, $orderid );
			$order->add_order_note( sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() ) );
		}

		public function woopay_add_order_note( $orderid, $message ) {
			$order		= new WC_Order( $orderid );

			$this->log( $message, $orderid );
			$order->add_order_note( sprintf( __( '%s Timestamp: %s.', $this->woopay_domain ), $message, $this->get_timestamp() ) );
		}

		public function get_virtual_information( $order ) {
			$order = new WC_Order( $order );

			$orderid		= $order->id;

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();


			$bankname		= get_post_meta( $orderid, '_' . $this->woopay_api_name . '_bankname', true );
			$bankaccount	= get_post_meta( $orderid, '_' . $this->woopay_api_name . '_bankaccount', true );
			$expirydate		= get_post_meta( $orderid, '_' . $this->woopay_api_name . '_expirydate', true );

			if ( $bankname != '' && $bankaccount != '' && $expirydate != '' ) {
				echo  '<h2>' . __( 'Virtual Account Information', $this->woopay_domain ) . '</h2>';
				echo  '<div class="clear"></div>';
				echo  '<ul class="order_details">';
				echo  '<li class="order">' . __( 'Bank Name', $this->woopay_domain ) . '<strong>' . $bankname . '</strong></li>';
				echo  '<li class="order">' . __( 'Account No', $this->woopay_domain ) . '<strong>' . $bankaccount . '</strong></li>';
				echo  '<li class="order">' . __( 'Due Date', $this->woopay_domain ) . '<strong>' . $this->get_dateformat( $expirydate, 'Y-m-d' ) . '</strong></li>';
				echo  '</ul>';
				echo  '<div class="clear"></div>';
			}
		}

		public function show_virtual_information( $orderid ) {
			$bankname		= get_post_meta( $orderid, '_' . $this->woopay_api_name . '_bankname', true );
			$bankaccount	= get_post_meta( $orderid, '_' . $this->woopay_api_name . '_bankaccount', true );
			$expirydate		= get_post_meta( $orderid, '_' . $this->woopay_api_name . '_expirydate', true );

			if ( $bankname != '' && $bankaccount != '' && $expirydate != '' ) {
				echo  '<h2>' . __( 'Virtual Account Information', $this->woopay_domain ) . '</h2>';
				echo  '<div class="clear"></div>';
				echo  '<ul class="order_details">';
				echo  '<li class="order">' . __( 'Bank Name', $this->woopay_domain ) . '<strong>' . $bankname . '</strong></li>';
				echo  '<li class="order">' . __( 'Account No', $this->woopay_domain ) . '<strong>' . $bankaccount . '</strong></li>';
				echo  '<li class="order">' . __( 'Due Date', $this->woopay_domain ) . '<strong>' . $this->get_dateformat( $expirydate, 'Y-m-d' ) . '</strong></li>';
				echo  '</ul>';
				echo  '<div class="clear"></div>';
			}
		}

		public function add_virtual_information( $order, $sent_to_admin ) {
			$order = new WC_Order( $order );

			$orderid		= $order->id;

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();

			$this->get_woopay_settings();

			$bankname		= get_post_meta( $orderid, '_' . $this->woopay_api_name . '_bankname', true );
			$bankaccount	= get_post_meta( $orderid, '_' . $this->woopay_api_name . '_bankaccount', true );
			$expirydate		= get_post_meta( $orderid, '_' . $this->woopay_api_name . '_expirydate', true );

			if ( $bankname != '' && $bankaccount != '' && $expirydate != '' ) {
				$email_option = get_option( 'woocommerce_customer_awaiting_order_settings' );

				if ( ! isset( $email_option[ 'email_type' ] ) ) {
					$email_type = 'html';
				} else {
					$email_type = $email_option[ 'email_type' ];
				}

				if ( $email_type == 'html' || $email_type == 'multipart' ) {
					echo '<h2>' . __( 'Virtual Account Information', $this->woopay_domain ) . '</h2>';
					echo  '<p><strong>' . __( 'Bank Name', $this->woopay_domain ) . ':</strong> ' . $bankname . '</p>';
					echo  '<p><strong>' . __( 'Account No', $this->woopay_domain ) . ':</strong> ' . $bankaccount . '</p>';
					echo  '<p><strong>' . __( 'Due Date', $this->woopay_domain ) . ':</strong> ' . $this->get_dateformat( $expirydate, 'Y-m-d' ) . '</p>';
				} else {
					echo __( 'Virtual Account Information', $this->woopay_domain ) . "\n\n";
					echo __( 'Bank Name', $this->woopay_domain ) . ': ' . $bankname . "\n";
					echo __( 'Account No', $this->woopay_domain ) . ': ' . $bankaccount . "\n";
					echo __( 'Due Date', $this->woopay_domain ) . ': ' . $this->get_dateformat( $expirydate, 'Y-m-d' ) . "\n\n";
				}
			}
		}

		public function add_customer_refund( $actions, $order ) {
			$this->woopay_refund_scripts();

			echo "<input type='hidden' id='" . $this->woopay_plugin_name . "-ask-refund-msg' value='" . __( 'Are you sure you want to continue the refund?', $this->woopay_domain ) . "'>";
			echo "<input type='hidden' id='" . $this->woopay_plugin_name . "-ask-decline-msg' value='" . __( 'Are you sure you want to decline the delivery?', $this->woopay_domain ) . "'>";
			echo "<input type='hidden' id='" . $this->woopay_plugin_name . "-prompt-confirm-msg' value='" . __( 'Enter your auth num.', $this->woopay_domain ) . "'>";

			$orderid		= $order->id;
			$tid			= get_post_meta( $order->id, '_' . $this->woopay_api_name . '_tid', true );

			$this->id		= $this->get_payment_method( $orderid );
			$this->init_settings();
			$this->get_woopay_settings();

			$customer_refund_array		= $this->customer_refund;

			if ( is_array( $customer_refund_array ) ) {
				if ( in_array( 'wc-' . $order->status, $customer_refund_array ) ) {
					if ( $tid != '' ) {
						$cancel_api = $this->get_api_url( 'refund_request' );
						$redirect = get_permalink( wc_get_page_id( 'myaccount' ) );

						$actions[ $this->woopay_plugin_name . '-cancel' ] = array(
							'url'  => wp_nonce_url( add_query_arg( array( 'orderid' => $orderid, 'tid' => $tid, 'redirect' => $redirect, 'type' => 'customer' ), $cancel_api ), $this->woopay_api_name . '_customer_refund' ),
							'name' => $this->refund_btn_txt
						);
					}
				}
			}

			return $actions;
		}

		public function get_payment_method( $orderid ) {
			$order				= new WC_Order( $orderid );
			$payment_method		= get_post_meta( $order->id, '_payment_method', true );

			return $payment_method;
		}

		public function is_valid_for_use( $allowed_currency ) {
			if ( is_array ( $allowed_currency ) ) {
				if ( in_array ( $this->get_currency(), $allowed_currency ) ) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}

		public function currency_check( $order, $allowed_currency ) {
			$currency = get_post_meta( $order->id, '_order_currency', true );

			if ( in_array( $currency, $allowed_currency ) ) {
				return true;
			} else {
				return false;
			}
		}

		public function get_name_lang( $first_name, $last_name ) {
			if ( get_locale() == 'ko_KR' ) {
				return $last_name.$first_name;
			} else {
				return $first_name.' '.$last_name;
			}
		}

		public function check_ios() {
			$agent = $_SERVER[ 'HTTP_USER_AGENT' ];
			
			if ( stripos( $agent, 'iPod' ) || stripos( $agent, 'iPhone' ) || stripos( $agent, 'iPad' ) ) {
				return true;
			} else {
				return false;
			}
		}

		public function check_mobile() {
			$agent = $_SERVER[ 'HTTP_USER_AGENT' ];
			
			if ( stripos ( $agent, 'iPod' ) || stripos( $agent, 'iPhone' ) || stripos( $agent, 'iPad' ) || stripos( $agent, 'Android' ) ) {
				return true;
			} else {
				return false;
			}
		}

		public function check_ie() {
			$agent = $_SERVER['HTTP_USER_AGENT'];

			if ( stripos ( $agent, 'Trident' ) || stripos( $agent, 'MSIE' ) ) {
				return true;
			} else {
				return false;
			}
		}

		public function add_mobile_str( $orderid ) {
			if ( $this->check_mobile() ) {
			$add_mobile_meta = get_post_meta( $orderid, '_payment_method_title', true );
				if ( ! stripos( $add_mobile_meta, __( ' (Mobile)', $this->woopay_domain ) ) ) {
					$add_mobile_meta = $add_mobile_meta . __( ' (Mobile)', $this->woopay_domain );
				}
				update_post_meta( $orderid, '_payment_method_title', $add_mobile_meta );
			} else {
				$add_mobile_meta = get_post_meta( $orderid, '_payment_method_title', true );
				if ( stripos( $add_mobile_meta, __( ' (Mobile)', $this->woopay_domain ) ) ) {
					$add_mobile_meta = str_replace( __( ' (Mobile)', $this->woopay_domain ), '', $add_mobile_meta );
				}
				update_post_meta( $orderid, '_payment_method_title', $add_mobile_meta );
			}
		}

		public function get_timestamp( $type = null ) {
			if ( $type != null ) {
				return current_time( $type );
			} else {
				return current_time( 'Y-m-d H:i:s' );
			}
		}

		public function get_expirytime( $interval, $type = null ) {
			if ( ! intval( $interval ) ) {
				return $this->get_timestamp( $type );
			}

			$current_time		= $this->get_timestamp();
			$current_time		= new DateTime( $current_time );
			$expiry_time		= $current_time->add( new DateInterval( 'P' . $interval . 'D' ) );

			if ( $type != null ) {
				return date_format( $expiry_time, $type );
			} else {
				return date_format( $expiry_time, 'Y-m-d H:i:s' );
			}
		}

		public function get_dateformat( $date, $type = null ) {
			$time = new DateTime( $date );

			if ( $type != null ) {
				return date_format( $time, $type );
			} else {
				return date_format( $time, 'Y-m-d H:i:s' );
			}
		}

		public function get_status_array() {
			return wc_get_order_statuses();
		}

		public function get_currency_str( $currency ) {
			$i = 0;
			$currency_str = "";

			if ( is_array( $currency ) ) {
				foreach ( $currency as $key => $value ) {
					$currency_str .= ( ( $i > 0 ) ? ", " : "" ) . $value;
					$i++;
				}

				return $currency_str;
			} else {
				return;
			}
		}

		public function get_chrome_version() { 
			$u_agent = $_SERVER[ 'HTTP_USER_AGENT' ];

			$version = '';

			if ( preg_match( '/Chrome/i', $u_agent) ) {
				$bname		= 'Google Chrome';
				$ub			= 'Chrome';
				$known		= array( 'Version', $ub, 'other' );
				$pattern	= '#(?<browser>' . join( '|', $known ) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';

				if ( ! preg_match_all( $pattern, $u_agent, $matches ) ) {
				}

				$version	= $matches[ 'version' ][0];

				$version	= explode( '.', $version );

				$version	= $version[0];
			}

			return $version;
		}

		public function get_client_ip() {
			if ( ! empty( $_SERVER[ 'HTTP_CLIENT_IP' ] ) ) {
				$ip = $_SERVER[ 'HTTP_CLIENT_IP' ];
			} elseif ( ! empty( $_SERVER[ 'HTTP_X_FORWARDED_FOR' ] ) ) {
				$ip = $_SERVER[ 'HTTP_X_FORWARDED_FOR' ];
			} else {
				$ip = $_SERVER[ 'REMOTE_ADDR' ];
			}
			return $ip;
		}

		public function get_server_ip() {
			return $_SERVER[ 'SERVER_ADDR' ];
		}

		public function get_paymethod_txt( $pay_type ) {
			switch ( $pay_type ) {
				case 'CARD' :
				case 'VCard' :
				case 'Card' :
				case 'ISP' :
				case 'onlycard' :
				case '100000000000' :
				case 'SC0010' :
				case 'card' :
				case '100' :
				case '101' :
				case '102' :
				case '103' :
					return __( 'Credit Card', $this->woopay_domain );
					break;
				case 'BANK' :
				case 'DirectBank' :
				case 'onlydbank' :
				case '010000000000' :
				case 'SC0030' :
				case '4' :
					return __( 'Account Transfer', $this->woopay_domain );
					break;
				case 'VBANK' :
				case 'Vbank' :
				case 'VBank' :
				case 'onlyvbank' :
				case '001000000000' :
				case 'SC0040' :
				case '7' :
					return __( 'Virtual Account', $this->woopay_domain );
					break;
				case 'CELLPHONE' :
				case 'HPP' :
				case 'MOBILE' :
				case 'onlyhpp' :
				case '000010000000' :
				case 'SC0060' :
				case '801' :
					return __( 'Mobile Payment', $this->woopay_domain );
					break;
				case '104' :
					return __( 'International Credit Card', $this->woopay_domain );
					break;
				case 'APAY' :
				case '106' :
					return __( 'Alipay', $this->woopay_domain );
					break;
				case '111' :
					return __( 'Tenpay', $this->woopay_domain );
					break;
				case '112' :
					return __( 'Inpay', $this->woopay_domain );
					break;
				case '113' :
					return __( 'China Pay', $this->woopay_domain );
					break;
				case '301' :
					return __( 'Open Pay', $this->woopay_domain );
					break;
				case 'GIFTCARD' :
					return __( 'Gift Card', $this->woopay_domain );
					break;
				case 'TMONEY' :
					return __( 'T-Money', $this->woopay_domain );
					break;
				case 'PPAY' :
					return __( 'PayPal', $this->woopay_domain );
					break;
				default :
					return '';
			}
		}

		public function get_bankname( $bankcode ) {
			$bankcode = str_replace( ' ', '', $bankcode );

			$_bankcode = array (
				'02' 	=> __( 'KDB', $this->woopay_domain ),
				'03'	=> __( 'IBK', $this->woopay_domain ),
				'003'	=> __( 'IBK', $this->woopay_domain ),
				'04'	=> __( 'Kookmin Bank', $this->woopay_domain ),
				'004'	=> __( 'Kookmin Bank', $this->woopay_domain ),
				'05'	=> __( 'KEB', $this->woopay_domain ),
				'07'	=> __( 'Suhyup', $this->woopay_domain ),
				'11'	=> __( 'Nonghyup', $this->woopay_domain ),
				'011'	=> __( 'Nonghyup', $this->woopay_domain ),
				'12'	=> __( 'Nonghyup', $this->woopay_domain ),
				'16'	=> __( 'Chukhyup', $this->woopay_domain ),
				'20'	=> __( 'Woori Bank', $this->woopay_domain ),
				'020'	=> __( 'Woori Bank', $this->woopay_domain ),
				'21'	=> __( 'Shinhan Bank', $this->woopay_domain ),
				'23'	=> __( 'Standard Chartered', $this->woopay_domain ),
				'25'	=> __( 'Hana Bank', $this->woopay_domain ),
				'26'	=> __( 'Shinhan Bank', $this->woopay_domain ),
				'026'	=> __( 'Shinhan Bank', $this->woopay_domain ),
				'27'	=> __( 'Citi Bank', $this->woopay_domain ),
				'31'	=> __( 'Daegu Bank', $this->woopay_domain ),
				'32'	=> __( 'Busan Bank', $this->woopay_domain ),
				'34'	=> __( 'Kwangju Bank', $this->woopay_domain ),
				'35'	=> __( 'Jeju Bank', $this->woopay_domain ),
				'37'	=> __( 'JB Bank', $this->woopay_domain ),
				'38'	=> __( 'Kangwon Bank', $this->woopay_domain ),
				'39'	=> __( 'Kyongnam Bank', $this->woopay_domain ),
				'41'	=> __( 'BC Card', $this->woopay_domain ),
				'53'	=> __( 'Citi Bank', $this->woopay_domain ),
				'54'	=> __( 'HSBC', $this->woopay_domain ),
				'71'	=> __( 'Korea Post', $this->woopay_domain ),
				'071'	=> __( 'Korea Post', $this->woopay_domain ),
				'81'	=> __( 'Hana Bank', $this->woopay_domain ),
				'081'	=> __( 'Hana Bank', $this->woopay_domain ),
				'83'	=> __( 'Peace Bank', $this->woopay_domain ),
				'87'	=> __( 'Shinsegae', $this->woopay_domain ),
				'88'	=> __( 'Shinhan Bank', $this->woopay_domain ),
				'D1'	=> __( 'Yuanta Securities', $this->woopay_domain ),
				'D2'	=> __( 'Hyundai Securities', $this->woopay_domain ),
				'D3'	=> __( 'Mirae Asset', $this->woopay_domain ),
				'D4'	=> __( 'Korea Investment', $this->woopay_domain ),
				'D5'	=> __( 'NH Investment', $this->woopay_domain ),
				'D6'	=> __( 'HI Investment', $this->woopay_domain ),
				'D7'	=> __( 'HMC Investment', $this->woopay_domain ),
				'D8'	=> __( 'SK Securities', $this->woopay_domain ),
				'D9'	=> __( 'Daishin Securities', $this->woopay_domain ),
				'DA'	=> __( 'Hana Daetoo Securities', $this->woopay_domain ),
				'DB'	=> __( 'Shinhan Investment', $this->woopay_domain ),
				'DC'	=> __( 'Dongbu Securities', $this->woopay_domain ),
				'DD'	=> __( 'Eugene Investment', $this->woopay_domain ),
				'DE'	=> __( 'Meritz Securities', $this->woopay_domain ),
				'DF'	=> __( 'Shinyoung Secruities', $this->woopay_domain ),
			);

			return $_bankcode[ $bankcode ];
		}

		public function deleteDirectory( $dir ) {
			if ( ! file_exists( $dir ) ) {
				return true;
			}

			if ( ! is_dir( $dir ) ) {
				return @unlink( $dir );
			}

			foreach ( scandir( $dir ) as $item ) {
				if ( $item == '.' || $item == '..' ) {
					continue;
				}

				if ( ! $this->deleteDirectory( $dir . DIRECTORY_SEPARATOR . $item ) ) {
					return false;
				}
			}

			return rmdir( $dir );
		}

		public function parse_http_query( $query ) { 
			$queryParts = explode( '&', $query );

			$params = array(); 
			foreach ( $queryParts as $param ) { 
				$item = explode( '=', $param );
				$params[ $item[0] ] = $item[1];
			}
			return $params; 
		}

		public function get_theme_template_file( $template ) {
			return get_stylesheet_directory() . '/' . apply_filters( 'woocommerce_template_directory', 'woocommerce', $template ) . '/' . $template;
		}

		public function get_file_permission( $file ) {
			if ( file_exists( $file ) ) {
				$octa_perm = fileperms ( $file );

				$perm = sprintf( '%o', $octa_perm );

				return (int)substr( $perm, -4 );
			} else {
				return 0;
			}
		}

		public function change_file_permission( $file, $perm ) {
			if ( file_exists( $file ) ) {
				if ( @chmod( $file, octdec( $perm ) ) ) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}

		public function get_random_string( $str ) {
			return substr( hash( 'sha256', current_time( 'timestamp' ) . $str . $this->woopay_plugin_name ), 0, 15 );
		}

		public function get_upload_dir() {
			$upload_dir = get_option( $this->woopay_api_name . '_upload_dir', '' );
			$wp_upload_dir = wp_upload_dir();

			if ( $upload_dir == '' ) {
				$random = $this->get_random_string( 'upload' );
				$woopay_upload_dir = $wp_upload_dir[ 'basedir' ] . '/' . $this->woopay_plugin_name . '/' . $random;

				update_option( $this->woopay_api_name . '_upload_dir', $woopay_upload_dir );

				return $woopay_upload_dir;
			} else {
				if ( strpos( $upload_dir, $wp_upload_dir[ 'basedir' ] ) !== false ) {
					return $upload_dir;
				} else {
					$random = $this->get_random_string( 'upload' );
					$woopay_upload_dir = $wp_upload_dir[ 'basedir' ] . '/' . $this->woopay_plugin_name . '/' . $random;

					update_option( $this->woopay_api_name . '_upload_dir', $woopay_upload_dir );

					return $woopay_upload_dir;
				}
			}
		}

		public function make_conf_file( $mertid, $mertkey ) {
			$txt = "server_id = 01\n";
			$txt .= "timeout = 60\n";
			$txt .= "log_level = 0\n";
			$txt .= "verify_cert = 1\n";
			$txt .= "verify_host = 1\n";
			$txt .= "report_error = 1\n";
			$txt .= "output_UTF8 = 1\n";
			$txt .= "auto_rollback = 1\n";
			$txt .= "log_dir = " . $this->woopay_plugin_basedir . '/bin/log' . "\n";
			$txt .= "t" . $mertid . " = " . $mertkey . "\n";
			$txt .= $mertid . " = " . $mertkey . "\n";

			$conf = $this->woopay_plugin_basedir . '/bin/lib/conf/mall.conf';

			$file = fopen( $conf, "w" );
			fwrite( $file, $txt );
			fclose( $file );
		}

		public function get_test_mid( $method ) {
			switch ( $method ) {
				case 'card' :
				case '100' :
				case '101' :
				case '102' :
				case '103' :
				case '4' :
				case '7' :
				case '801' :
				case 'paygate_pro_card' :
				case 'paygate_pro_transfer' :
				case 'paygate_pro_mobile' :
					return 'paygatekr';
					break;
				case '104' :
				case '106' :
				case '111' :
				case '112' :
				case '113' :
				case 'paygate_pro_international' :
					return 'paygateus';
					break;
				case '301' :
				case 'paygate_pro_openpay' :
					return 'paygatestore';
					break;
				default :
					return '';
			}
		}

		public function get_test_hashkey( $method ) {
			switch ( $method ) {
				case 'card' :
				case '100' :
				case '101' :
				case '102' :
				case '103' :
				case '4' :
				case '7' :
				case '801' :
				case 'paygate_pro_card' :
				case 'paygate_pro_transfer' :
				case 'paygate_pro_mobile' :
					return 'test1';
					break;
				case '104' :
				case '106' :
				case '111' :
				case '112' :
				case '113' :
				case 'paygate_pro_international' :
					return '123!';
					break;
				case '301' :
				case 'paygate_pro_openpay' :
					return '123!';
					break;
				default :
					return '';
			}
		}

		public function trim_trailing_zeros( $number ) {
			if ( strpos( $number, '.' ) !== false ) {
				$number = rtrim( $number, '0' );
			}
			return rtrim( $number, '.' );
		}
		
		public function get_hash( $key, $data ) {
			if ( $key == '' ) {
				return false;
			}
			$orgtotal = $data[ 'orgtotal' ];
			$total = $data[ 'total' ];

			if ( $orgtotal != $total ) $total = $orgtotal;
			
			if ( $data[ 'orgcurrency' ] == 'WON' ) {
				$currency = 'KRW';
			} else {
				$currency = $data[ 'orgcurrency' ];
			}

			if ( $currency == 'KRW' || $currency == 'WON' ) {
				$total = $this->trim_trailing_zeros( $total );
			}

			$to_hash = $data[ 'replycode' ] . $data[ 'tid' ] . $data[ 'PG_OID' ] . $total . $currency;
			$hashed = hash( 'sha256', $key . $to_hash );
			
			return $hashed;
		}

		function get_langcode( $locale ) {
			return substr( $locale, -2 );
		}

		// API Check
		public function woopay_check_api() {
			$response = wp_remote_get( $this->get_api_url( 'check_api' ) );

			if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
				$body = $response[ 'body' ];
				$result = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $body) );

				if ( $result->result == 'success' ) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
	}
}
?>