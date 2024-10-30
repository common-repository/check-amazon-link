<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
} // Exit if accessed directly

if ( ! class_exists( 'AZLC_semaphore' ) ) :
	class AZLC_semaphore {

		public static $min_sleep_time = 5;

		public static function set_min_sleep_time( $x ) {
			$x_safe               = filter_var( $x, FILTER_SANITIZE_NUMBER_INT );
			self::$min_sleep_time = $x_safe;
			global $azlc_logger;
			$azlc_logger->write( "semaphore sleep time set to " . $x );
		}

		public static function not_locked() {
			global $azlc_logger;
			$lock_options = get_option( 'azlc_locked_options' );
			$azlc_logger->write( "not_locked called" );
			if ( $lock_options['locked'] == 0 ) { // no worker working right now
				$x = time() - $lock_options['time_locked'];
				$azlc_logger->write( "this time difference is " . $x );
				if ( time() - $lock_options['time_locked'] < self::$min_sleep_time ) {
					// it hasn't been long enough, we need to not overload server
					$azlc_logger->write( "we are keeping it locked because of the timeframe" );

					return false;
				} else {
					$azlc_logger->write( "it is unlocked AND it has been long enough" );

					return true;
				}
			} else {
				if ( isset( $lock_options['time_locked'] ) ) {

					$azlc_logger->write( "it was locked at " . $lock_options['time_locked'] . " and now it is " . time() );
					if ( time() - $lock_options['time_locked'] > self::$min_sleep_time + 2 ) {
						// with default options, this says if it was locked more than 32 seconds ago, do this
						// it was locked so long ago that it is stuck locked, so we will unlock it
						$azlc_logger->write( "it was locked long enough ago, so we are updating the option" );
						AZLC_semaphore::unlock();

						return true;
					} else {
						$azlc_logger->write( "it hasnt been long enough, so it needs to stay locked" );

						return false;
					}
				} else {
					// time_locked is not set, this scenario should not happen, but incase it does...
					$azlc_logger->write( "time locked has not been set" );
					AZLC_semaphore::unlock();

					return true;
				}
			}
		}

		public static function lock() {
			global $azlc_logger;
			$azlc_logger->write( "locking..." );
			$options                = get_option( 'azlc_locked_options' );
			$options['time_locked'] = time();
			$options['locked']      = 1;
			wp_cache_delete( 'alloptions', 'options' );
			update_option( 'azlc_locked_options', $options );
		}

		public static function unlock() {
			global $azlc_logger;
			$azlc_logger->write( "unlocking..." );
			$options           = get_option( 'azlc_locked_options' );
			$options['locked'] = 0;
			wp_cache_delete( 'alloptions', 'options' );
			update_option( 'azlc_locked_options', $options );
		}

	}
endif;