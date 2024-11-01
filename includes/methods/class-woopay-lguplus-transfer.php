<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayLGUPlusTransfer' ) ) {
	class WooPayLGUPlusTransfer extends WooPayLGUPlusPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'lguplus_transfer';
			$this->section					= 'woopaylguplustransfer';
			$this->method 					= 'SC0030';
			$this->method_title 			= __( 'LG U+ Account Transfer', $this->woopay_domain );
			$this->title_default 			= __( 'Account Transfer', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via account transfer.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'bank';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}
	}

	function add_lguplus_transfer( $methods ) {
		$methods[] = 'WooPayLGUPlusTransfer';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_lguplus_transfer' );
}