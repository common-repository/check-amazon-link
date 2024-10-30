<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
} // Exit if accessed directly

if ( ! class_exists( 'AZLC_Logger' ) ) :
	final class AZLC_Logger {


		/**
		 * @var  string  log file
		 */
		private $_file = '';
		private $debug = 0;

		/**
		 * Sets the log pile path.
		 *
		 * @param  string $file
		 */
		public function __construct( $file = null ) {

			$opts        = get_option( "azlc_plugin_options" );
			if(isset($opts['debug'])) {
				$this->debug = $opts['debug'];
			}


			if ( $file == null ) {

				$file = plugin_dir_path( __FILE__ ) . 'debug_log.txt';
				try {
					$fh = fopen( $file, 'a' );
					fclose( $fh );
				} catch ( Exception $e ) {
					// Failed to create the file, oh well...
					$file = null;
				}
			}

			$this->_file = $file;
		}

		/**
		 * Add a message to the log.
		 *
		 *
		 * @param  string $message message to add to the log
		 * @param  array $args arguments to pass to the writer
		 * @param  string $context context of the log message
		 * @param  bool $backtrace show the backtrace
		 *
		 * @return void
		 */
		public function write( $message, array $args = null, $context = null, $backtrace = false ) {

			if ( $this->debug == 0 ) {
				// debugging is turned off, don't log the message.
				return;
			}

			if ( $args !== null ) {
				foreach ( $args as $key => $value ) {
					$message = str_replace( ':' . $key, $value, $message );
				}
			}

			if ( $context !== null ) {
				$context = '[' . strtoupper( str_replace( '-', ' ', $context ) ) . '] ';
			}

			$error_str = $context . '[' . current_time( 'mysql', 1 ) . ' - ' . $_SERVER['REMOTE_ADDR'] . '] ' . $message;

			if ( $backtrace ) {
				ob_start();
				debug_print_backtrace();
				$trace = ob_get_contents();
				ob_end_clean();

				$error_str .= "\n\n" . $trace . "\n\n";
			}

			if ( is_writable( $this->_file ) ) {
				error_log( $error_str . "\n", 3, $this->_file );
			} else {
				error_log( $error_str );
			}
		}

	}
endif;