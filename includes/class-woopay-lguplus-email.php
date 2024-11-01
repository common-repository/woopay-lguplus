<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayLGUPlusEmail' ) ) {
	class WooPayLGUPlusEmail extends WC_Email {
		
		public function __construct() {
			$this->id               = 'customer_awaiting_order';
			$this->title            = __( 'Awaiting Payment (LG U+)', 'woopay-core' );
			$this->description      = __( 'This is an order notification sent to customers containing their virtual bank information.', 'woopay-core' );

			$this->heading          = __( 'Thank you for your order', 'woopay-core' );
			$this->subject          = __( 'Your {site_title} virtual bank information from {order_date}', 'woopay-core' );

			$this->template_base	= str_replace( 'includes/', '', dirname( __FILE__ ) . '/assets/templates/' );

			$this->template_html    = 'emails/woopay-lguplus-awaiting-payment.php';
			$this->template_plain   = 'emails/plain/woopay-lguplus-awaiting-payment.php';

			// Triggers for this email
			add_action( 'woocommerce_order_status_pending_to_awaiting_notification', array( $this, 'trigger' ) );

			// Call parent constructor
			parent::__construct();
		}

		public function trigger( $order_id ) {
			$payment_method = get_post_meta( $order_id, '_payment_method', true );

			if ( $payment_method != 'lguplus_virtual' ) {
				return;
			}

			if ( $order_id ) {
				$this->object       = wc_get_order( $order_id );
				$this->recipient    = $this->object->billing_email;

				$this->find['order-date']      = '{order_date}';
				$this->find['order-number']    = '{order_number}';

				$this->replace['order-date']   = date_i18n( wc_date_format(), strtotime( $this->object->order_date ) );
				$this->replace['order-number'] = $this->object->get_order_number();
			}

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return;
			}

			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		public function get_content_html() {
			ob_start();
			wc_get_template( $this->template_html, array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false
			), null, str_replace( 'includes/', '', dirname( __FILE__ ) . '/assets/templates/' ) );
			return ob_get_clean();
		}

		public function get_content_plain() {
			ob_start();
			wc_get_template( $this->template_plain, array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true
			), null, str_replace( 'includes/', '', dirname( __FILE__ ) . '/assets/templates/' ) );
			return ob_get_clean();
		}
	}
}

return new WooPayLGUPlusEmail();