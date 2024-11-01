<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooPayLogger' ) ) {
	class WooPayLogger {
		private $_handles;

		public function __construct() {
			$this->_handles = array();
		}

		public function __destruct() {
			foreach ( $this->_handles as $handle ) {
				@fclose( $handle );
			}
		}

		public function woopay_get_log_file_path( $handle, $random ) {
			return trailingslashit( WC_LOG_DIR ) . $handle . '-' . $random . '.log';
		}

		private function open( $handle, $random ) {
			if ( isset( $this->_handles[ $handle ] ) ) {
				return true;
			}

			if ( $this->_handles[ $handle ] = @fopen( $this->woopay_get_log_file_path( $handle, $random ), 'a' ) ) {
				return true;
			}

			return false;
		}

		public function add( $handle, $message, $random ) {
			if ( $this->open( $handle, $random ) && is_resource( $this->_handles[ $handle ] ) ) {
				$time = date_i18n( '[Y-m-d H:i:s]' );
				@fwrite( $this->_handles[ $handle ], $time . " " . $message . "\n" );
			}
		}

		public function clear( $handle ) {
			if ( $this->open( $handle ) && is_resource( $this->_handles[ $handle ] ) ) {
				@ftruncate( $this->_handles[ $handle ], 0 );
			}
		}

	}

}
