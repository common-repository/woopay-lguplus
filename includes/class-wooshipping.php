<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooShipping' ) ) {
	class WooShipping extends WC_Shipping_Method {

		public $wooshippings = array();

		public function __construct() {
			$this->id 										= 'woopshipping';
			$this->method_title 							= __( 'WooShipping', 'woopay-core' );
			$this->wooshipping_option 						= 'woocommerce_wooshippings';
			$this->method_description 						= __( 'WooShipping lets you control various shipping fees.', 'woopay-core' );

			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_wooshippings' ) );
			add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'save_default_costs' ) );

			add_filter( 'woocommerce_shipping_methods', array( $this, 'add_wooshipping_method' ) );

			$this->shipping_init();
		}

		public function shipping_init() {
			$this->init_form_fields();
			$this->init_settings();
			$this->add_extra_form_fields();

			$this->enabled				= $this->get_option( 'enabled' );
			$this->title				= $this->get_option( 'title' );
			$this->availability			= 'specific';
			$this->countries			= array( 'KR' );
			$this->type					= 'item';
			$this->shipping_type		= $this->get_option( 'shipping_type' );
			$this->tax_status			= $this->get_option( 'tax_status' );

			// Fixed
			$this->fixed_cost			= $this->get_option( 'fixed_cost' );

			// Conditional
			$this->conditional_cost		= $this->get_option( 'conditional_cost' );
			$this->conditional_total	= $this->get_option( 'conditional_total' );

			$this->get_wooshippings();
		}

		public function add_extra_form_fields() {
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> __( 'Enable/Disable', 'woopay-core' ),
					'type' 			=> 'checkbox',
					'label' 		=> __( 'Enable this shipping method', 'woopay-core' ),
					'default' 		=> 'no',
				),
				'title' => array(
					'title' 		=> __( 'Method Title', 'woopay-core' ),
					'type' 			=> 'text',
					'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woopay-core' ),
					'default'		=> __( 'Shipping Fee', 'woopay-core' ),
					'desc_tip'		=> true
				),
				'shipping_type' => array(
					'title'			=> __( 'Shipping Cost Type', 'woopay-core' ),
					'type'			=> 'select',
					'class'			=> 'wc-enhanced-select',
					'default'		=> 'fixed',
					'options'		=> array(
						'fixed'			=> __( 'Fixed Rates', 'woopay-core' ),
						'free' 			=> __( 'Free Shipping', 'woopay-core' ),
						'conditional' 	=> __( 'Conditional Rates', 'woopay-core' ),
					),
					'description' 	=> __( 'You can select the type of shipping cost.', 'woopay-core' ),
				),
				'tax_status' => array(
					'title' 		=> __( 'Tax Status', 'woopay-core' ),
					'type' 			=> 'select',
					'class'         => 'wc-enhanced-select',
					'default' 		=> 'taxable',
					'options'		=> array(
						'taxable' 		=> __( 'Taxable', 'woopay-core' ),
						'none' 			=> __( 'None', 'woopay-core' )
					),
				),
				'fixed_costs_table' => array(
					'type'				=> 'fixed_costs_table'
				),
				'conditional_costs_table' => array(
					'type'				=> 'conditional_costs_table'
				),
			);

			return $this->form_fields;
		}

		public function calculate_shipping( $package = array() ) {
			$this->rates    = array();
			$cost_per_order = 0;
			$rate           = array(
				'id'    => $this->id,
				'label' => $this->title,
				'cost'  => 0,
			);

			if ( method_exists( $this, $this->type . '_shipping' ) ) {
				$costs = call_user_func( array( $this, $this->type . '_shipping' ), $package );

				if ( ! is_null( $costs ) || $costs > 0 ) {
					if ( is_array( $costs ) ) {
						$costs[ 'order' ] = $cost_per_order;
					} else {
						$costs += $cost_per_order;
					}

					$rate[ 'cost' ]     = $costs;
					$rate[ 'calc_tax' ] = 'per_' . $this->type;

					$this->add_rate( $rate );
				}
			}
		}

		public function item_shipping( $package ) {
			$costs = array();

			$matched = false;

			$class_total		= array();

			switch( $this->shipping_type ) {
			case 'fixed' :
				// Set total price
				foreach ( $package[ 'contents' ] as $item_id => $values ) {
					$_product = $values[ 'data' ];

					if ( $values[ 'quantity' ] > 0 && $_product->needs_shipping() ) {
						$shipping_class = $_product->get_shipping_class();

						if ( ! isset( $class_total[ $shipping_class ] ) ) {
							$class_total[ $shipping_class ] = (int)$_product->get_price() * $values[ 'quantity' ];
							$class_total[ $shipping_class . '_total' ] = 1;
							$class_total[ $shipping_class . '_count' ] = 1;
						} else {
							$class_total[ $shipping_class ] += ( (int)$_product->get_price() * $values[ 'quantity' ] );
							$class_total[ $shipping_class . '_total' ]++;
						}
					}
				}

				// Compare total price to minimum price to set free shipping
				foreach ( $package[ 'contents' ] as $item_id => $values ) {
					$_product = $values[ 'data' ];

					if ( $values[ 'quantity' ] > 0 && $_product->needs_shipping() ) {
						$shipping_class = $_product->get_shipping_class();

						$fixed_cost = 0;

						if ( isset( $this->wooshippings[ $shipping_class ] ) ) {
							$fixed_cost = $this->wooshippings[ $shipping_class ][ 'fixed_cost' ];
							$class_total[ $shipping_class . '_count' ]++;
							$matched = true;
						} elseif ( isset( $this->cost ) && $this->cost !== '' ) {
							$fixed_cost = $this->fixed_cost;
							$class_total[ $shipping_class . '_count' ]++;
							$matched = true;
						}

						if ( $class_total[ $shipping_class . '_count' ] <= $class_total[ $shipping_class . '_total' ] ) {
							$costs[ $item_id ] = 0;
						} else {
							$costs[ $item_id ] = $fixed_cost;
						}
					}
				}
				break;
			case 'free' :
				// Free shipping
				foreach ( $package[ 'contents' ] as $item_id => $values ) {
					$_product = $values[ 'data' ];

					if ( $values[ 'quantity' ] > 0 && $_product->needs_shipping() ) {
						$shipping_class = $_product->get_shipping_class();

						$matched = true;
						$costs[ $item_id ] = 0;
					}
				}
				break;
			case 'conditional' :
				// Set total price
				foreach ( $package[ 'contents' ] as $item_id => $values ) {
					$_product = $values[ 'data' ];

					if ( $values[ 'quantity' ] > 0 && $_product->needs_shipping() ) {
						$shipping_class = $_product->get_shipping_class();

						if ( ! isset( $class_total[ $shipping_class ] ) ) {
							$class_total[ $shipping_class ] = (int)$_product->get_price() * $values[ 'quantity' ];
							$class_total[ $shipping_class . '_total' ] = 1;
							$class_total[ $shipping_class . '_count' ] = 1;
						} else {
							$class_total[ $shipping_class ] += ( (int)$_product->get_price() * $values[ 'quantity' ] );
							$class_total[ $shipping_class . '_total' ]++;
						}
					}
				}

				// Compare total price to minimum price to set free shipping
				foreach ( $package[ 'contents' ] as $item_id => $values ) {
					$_product = $values[ 'data' ];

					if ( $values[ 'quantity' ] > 0 && $_product->needs_shipping() ) {
						$shipping_class = $_product->get_shipping_class();

						$conditional_total = $conditional_cost = 0;

						if ( isset( $this->wooshippings[ $shipping_class ] ) ) {
							$conditional_total = $this->wooshippings[ $shipping_class ][ 'conditional_total' ];

							if ( $class_total[ $shipping_class ] >= $conditional_total ) {
								$conditional_cost = 0;
							} else {
								$conditional_cost = $this->wooshippings[ $shipping_class ][ 'conditional_cost' ];
								$class_total[ $shipping_class . '_count' ]++;
							}
							$matched = true;
						} elseif ( isset( $this->cost ) && $this->cost !== '' ) {
							$conditional_total	= $this->conditional_total;

							if ( $class_total[ $shipping_class ] >= $conditional_total ) {
								$conditional_cost = 0;
							} else {
								$conditional_cost = $this->conditional_cost;
								$class_total[ $shipping_class . '_count' ]++;
							}
							$matched = true;
						} else {
							$conditional_total	= $this->conditional_total;

							if ( $class_total[ $shipping_class ] >= $conditional_total ) {
								$conditional_cost = 0;
							} else {
								$conditional_cost = $this->conditional_cost;
								$class_total[ $shipping_class . '_count' ]++;
							}
							$matched = true;
						}

						if ( $class_total[ $shipping_class . '_count' ] <= $class_total[ $shipping_class . '_total' ] ) {
							$costs[ $item_id ] = 0;
						} else {
							$costs[ $item_id ] = $conditional_cost;
						}
					}
				}
				break;
			}

			if ( $matched ) {
				return $costs;
			} else {
				return 0;
			}
		}

		public function find_shipping_classes( $package ) {
			$found_shipping_classes = array();

			foreach ( $package[ 'contents' ] as $item_id => $values ) {
				if ( $values[ 'data' ]->needs_shipping() ) {
					$found_class = $values[ 'data' ]->get_shipping_class();

					if ( ! isset( $found_shipping_classes[ $found_class ] ) ) {
						$found_shipping_classes[ $found_class ] = array();
					}

					$found_shipping_classes[ $found_class ][ $item_id ] = $values;
				}
			}

			return $found_shipping_classes;
		}

		public function validate_fixed_costs_table_field( $key ) {
			return false;
		}

		public function generate_fixed_costs_table_html() {
			ob_start();

			if ( $this->shipping_type == 'fixed' ) {
			?>
			</table>
			<h3 class="wc-settings-sub-title "><?php echo __( 'Fixed Rates', 'woopay-core' ); ?></h3>
			<p><?php echo __( 'Fixed rates can be set below. You can declare fixed shipping costs per shipping class.', 'woopay-core' );?></p>
			<table class="form-table">
			<?php } ?>
			<tr valign="top" <?php if ( $this->shipping_type != 'fixed' ) { echo 'style="display:none;"'; }?>>
				<th scope="row" class="titledesc"><?php echo __( 'Costs', 'woopay-core' ); ?></th>
				<td class="forminp" id="<?php echo $this->id; ?>_fixed_wooshippings">
					<table class="shippingrows widefat" cellspacing="0">
						<thead>
							<tr>
								<th class="check-column"><input type="checkbox"></th>
								<th class="shipping_class"><?php echo __( 'Shipping Class', 'woopay-core' ); ?></th>
								<th><?php echo __( 'Shipping Cost', 'woopay-core' ); ?> <a class="tips" data-tip="<?php echo __( 'Shipping cost, excluding tax.', 'woopay-core' ); ?>">[?]</a></th>
							</tr>
						</thead>
						<tbody class="wooshippings">
							<tr>
								<td></td>
								<td class="wooshipping_class"><?php echo __( 'Any class', 'woopay-core' ); ?></td>
								<td><input type="text" value="<?php echo esc_attr( wc_format_localized_price( $this->fixed_cost ) ); ?>" name="default_fixed_cost" placeholder="<?php echo __( 'N/A', 'woopay-core' ); ?>" size="4" class="wc_input_price" /></td>
							</tr>
							<?php
							$i = -1;
							if ( $this->wooshippings ) {
								foreach ( $this->wooshippings as $class => $rate ) {
									$i++;

									if ( isset( $rate['fixed_cost'] ) ) {
										echo '<tr class="wooshipping" id="aaa">
											<th class="check-column"><input type="checkbox" name="select" /></th>
											<td class="wooshipping_class">
													<select name="' . esc_attr( $this->id . '_fixed_class[' . $i . ']' ) . '" class="select">';

										if ( WC()->shipping->get_shipping_classes() ) {
											foreach ( WC()->shipping->get_shipping_classes() as $shipping_class ) {
												echo '<option value="' . esc_attr( $shipping_class->slug ) . '" '.selected($shipping_class->slug, $class, false).'>'.$shipping_class->name.'</option>';
											}
										} else {
											echo '<option value="">'.__( 'Select a class&hellip;', 'woopay-core' ).'</option>';
										}

										echo '</select>
											</td>
											<td><input type="text" value="' . esc_attr( $rate['fixed_cost'] ) . '" name="' . esc_attr( $this->id .'_fixed_cost[' . $i . ']' ) . '" placeholder="' . wc_format_localized_price( 0 ) . '" size="4" class="wc_input_price" /></td>
										</tr>';
									}
								}
							}
							?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="4"><a href="#" class="add_fixed button"><?php echo __( 'Add Cost', 'woopay-core' ); ?></a> <a href="#" class="remove_fixed button"><?php echo __( 'Delete Selected Costs', 'woopay-core' ); ?></a></th>
							</tr>
						</tfoot>
					</table>
					<script type="text/javascript">
						jQuery(function() {

							jQuery('#<?php echo $this->id; ?>_fixed_wooshippings').on( 'click', 'a.add_fixed', function(){

								var size = jQuery('#<?php echo $this->id; ?>_fixed_wooshippings tbody .wooshipping').size();

								jQuery('<tr class="wooshipping">\
									<th class="check-column"><input type="checkbox" name="select" /></th>\
									<td class="wooshipping_class">\
										<select name="<?php echo $this->id; ?>_fixed_class[' + size + ']" class="select">\
											<?php
											if (WC()->shipping->get_shipping_classes()) :
												foreach (WC()->shipping->get_shipping_classes() as $class) :
													echo '<option value="' . esc_attr( $class->slug ) . '">' . esc_js( $class->name ) . '</option>';
												endforeach;
											else :
												echo '<option value="">'.__( 'Select a class&hellip;', 'woopay-core' ).'</option>';
											endif;
											?>\
										</select>\
									</td>\
									<td><input type="text" name="<?php echo $this->id; ?>_fixed_cost[' + size + ']" placeholder="<?php echo wc_format_localized_price( 0 ); ?>" size="4" class="wc_input_price" /></td>\
								</tr>').appendTo('#<?php echo $this->id; ?>_fixed_wooshippings table tbody');

								return false;
							});

							// Remove row
							jQuery('#<?php echo $this->id; ?>_fixed_wooshippings').on( 'click', 'a.remove_fixed', function(){
								var answer = confirm("<?php echo __( 'Delete the selected rates?', 'woopay-core' ); ?>");
								if (answer) {
									jQuery('#<?php echo $this->id; ?>_fixed_wooshippings table tbody tr th.check-column input:checked').each(function(i, el){
										jQuery(el).closest('tr').remove();
									});
								}
								return false;
							});

						});
					</script>
				</td>
			</tr>

			<?php
			return ob_get_clean();
		}

		public function validate_conditional_costs_table_field( $key ) {
			return false;
		}

		public function generate_conditional_costs_table_html() {
			ob_start();

			if ( $this->shipping_type == 'conditional' ) {
			?>
			</table>
			<h3 class="wc-settings-sub-title "><?php echo __( 'Conditional Free Shipping', 'woopay-core' ); ?></h3>
			<p><?php echo __( 'Conditional free shipping can be set below. You can declare the minimum price for free shipping.', 'woopay-core' );?></p>
			<table class="form-table">
			<?php } ?>
			<tr valign="top" <?php if ( $this->shipping_type != 'conditional' ) { echo 'style="display:none;"'; }?>>
				<th scope="row" class="titledesc"><?php echo __( 'Costs', 'woopay-core' ); ?></th>
				<td class="forminp" id="<?php echo $this->id; ?>_conditional_wooshippings">
					<table class="shippingrows widefat" cellspacing="0">
						<thead>
							<tr>
								<th class="check-column"><input type="checkbox"></th>
								<th class="shipping_class"><?php echo __( 'Shipping Class', 'woopay-core' ); ?></th>
								<th><?php echo __( 'Shipping Cost', 'woopay-core' ); ?> <a class="tips" data-tip="<?php echo __( 'Shipping cost, excluding tax.', 'woopay-core' ); ?>">[?]</a></th>
								<th><?php echo __( 'Minimum Price', 'woopay-core' ); ?> <a class="tips" data-tip="<?php echo __( 'Minimum price for free shipping.', 'woopay-core' ); ?>">[?]</a></th>
							</tr>
						</thead>
						<tbody class="wooshippings">
							<tr>
								<td></td>
								<td class="wooshipping_class"><?php echo __( 'Any class', 'woopay-core' ); ?></td>
								<td><input type="text" value="<?php echo esc_attr( wc_format_localized_price( $this->conditional_cost ) ); ?>" name="default_conditional_cost" placeholder="<?php echo __( 'N/A', 'woopay-core' ); ?>" size="4" class="wc_input_price" /></td>
								<td><input type="text" value="<?php echo esc_attr( wc_format_localized_price( $this->conditional_total ) ); ?>" name="default_conditional_total" placeholder="<?php echo __( 'N/A', 'woopay-core' ); ?>" size="4" class="wc_input_price" /></td>
							</tr>
							<?php
							$i = -1;
							if ( $this->wooshippings ) {
								foreach ( $this->wooshippings as $class => $rate ) {
									$i++;

									if ( isset( $rate['conditional_cost'] ) ) {
										echo '<tr class="wooshipping">
											<th class="check-column"><input type="checkbox" name="select" /></th>
											<td class="wooshipping_class">
													<select name="' . esc_attr( $this->id . '_conditional_class[' . $i . ']' ) . '" class="select">';

										if ( WC()->shipping->get_shipping_classes() ) {
											foreach ( WC()->shipping->get_shipping_classes() as $shipping_class ) {
												echo '<option value="' . esc_attr( $shipping_class->slug ) . '" '.selected($shipping_class->slug, $class, false).'>'.$shipping_class->name.'</option>';
											}
										} else {
											echo '<option value="">'.__( 'Select a class&hellip;', 'woopay-core' ).'</option>';
										}

										echo '</select>
											</td>
											<td><input type="text" value="' . esc_attr( $rate['conditional_cost'] ) . '" name="' . esc_attr( $this->id .'_conditional_cost[' . $i . ']' ) . '" placeholder="' . wc_format_localized_price( 0 ) . '" size="4" class="wc_input_price" /></td>
											<td><input type="text" value="' . esc_attr( $rate['conditional_total'] ) . '" name="' . esc_attr( $this->id .'_conditional_total[' . $i . ']' ) . '" placeholder="' . wc_format_localized_price( 0 ) . '" size="4" class="wc_input_price" /></td>
										</tr>';
									}
								}
							}
							?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="4"><a href="#" class="add_conditional button"><?php echo __( 'Add Cost', 'woopay-core' ); ?></a> <a href="#" class="remove_conditional button"><?php echo __( 'Delete Selected Costs', 'woopay-core' ); ?></a></th>
							</tr>
						</tfoot>
					</table>
					<script type="text/javascript">
						jQuery(function() {

							jQuery('#<?php echo $this->id; ?>_conditional_wooshippings').on( 'click', 'a.add_conditional', function(){

								var size = jQuery('#<?php echo $this->id; ?>_conditional_wooshippings tbody .wooshipping').size();

								jQuery('<tr class="wooshipping">\
									<th class="check-column"><input type="checkbox" name="select" /></th>\
									<td class="wooshipping_class">\
										<select name="<?php echo $this->id; ?>_conditional_class[' + size + ']" class="select">\
											<?php
											if (WC()->shipping->get_shipping_classes()) :
												foreach (WC()->shipping->get_shipping_classes() as $class) :
													echo '<option value="' . esc_attr( $class->slug ) . '">' . esc_js( $class->name ) . '</option>';
												endforeach;
											else :
												echo '<option value="">'.__( 'Select a class&hellip;', 'woopay-core' ).'</option>';
											endif;
											?>\
										</select>\
									</td>\
									<td><input type="text" name="<?php echo $this->id; ?>_conditional_cost[' + size + ']" placeholder="<?php echo wc_format_localized_price( 0 ); ?>" size="4" class="wc_input_price" /></td>\
									<td><input type="text" name="<?php echo $this->id; ?>_conditional_total[' + size + ']" placeholder="<?php echo wc_format_localized_price( 0 ); ?>" size="4" class="wc_input_price" /></td>\
								</tr>').appendTo('#<?php echo $this->id; ?>_conditional_wooshippings table tbody');

								return false;
							});

							// Remove row
							jQuery('#<?php echo $this->id; ?>_conditional_wooshippings').on( 'click', 'a.remove_conditional', function(){
								var answer = confirm("<?php echo __( 'Delete the selected rates?', 'woopay-core' ); ?>");
								if (answer) {
									jQuery('#<?php echo $this->id; ?>_conditional_wooshippings table tbody tr th.check-column input:checked').each(function(i, el){
										jQuery(el).closest('tr').remove();
									});
								}
								return false;
							});

						});
					</script>
				</td>
			</tr>

			<?php
			return ob_get_clean();
		}

		public function process_wooshippings() {
			$wooshippings			= array();
			$classes				= array();

			$tmp_fixed_class			= isset( $_POST[ $this->id . '_fixed_class'] ) ? $_POST[ $this->id . '_fixed_class' ] : array();
			$tmp_conditional_class		= isset( $_POST[ $this->id . '_conditional_class'] ) ? $_POST[ $this->id . '_conditional_class' ] : array();

			$fixed_class = array();
			$conditional_class = array();

			foreach ( $tmp_fixed_class as $key => $value ) {
				$fixed_class[ $key ] = urlencode( $value );
			}

			foreach ( $tmp_conditional_class as $key => $value ) {
				$conditional_class[ $key ] = urlencode( $value );
			}

			for ( $i = 0; $i <= max( array_merge( array( 0 ), array_keys( $fixed_class ) ) ); $i ++ ) {
				if ( empty( $fixed_class[ $i ] ) ) {
					continue;
				}

				$wooshippings[ urldecode( sanitize_title( $fixed_class[ $i ] ) ) ][ 'fixed_cost' ] = wc_format_decimal( $_POST[ $this->id . '_fixed_cost'][ $i ] );
			}

			for ( $i = 0; $i <= max( array_merge( array( 0 ), array_keys( $conditional_class ) ) ); $i ++ ) {
				if ( empty( $conditional_class[ $i ] ) ) {
					continue;
				}

				$wooshippings[ urldecode( sanitize_title( $conditional_class[ $i ] ) ) ][ 'conditional_cost' ] = wc_format_decimal( $_POST[ $this->id . '_conditional_cost'][ $i ] );
				$wooshippings[ urldecode( sanitize_title( $conditional_class[ $i ] ) ) ][ 'conditional_total' ] = wc_format_decimal( $_POST[ $this->id . '_conditional_total'][ $i ] );
			}

			update_option( $this->wooshipping_option, $wooshippings );

			$this->get_wooshippings();
		}

		public function save_default_costs( $fields ) {
			$fields[ 'fixed_cost' ]				= wc_format_decimal( $_POST[ 'default_fixed_cost' ] );

			$fields[ 'conditional_cost' ]		= wc_format_decimal( $_POST[ 'default_conditional_cost' ] );
			$fields[ 'conditional_total' ]		= wc_format_decimal( $_POST[ 'default_conditional_total' ] );

			return $fields;
		}

		public function get_wooshippings() {
			$this->wooshippings = array_filter( (array) get_option( $this->wooshipping_option ) );
		}

		public function add_wooshipping_method( $methods ) {
			$methods[] = 'WooShipping';
			return $methods;
		}
	}

	return new WooShipping();
}
?>