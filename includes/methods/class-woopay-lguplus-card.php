<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayLGUPlusCard' ) ) {
	class WooPayLGUPlusCard extends WooPayLGUPlusPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'lguplus_card';
			$this->section					= 'woopaylgupluscard';
			$this->method 					= 'SC0010';
			$this->method_title 			= __( 'LG U+ Credit Card', $this->woopay_domain );
			$this->title_default 			= __( 'Credit Card', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via credit card.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'card';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}
	}

	function add_lguplus_card( $methods ) {
		$methods[] = 'WooPayLGUPlusCard';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_lguplus_card' );
}