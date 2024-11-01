<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayLGUPlusVirtual' ) ) {
	class WooPayLGUPlusVirtual extends WooPayLGUPlusPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'lguplus_virtual';
			$this->section					= 'woopaylguplusvirtual';
			$this->method 					= 'SC0040';
			$this->method_title 			= __( 'LG U+ Virtual Account', $this->woopay_domain );
			$this->title_default 			= __( 'Virtual Account', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via virtual account.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'bank';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= true;
		}
	}

	function add_lguplus_virtual( $methods ) {
		$methods[] = 'WooPayLGUPlusVirtual';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_lguplus_virtual' );
}