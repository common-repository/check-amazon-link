<?php
// Quality Control Module
// Author: LinSoftware.com
if ( ! class_exists( 'LinSoftware_QC' ) ) :
	class LinSoftware_QC {
		public static $plugin_name;

		function __construct($name) {
			self::$plugin_name = $name;
			add_action( 'wp_ajax_linsoftware_qc',array($this, 'ls_ajax') );
			add_action( 'admin_enqueue_scripts', array($this, 'enq_scripts') );
			add_action( 'admin_head', array($this, 'enq_css') );
			add_option('linsw_qc', array(
				'enabled' => 1, //boolean, whether to show quality control box
				'first' => 1, // by default, box will be shown first time
				'interval' => 1,
				'accum' => 0, // accumulator, change every time there is an ajax request
				'id' => uniqid()
			));
		}

		public static function get_site_id() {
			$opts = get_option('linsw_qc');
			if(isset($opts['id'])) {
				return $opts['id'];
			} else {
				return 0;
			}
		}

		public static function enq_css() {
			?>

			<style>
				.lsqc_inner {
					border: 5px solid #0088cc;
					padding: 10px;
					width: 300px;
				}

				.lsqc_inner button {
					margin-left: 10px;
					margin-right: 10px;
					margin-bottom: 20px;
				}

			</style>

			<?php

		}


		public static function record_response($response) {
			$url        = "http://www.linsoftware.com/svc/qc.php";
			$res        = wp_remote_post( $url, array(
					'method'      => 'POST',
					'timeout'     => 45,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
					'headers'     => array(),
					'body'        => array( 'id' => self::get_site_id(), 'ms' => is_multisite(), 'rs' => $response, 'sc' =>
						self::$plugin_name ),
					'cookies'     => array()
				)
			);
		}

		public static function enq_scripts() {
			wp_register_script( 'linsoftware_qc', plugins_url( 'linsqc.js', __FILE__ ), array( 'jquery' ), '1' );
			wp_enqueue_script( 'linsoftware_qc' );
		}

		public static function ls_ajax() {
			$stage = filter_var($_POST['lsw_stage'], FILTER_SANITIZE_STRING);
			self::accumlator();
			switch($stage) {
				case 'initial':
					self::show();
					break;
				case 'respond_no':
					self::record_response('no');
					self::doOnNegativeResponse();
					break;
				case 'respond_yes':
					self::record_response('yes');
					self::disable();
					self::doOnPositiveResponse();
					break;
				case 'respond_maybe':
					self::setShowInterval(5);
					self::record_response('maybe');
					self::doOnNeutralResponse();
					break;
				case 'submit_report':
					self::disable();
					if(isset($_POST['lsw_anon'])) {
						$safe_anon = filter_var($_POST['lsw_anon'], FILTER_SANITIZE_NUMBER_INT);
					} else {
						$safe_anon =  0;
					}
					self::sendBugReport($safe_anon);
					self::doOnReportSent();
					break;
			}
		}

		public static function javascript() {
			?>

			<?php
		}


		public static function setFirstShowInterval($int) {
			$opt = get_option('linsw_qc');
			$opt['first'] = $int;
			update_option('linsw_qc', $opt);
		}

		public static function setShowInterval($int) {
			$opt = get_option('linsw_qc');
			$opt['interval'] = $int;
			update_option('linsw_qc', $opt);
		}

		public static function sendBugReport($anonymous) {
			$file = plugin_dir_path( __FILE__ ) . 'debug_log.txt';

			ob_start();
			echo "Type of Report: ";
			if($anonymous) echo "Anonymous";
			else echo "Complete";
			echo PHP_EOL;
			echo "Wordpress Version: " . get_bloginfo('version') . PHP_EOL;
			echo "Multisite: ";
			echo is_multisite() ? 'Yes' : 'No';
			echo PHP_EOL;
			echo "Check Amazon Links Version:" . get_option('azlc_version', '0') . PHP_EOL;
			echo "Check Amazon Links Database Version:" . get_option('azlc_db_version', '0') . PHP_EOL;

			var_dump( get_option('azlc_plugin_options_internal', 0));

			$opts = get_option('azlc_plugin_options', 0);  // we don't dump this var because it contains sensitive info
			// print options that aren't sensitive info...
			if(isset($opts['region'])) {
				echo "Region: " . $opts['region'] . PHP_EOL;
			}
			if(isset($opts['send_email'])) {
				echo "send_email: " . $opts['send_email'] . PHP_EOL;
			}
			if(isset($opts['min_sleep_time'])) {
				echo "min_sleep_time: " . $opts['min_sleep_time'] . PHP_EOL;
			}
			if(isset($opts['ajax_sleep_time_parsing'])) {
				echo 'ajax_sleep_time_parsing: '. $opts['ajax_sleep_time_parsing'] . PHP_EOL;
			}
			if(isset($opts['background_admin'])) {
				echo 'background_admin: '. $opts['background_admin'] . PHP_EOL;
			}
			if(isset($opts['background_front'])) {
				echo 'background_front: ' . $opts['background_front'] . PHP_EOL;
			}
			if(isset($opts['truncate'])) {
				echo 'truncate: ' . $opts['truncate'] . PHP_EOL;
			}
			if(isset($opts['reset'])) {
				echo 'reset: ' . $opts['reset'] . PHP_EOL;
			}
			if(isset($opts['debug'])) {
				echo 'debug: ' . $opts['debug'] . PHP_EOL;
			}

			var_dump( get_option('azlc_already_installed', 0));


			// get some sample rows from the database to help debug

			require_once('AmazonLinkCheckerDatabase.php');
			/* @var $wpdb WPDB */
			global $wpdb;
			$database = new AmazonLinkCheckerDatabase();
			$res = $wpdb->get_results("SELECT * FROM " . $database->product_data_table . " LIMIT 10", ARRAY_A);
			echo "product data table sample rows" . PHP_EOL;
			var_dump($res);
			echo PHP_EOL;

			$res = $wpdb->get_results("SELECT * FROM " . $database->link_instances_table . " LIMIT 10", ARRAY_A);
			echo "link instances sample rows" . PHP_EOL;
			var_dump($res);
			echo PHP_EOL;

			$res = $wpdb->get_results("SELECT * FROM " . $database->product_table . " LIMIT 10", ARRAY_A);
			echo "product table sample rows" . PHP_EOL;
			var_dump($res);
			echo PHP_EOL;

			if(!$anonymous) {
				echo "Site URL: ";
				if ( is_multisite() ) {
					echo network_site_url();
				} else {
					echo site_url();
				}
				echo PHP_EOL;

				echo "Home URL: ";
				if ( is_multisite() ) {
					echo network_home_url();
				} else {
					echo home_url();
				}
				echo PHP_EOL;

				echo "Admin Email: ";
				if ( is_multisite() ) {
					echo get_site_option( 'admin_email' );
				} else {
					echo get_option( 'admin_email' );
				}
				echo PHP_EOL;
			}
			// end NON-anonymous section
			$plugins = get_plugins();
			foreach ( $plugins as $plugin ) {
				var_dump( $plugin );
			}

			echo "Current Theme Info:" . PHP_EOL;
			var_dump(wp_get_theme());

			echo "Browser Info:" . PHP_EOL;
			echo filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING) . PHP_EOL;
			$browser = get_browser(null, true);
			print_r($browser);

			$buffer = ob_get_contents();

			ob_end_clean();

			$buffer = strip_tags($buffer);
			file_put_contents($file, $buffer, FILE_APPEND | LOCK_EX);

			$subject = 'Check Amazon Links Debug Report';
			$headers = 'From: Linnea <admin@linsoftware.com>' . "\r\n";
			$attachments = array( $file );
			wp_mail( 'linnea.wilhelm@gmail.com', $subject, 'plugin not working - see attachment', $headers,
				$attachments );

		}

		public static function doOnPositiveResponse() {
			$html = self::topOfBox() . "<h2>Great to hear!  When you have a moment, please <A href='https://wordpress.org/support/view/plugin-reviews/check-amazon-link'>write a review.</A>
			</h2>" . self::bottomOfBox();
			$response = array('html'=>$html);
			self::closeAjax($response);
		}

		public static function doOnNegativeResponse() {
			$html = self::topOfBox() . "<h2>Sorry about the problem. Would you like to send an
		error report?</h2>

		<button id='lsqc_r'>Send Error Report</button><br>
		<label><input type='checkbox' id ='ls_anon' name='anonymous' value='anonymous'>Don't include my site url or
		email address.</label>
		<p>Error reports help us make improvements.  They include diagnostic information including your Wordpress
		version, active
		plugins, active theme, browser info, error
		messages, your
		website URL, and email address for follow-up questions. You can admit these last two items by checking the
		above box, but it will make it harder for us to diagnose the issue.</p>
		" . self::bottomOfBox();
			$response = array('html'=>$html);
			self::closeAjax($response);
		}

		public static function doOnNeutralResponse() {
			$html = self::topOfBox() . "<P>OK, we'll ask you again after you've had more time to try this plugin.</p>
		". self::bottomOfBox();
			$response = array('html'=>$html);
			self::closeAjax($response);
		}


		public static function doOnReportSent() {
			$html = self::topOfBox() . "<P>Your report has been submitted. Thank you!</p>" . self::bottomOfBox();
			$response = array('html'=>$html);
			self::closeAjax($response);
		}

		public static function bottomOfBox() {
			return "
		<P id='linqc_close'><a href='#'>Close</a></P></div>
		";
		}

		public static function topOfBox() {
			return "<div class='lsqc_inner'>";
		}

		public static function closeAjax($dataToSend) {
			echo json_encode($dataToSend);
			wp_die();
		}


		public static function accumlator() {
			$opt = get_option('linsw_qc');
			$opt['accum']++;
			update_option('linsw_qc', $opt);
		}

		public static function disable() {
			// call this function
			// after user has submitted an error report, or told me that its working
			// because they don't need to keep seeing it!
			$opt = get_option('linsw_qc');
			$opt['enabled'] = 0;
			update_option('linsw_qc', $opt);

		}

		public static function show() {
			$opt = get_option('linsw_qc');
			// do not show for these reasons:
			if($opt['enabled']==0 ||    //disabled
			   $opt['accum']<$opt['first'] ||   // accumulator is less than minimum amount
			   ( $opt['accum']>=$opt['first']  && $opt['accum'] % $opt['interval']!==0) ) // interval
			{
				$html = '';
				$response = array('html'=>$html);
				self::closeAjax($response);
			} else { // show box
				$html =  self::topOfBox() . "<h2>Is this plugin working for you?</h2>
					<button id='lsqc_1'>Yes</button>
					<button id='lsqc_0'>No</button>
					<button id='lsqc_3'>Not Sure</button>" .  self::bottomOfBox();
				$response = array('html'=>$html);
				self::closeAjax($response);
			}
		}
	}

	$linsoft_qc = new LinSoftware_QC('Check_Amazon_Links');

endif;