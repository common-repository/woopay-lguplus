<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayLGUPlusMobile' ) ) {
	class WooPayLGUPlusMobile extends WooPayLGUPlusPayment {
		public function __construct() {
			parent::__construct();

			$this->method_init();
		}

		function method_init() {
			$this->id						= 'lguplus_mobile';
			$this->section					= 'woopaylguplusmobile';
			$this->method 					= 'SC0060';
			$this->method_title 			= __( 'LG U+ Mobile Payment', $this->woopay_domain );
			$this->title_default 			= __( 'Mobile Payment', $this->woopay_domain );
			$this->desc_default  			= __( 'Payment via mobile payment.', $this->woopay_domain );
			$this->allowed_currency			= array( 'KRW' );
			$this->default_checkout_img		= 'mobile';
			$this->supports					= array( 'products', 'refunds' );
			$this->has_fields				= false;
			$this->allow_testmode			= false;
		}
	}

	function add_lguplus_mobile( $methods ) {
		$methods[] = 'WooPayLGUPlusMobile';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_lguplus_mobile' );
}