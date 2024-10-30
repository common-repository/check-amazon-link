<?php
/*
 * Plugin Name: Check Amazon Links
 * Plugin URI: http://www.linsoftware.com/amazon-link-checker/
 * Description: Checks Amazon links to see if products are in stock.  Displays a table of all your Amazon links.  Notifies you by email about out-of-stock products.
 * Version: 1.2.0
 * Author: Linnea Wilhelm, Lin Software
 * Author URI: http://www.linsoftware.com
 */

/*
 * Requires Wordpress Version 2.7 for Settings API
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit();
} // Exit if accessed directly

require_once('linsoftware_quality_control.php');
require_once( 'AmazonLinkCheckerDatabase.php' );
require_once( 'AzWorker.php' );
require_once( 'AZLC_Logger.php' );
require_once( 'AZLC_HTMLParser.php' );
require_once( 'AZLC_LinkInstance.php' );
require_once( 'AZLC_Utility.php' );
require_once( 'AZLC_semaphore.php' );

define( 'AZLC_PLUGIN_NAME', "Check Amazon Links" );
define( 'AZLC_PLUGIN_TOOLS_PAGE_NAME', "Check Amazon Links Tools" );
define( 'AZLC_PLUGIN_SETTINGS_PAGE_NAME', "Check Amazon Links Settings" );
define( 'AZLC_MENU_SLUG_FOR_TOOLS', "amazon_link_checker_menu_tools" );
define( 'AZLC_MENU_SLUG_FOR_SETTINGS', "amazon_link_checker_menu_settings" );

// keep track of plugin version
define( 'AZLC_VERSION_NUM', '1.2.0' );
// add_option only adds the option if it does not already exist
// when updating, run update code and then update_option
add_option( 'azlc_version', AZLC_VERSION_NUM );

// keep track of database version, only change this if database structure changes
define( 'AZLC_DB_VERSION_NUM', '1.1' );
// add_option only adds the option if it does not already exist
// when updating, run update code and then update_option
add_option( 'azlc_db_version', AZLC_DB_VERSION_NUM );

// Initialize Logger
/** @var $azlc_logger AZLC_Logger */
global $azlc_logger;
$azlc_logger = new AZLC_Logger();

// Initialize Database
/** @var $azlc_database AmazonLinkCheckerDatabase */
global $azlc_database;
$azlc_database = new    AmazonLinkCheckerDatabase;


// code to run on update
if(AZLC_VERSION_NUM != get_option('azlc_version')) {
	update_option('azlc_version', AZLC_VERSION_NUM);
}

// more code to run on update
if(AZLC_DB_VERSION_NUM != get_option('azlc_db_version')) {
	//add indexes
	$azlc_database->addIndexes();
	update_option('azlc_db_version', AZLC_DB_VERSION_NUM);
}



add_action( 'wp_enqueue_scripts', 'azlc_js_enqueue_front_end' );
add_action( 'admin_enqueue_scripts', 'azlc_js_enqueue_back_end' );
add_action( 'admin_enqueue_scripts', 'azlc_css_enqueue_back_end' );

if(is_multisite()) {
	add_action('plugins_loaded', 'azlc_multisite');
	add_action('admin_init', array('AmazonLinkCheckerCore', 'multisite_check_activated'));
}


function azlc_multisite() {
	global $wpdb;

	// check for flag
	$blog_id = get_site_option('azlc_update_from');
	if($blog_id) {
		// switch to the blog to copy the actions from
		switch_to_blog($blog_id);
		$options = get_option('azlc_plugin_options');
		// copy settings to all sites
		AmazonLinkCheckerCore::copy_settings_from($blog_id, 'azlc_plugin_options', $options );
		// remove flag
		delete_site_option('azlc_update_from');
		// switch back
		restore_current_blog();
	}
}

function azlc_css_enqueue_back_end() {
	wp_register_style( 'amazonlinkcheckercss', plugin_dir_url( __FILE__ ) . '/css/amazonlinkchecker.css', false, '1.0.0' );
	wp_enqueue_style( 'amazonlinkcheckercss' );
	do_action('azlc_css_backend');
}

function azlc_js_enqueue_front_end() {
	// if option is not set, the default is 1 (turned on)
	$options = get_option( 'azlc_plugin_options', array( 'background_front' => 1 ) );
	if(! isset($options['background_front'])) {
		$options['background_front'] = 1;
	}
	if ( $options['background_front'] == 1 ) {
		wp_enqueue_script( 'azlc_bg', plugins_url( 'js/azlc_bg.js', __FILE__ ), array( 'jquery' ), 2, false );
		do_action('azlc_js_frontend');
	}
}

function azlc_js_enqueue_back_end() {
	// if option is not set, the default is 1 (turned on)
	$options = get_option( 'azlc_plugin_options', array( 'background_admin' => 1 ) );
	if(! isset($options['background_admin'])) {
		$options['background_admin'] = 1;
	}
	if ( $options['background_admin'] == 1 ) {
		wp_enqueue_script( 'azlc_bg', plugins_url( 'js/azlc_bg.js', __FILE__ ), array( 'jquery' ), 2, false );
		do_action('azlc_js_backend');
	}
}

register_deactivation_hook( __FILE__, array( 'AmazonLinkCheckerCore', 'deactivate' ) );
register_activation_hook( __FILE__, array( 'AmazonLinkCheckerCore', 'activate' ) );
add_action( 'publish_post', array( 'AmazonLinkCheckerCore', 'schedule_parse_post' ) );
add_action( 'publish_page', array( 'AmazonLinkCheckerCore', 'schedule_parse_post' ) );
add_action( 'delete_post', array( 'AmazonLinkCheckerCore', 'handle_post_deletion' ) );
add_action( 'admin_menu', array( 'AmazonLinkCheckerCore', 'my_plugin_menus' ) );
add_action( 'admin_init', array( 'AmazonLinkCheckerCore', 'plugin_admin_init' ) );
add_action( 'admin_init', array( 'AmazonLinkCheckerCore', 'redirect_about_page' ), 1 );
add_action( 'wp_head', 'azlc_head_js' );



function azlc_head_js() {
	?>
	<script type="text/javascript">
		if (typeof ajaxurl === 'undefined') {
			var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
		}
		<?php global $wp_query;
				$post_id = $wp_query->post->ID;
			echo "var post_id = '" . $post_id . "';"
				?>

	</script>
	<?php
}


// callback for ajax to reload the link instances table
add_action( 'wp_ajax_azlc_linksTable', 'azlc_linksTable_callback' );
//callback for ajax to look up links & products in the background
add_action( 'wp_ajax_azlc_ajax_controller', 'azlc_ajax_controller' );
add_action( 'wp_ajax_nopriv_azlc_ajax_controller', 'azlc_ajax_controller' );

//callback for ajax to update options on all sites
if(is_multisite()) {
	add_action('wp_ajax_azlc_ms', 'azlc_ms');
}

function azlc_ms() {
	add_site_option( 'azlc_update_from', get_current_blog_id() );
}

/**
 *  Function used by wp_cron
 * @return void
 */
function azlc_housework() {
	global $azlc_logger;

	// clear this option, it's a history of post ids recently looked up
	update_option('azlc_pids_h', array());

	// add asins not looked up in the last 7 days to the priority queue!
	// this is to solve the problem of always looking up the same asins over and over
	$asins = AzWorker::get_asins_not_looked_up(30, 7);
	$priority_queue = get_option('azlc_prq', array());
	$new_priority_queue = array_merge($priority_queue, $asins);
	update_option('azlc_prq', $new_priority_queue);

	// if debug log is over 1MB, truncate it:
	$file = plugin_dir_path( __FILE__ ) .'debug_log.txt';
	if(filesize($file) > 1000000) {
		$handle = fopen($file, 'w');
		fclose($handle);

		$azlc_logger->write("File Truncated");
	}

	// cleanup products table
	// delete rows where the title is blank
	// this happens occasionally
	// todo: fix this so that we don't have to clean it up!
	/* @var $wpdb WPDB */
	global $azlc_database, $wpdb;
	$res = $wpdb->delete($azlc_database->product_table, array('title'=>''));
	$azlc_logger->write("azlc_housework: number of products deleted: " . $res);

	// truncate products data table according to the option chosen

	$options = get_option('azlc_plugin_options');
	if(isset($options['truncate']) && $options['truncate'] > 0) {
		$truncate =  filter_var( $options['truncate'], FILTER_SANITIZE_NUMBER_INT );
		$query = "DELETE FROM " . $azlc_database->product_data_table . " WHERE time_of_retrieval < DATE_SUB(NOW(), INTERVAL " . $truncate . " DAY)";
		$result = $wpdb->query($query);
		if(false === $result) {
			$azlc_logger->write("azlc_housework: ERROR truncating product_data_table" );
			} else {
			$azlc_logger->write("azlc_housework: successfully truncated product_data_table, deleted this many rows: " . $result );
			}
	}

}

/**
 * Function used by ajax call
 * @return void
 */
function azlc_ajax_controller() {
	global $azlc_logger;
	$options       = AmazonLinkCheckerCore::load_options();
	$internal_opts = get_option( 'azlc_plugin_options_internal' );

	$azworker      = new AzWorker( $options );

	if(isset($options['reset'])) {
		if($options['reset']==1) {
			$azworker->reset();
		}
	}


	$post_ids      = get_posts_to_parse( $azworker );

	// $_POST['azlc_pid'] will normally be set when ajax call is from a front end page
	// it will contain the post id of the page that the ajax call comes from
	// this plugin is designed to optimize amazon look-ups
	// so that recently viewed pages are looked up first
	// hopefully this means that popular pages will always have their amazon links recently checked

	if(isset($_POST['azlc_pid'])) {
		$pid_safe = filter_var( $_POST['azlc_pid'], FILTER_SANITIZE_NUMBER_INT );
		$pids = get_option('azlc_pids', array());
		// this option keeps a 24 hour history to avoid parsing the same posts over and over
		// this option is cleared by cron
		$pids_history = get_option('azlc_pids_h', array());

		// add the post_id to the array only if it's not in it
		if(array_search($pid_safe, $pids)===false && array_search($pid_safe, $pids_history)===false) {
			array_push( $pids, $pid_safe );
			array_push($pids_history,$pid_safe);
			update_option( 'azlc_pids', $pids );
			update_option('azlc_pids_h', $pids_history);
		}
	}
	if ( $internal_opts['az_ok'] == 0 ) {
		// test amazon credentials
		if ( $azworker->AZcredentials_are_working() ) {
			$azworker->finish();
			$internal_opts['az_ok'] = 1;
			update_option( 'azlc_plugin_options_internal', $internal_opts );
			$x['interval'] = $options['ajax_sleep_time_parsing'];
			$x['success']  = 1;
			$x['az']       = 1;
			echo json_encode( $x );
			wp_die();
		} else {
			$azworker->finish();
			$x['error']   = 'Exception';
			$x['success'] = 0;
			$x['az']      = 0;
			echo json_encode( $x );
			wp_die();
		}
	}
	if ( empty( $post_ids ) ) {
		AZLC_semaphore::set_min_sleep_time( $options['min_sleep_time'] );
	} else {
		AZLC_semaphore::set_min_sleep_time( $options['ajax_sleep_time_parsing'] );
	}

	try {
		$azworker->prepareForWork();
	} catch ( Exception $ex ) {
		$azlc_logger->write( "in ajax_controller and got an exception so dying.." );
		$x['error']   = 'Exception';
		$x['success'] = 0;
		echo json_encode( $x );
		wp_die();
	}

	$x = array();

	if ( empty( $post_ids ) ) {
		// this indicates that all posts are parsed,
		// so, we should look up a product
		$azworker->lookup_next_asin();
		//todo: remove this
		$x['asin'] = 'multiple';
		$x['interval'] = $options['min_sleep_time'];
	} else {
		// parse a post
		$azworker->parse_single_post( $post_ids[0] );
		$x['interval'] = $options['ajax_sleep_time_parsing'];

		//also, begin to look up the products
		if(count($post_ids) % 3 === 0 ) {
		//do this approx. every 3rd time
		$azworker->lookup_next_asin();
		}

	}

	$azworker->finish();
	$x['success'] = 1;
	echo json_encode( $x );
	wp_die();
}



/**
 * returns an empty array if there are no posts waiting to be parsed
 * otherwise, returns an array of post ids
 * this also update the internal option pages_parsed
 *
 * @param $azworker AzWorker
 *
 * @return array
 */
function get_posts_to_parse( $azworker ) {
	global $azlc_logger;
	$internal_options_array = get_option( 'azlc_plugin_options_internal' );
	if ( $internal_options_array['pages_parsed'] == 1 ) {
		return array();
	} else {
		// get list of post IDs and see if they have already been parsed
		$args = array(
			'posts_per_page'   => - 1,
			'offset'           => 0,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'ID',
			'order'            => 'DESC',
			'include'          => '',
			'exclude'          => '',
			'meta_key'         => '',
			'meta_value'       => '',
			'post_type'        => 'any',
			'post_mime_type'   => '',
			'post_parent'      => '',
			'post_status'      => 'publish',
			'suppress_filters' => true
		);

		$posts_array = get_posts( $args );
		$ids         = array();

		foreach ( $posts_array as $p ) {
			if ( $azworker->already_parse( $p->ID ) ) {
				continue;
			}
			$ids[] = $p->ID;
		}


		if ( empty( $ids ) ) {
			// there are no posts to parse,
			// set internal option value
			$azlc_logger->write( "in get_posts_to_parse and updating the internal pages_parsed option to 1" );
			$internal_options_array['pages_parsed'] = 1;
			update_option( 'azlc_plugin_options_internal', $internal_options_array );
			return array();
		} else {
			$azlc_logger->write( "in get_posts_to_parse and returning an array of posts to parse" );
			return $ids;
		}

	}
}


function azlc_linksTable_callback() {

	if ( isset( $_POST['azlc_linksTableOptions']['num_of_rows'] ) ) {
		$num_of_rows_safe = filter_var( $_POST['azlc_linksTableOptions']['num_of_rows'], FILTER_SANITIZE_NUMBER_INT );
	} else {
		$num_of_rows_safe = null;
	}
	if ( isset( $_POST['azlc_linksTableOptions']['page_num'] ) ) {
		$page_num_safe = filter_var( $_POST['azlc_linksTableOptions']['page_num'], FILTER_SANITIZE_NUMBER_INT );
	} else {
		$page_num_safe = 1;
	}

	if ( isset( $_POST['azlc_linksTableOptions']['first_sort_column'] ) ) {
		$first_sort_column_safe = filter_var( $_POST['azlc_linksTableOptions']['first_sort_column'], FILTER_SANITIZE_STRING );
	} else {
		$first_sort_column_safe = null;
	}


	if ( isset( $_POST['azlc_linksTableOptions']['first_sort_column'] ) ) {
		$first_sort_order_safe = filter_var( $_POST['azlc_linksTableOptions']['first_sort_order'], FILTER_SANITIZE_STRING );
	} else {
		$first_sort_order_safe = null;
	}

	if ( isset( $_POST['azlc_linksTableOptions']['second_sort_column'] ) ) {
		$second_sort_column_safe = filter_var( $_POST['azlc_linksTableOptions']['second_sort_column'], FILTER_SANITIZE_STRING );
	} else {
		$second_sort_column_safe = null;
	}


	if ( isset( $_POST['azlc_linksTableOptions']['second_sort_order'] ) ) {
		$second_sort_order_safe = filter_var( $_POST['azlc_linksTableOptions']['second_sort_order'], FILTER_SANITIZE_STRING );
	} else {
		$second_sort_order_safe = null;
	}


	$offset = ( $page_num_safe - 1 ) * $num_of_rows_safe;

	// this prints the links table
	AZLC_Utility::load_sorted_links_table( $num_of_rows_safe, $offset, $first_sort_column_safe, $first_sort_order_safe, $second_sort_column_safe, $second_sort_order_safe );


	wp_die(); // this is required to terminate immediately and return a proper response
}


// tell wp_cron to send a daily email
if ( ! wp_next_scheduled( 'azlc_send_email' ) ) {
	wp_schedule_event( time(), 'daily', 'azlc_send_email' );
}

// tell wp_cron to do hourly housework
if ( ! wp_next_scheduled( 'azlc_wk' ) ) {
	wp_schedule_event( time(), 'daily', 'azlc_wk' );
}

add_action('azlc_wk', 'azlc_housework');
add_action( 'azlc_send_email', array( 'AmazonLinkCheckerCore', 'send_email' ) );

if ( ! class_exists( 'AmazonLinkCheckerCore' ) ) :
class AmazonLinkCheckerCore {

	// new to version 1.0.3
	public static function multisite_check_activated() {
		global $wpdb;
		$activated = get_site_option('azlc_multisite_activated');
		if($activated == 'false') {
			return false;
		} else {
			$sql = "SELECT blog_id FROM $wpdb->blogs";
			$blog_ids = $wpdb->get_col($sql);
			foreach($blog_ids as $blog_id) {
				if(!in_array($blog_id, $activated)) {
					switch_to_blog($blog_id);
					AmazonLinkCheckerCore::implement_activation();
					$activated[] = $blog_id;
				}
			}
			restore_current_blog();
			update_site_option('azlc_multisite_activated', $activated);
		}
	}


	public static function copy_settings_from($source_id, $option_name, $option_value) {
				global $wpdb;
				$sql = "SELECT blog_id FROM $wpdb->blogs";
				$blog_ids = $wpdb->get_col($sql);
				foreach($blog_ids as $blog_id) {
					// update option on other blogs
					if($blog_id!=$source_id) {
						switch_to_blog($blog_id);
						update_option($option_name, $option_value);
					}
				}
				restore_current_blog();
	}

	// new to version 1.0.1
	public static function mailing_list( $option, $value) {
		global $azlc_logger;
		if(strcmp($option, 'azlc_plugin_options')===0) {
		$azlc_logger->write("in MAILING LIST for azlc_options");
		if(isset($value['mailing_list'])) {
			// user has opted into mailing list
			$email_safe = urlencode($value['email']);
			$url = "http://www.linsoftware.com/svc/azlc_email.php";
			$res = wp_remote_post( $url, array(
	'method' => 'POST',
	'timeout' => 45,
	'redirection' => 5,
	'httpversion' => '1.0',
	'blocking' => true,
	'headers' => array(),
	'body' => array( 'e' => $email_safe, 's' => 'amazonlinkchecker'),
	'cookies' => array()
    )
);
		}
		}
	}






	public static function send_email() {
		global $azlc_logger;
		$azlc_logger->write( "in send email function" );
		$site_url   = get_option( 'siteurl', '' );
		$options    = AmazonLinkCheckerCore::load_options();
		$send_email = isset( $options['email_option'] ) ? $options['email_option'] : 1;
		if ( $send_email == 0 ) {
			// user has opted out of emails
			$azlc_logger->write( "user has opted out of emails" );
			return;
		} else {
			// get out of stock items found in last day
			$results = AmazonLinkCheckerCore::get_products_out_of_stock();
			if ( ! empty( $results ) ) {
				$azlc_logger->write( "send_email function found "  . count( $results )  . " out of stock items");
				$body = 'The Wordpress Plugin, Check Amazon Links, found ' . count( $results ) . ' links to out of
				stock products in the past day.  Here is a list of them: <p>';


				foreach ( $results as $r ) {
					$url_safe = esc_url("http://www.amazon." . $r['region'] . "/dp/" . $r['asin']);
					$body .= "Product Name: <A href='" . $url_safe . "'>" . esc_html($r['title']) . "</a><br>";
					$url_safe =  esc_url($site_url . "?p=" . $r['post_id']);
					$body .= "Linked from This Post: <a href='" . $url_safe . "'>" . esc_html($r['post_title']) . "</a><br><br>";
				}

				$body .= "<p>More details can be found at the
<a href='" . $site_url . "/wp-admin/tools.php?page=amazon_link_checker_menu_tools'>Check Amazon Links Tools
				page.</a>
This is an automated email.  You can turn off this email on the <a href='" . $site_url . "/wp-admin/options-general.php?page=amazon_link_checker_menu_settings'>
Check Amazon Links settings page.</a></p>";
				$internal_options_array     = get_option( 'azlc_plugin_options_internal' );
				$body .= $internal_options_array['email_footer'];
				// send email
				self::send_html_email( $options['email'], 'Out of Stock Amazon Products Detected', $body );

			} else {
				$azlc_logger->write( "send_email function found NO out of stock items");
			}
		}
	}


	function send_html_email( $email_address, $subject, $body ) {
		//Need to override the default 'text/plain' content type to send a HTML email.
		add_filter( 'wp_mail_content_type', array('AmazonLinkCheckerCore', 'set_html_content_type') );

		//Let auto-responders and similar software know this is an auto-generated email
		//that they shouldn't respond to.
		$headers = array( 'Auto-Submitted: auto-generated' );

		$success = wp_mail( $email_address, $subject, $body, $headers );

		//Remove the override so that it doesn't interfere with other plugins that might
		//want to send normal plaintext emails.
		remove_filter( 'wp_mail_content_type',  array('AmazonLinkCheckerCore', 'set_html_content_type')   );

		return $success;
	}


	function set_html_content_type() {
		return 'text/html';
	}



	/**
	 * gets products that were detected out of stock within the past day
	 * @return array
	 */
	public static function get_products_out_of_stock() {
		/* @var $wpdb WPDB */
		global $wpdb, $azlc_database;
		$query = <<<HEREDOC
SELECT pd.time_of_retrieval AS time, li.id, li.asin AS asin, li.post_id AS
post_id, li.affiliate_tag AS affiliate_tag, li.region AS region, title,
li.post_title AS post_title FROM {$azlc_database->product_data_table}  pd
JOIN {$azlc_database->link_instances_table} li ON
pd.asin = li.asin
 JOIN {$azlc_database->product_table} pr ON
pr.asin = pd.asin
 WHERE stock_status LIKE 'Out of Stock' AND time_of_retrieval >= DATE_SUB(NOW(), INTERVAL 1 DAY)  GROUP BY li.id
HEREDOC;
		return $wpdb->get_results( $query, ARRAY_A );
	}

	// this function sends user to the settings page after initial plugin activation
	public static function redirect_about_page() {
		// only do this if the user can activate plugins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// don't do anything if the transient isn't set
		if ( ! get_transient( 'azlc_about_page_activated' ) ) {
			return;
		}

		delete_transient( 'azlc_about_page_activated' );
		wp_safe_redirect( admin_url( 'options-general.php?page=amazon_link_checker_menu_settings' ) );
		exit;
	}

	public static function activate_about_page() {
		set_transient( 'azlc_about_page_activated', 1, 30 );
	}

	/**
	 * @param $post_ID
	 */
	public static function schedule_parse_post( $post_ID ) {

		// santize post_id
		$post_ID_safe = filter_var( $post_ID, FILTER_SANITIZE_NUMBER_INT );

		global $azlc_logger;

		$azlc_logger->write( "in schedule_parse_post and the post id is: " . $post_ID_safe );


		$internal_options_array                 = get_option( 'azlc_plugin_options_internal' );
		$internal_options_array['pages_parsed'] = 0;
		$internal_options_array['pages_parsed'] = 0;
		update_option( "azlc_plugin_options_internal", $internal_options_array );

		// remove entry from post status table
		// this is necessary to get this post to be parsed again
		// since this function is called when posts are edited in addition to being newly published
		/* @var $wpdb WPDB */
		global $wpdb, $azlc_database;
		$wpdb->delete( $azlc_database->post_status_table, array( 'post_id' => $post_ID_safe ) );
	}

	public static function handle_post_deletion( $post_ID ) {
		// santize post_id
		$post_ID_safe = filter_var( $post_ID, FILTER_SANITIZE_NUMBER_INT );

		global $azlc_database, $azlc_logger;

		$azlc_logger->write( "handling post deletion for post " . $post_ID_safe );

		$azlc_database->deleteLinkInstances( $post_ID_safe );
	}


	public static function plugin_admin_init() {
		register_setting( 'amazon_link_plugin_options', 'azlc_plugin_options' );
		add_settings_section( 'plugin_main', '', array(
			'AmazonLinkCheckerOptionsPage',
			'plugin_section_text'
		), 'main_options' );
		add_settings_field( 'associateTag', 'Associate Tag', array(
			'AmazonLinkCheckerOptionsPage',
			'associate_tag_string'
		), 'main_options', 'plugin_main' );
		add_settings_field( 'AWSAccessKeyId', 'AWS Access Key ID', array(
			'AmazonLinkCheckerOptionsPage',
			'access_key_string'
		), 'main_options', 'plugin_main' );
		add_settings_field( 'AWSSecretKey', 'AWS Secret Key', array(
			'AmazonLinkCheckerOptionsPage',
			'secret_key_string'
		), 'main_options', 'plugin_main' );

		add_settings_field( 'default_region', 'Default Region', array(
			'AmazonLinkCheckerOptionsPage',
			'default_region'
		), 'main_options', 'plugin_main' );

		add_settings_field( 'email_option', 'Email Notification', array(
			'AmazonLinkCheckerOptionsPage',
			'email_option'
		), 'main_options', 'plugin_main' );

		add_settings_field( 'email_address', 'Email address', array(
			'AmazonLinkCheckerOptionsPage',
			'email_address'
		), 'main_options', 'plugin_main' );

		add_settings_field( 'mailing_list_option', 'Mailing List', array(
			'AmazonLinkCheckerOptionsPage',
			'mailing_list_option'
		), 'main_options', 'plugin_main' );

		add_settings_section( 'azlc_additional_opts', '', array(
			'AmazonLinkCheckerOptionsPage',
			'additional_options'
		), 'main_options' );
		add_settings_field( 'sleep_time', 'Minimum Sleep Time Between Amazon Requests', array(
			'AmazonLinkCheckerOptionsPage',
			'min_sleep_time'
		), 'main_options', 'azlc_additional_opts' );
		add_settings_field( 'ajax_min_sleep_time_for_parsing', 'Minimum Sleep Time Between Ajax Requests', array(
			'AmazonLinkCheckerOptionsPage',
			'ajax_min_sleep_time_for_parsing'
		), 'main_options', 'azlc_additional_opts' );
		add_settings_field( 'background_ajax_admin_option', 'Run in Background on Admin Pages', array(
			'AmazonLinkCheckerOptionsPage',
			'background_ajax_admin_option'
		), 'main_options', 'azlc_additional_opts' );
		add_settings_field( 'background_ajax_front_option', 'Run in Background on All Front-end Pages', array(
			'AmazonLinkCheckerOptionsPage',
			'background_ajax_front_option'
		), 'main_options', 'azlc_additional_opts' );
		add_settings_field('truncate', 'How much Amazon price history to save?', array('AmazonLinkCheckerOptionsPage', 'truncate'), 'main_options', 'azlc_additional_opts');
		add_settings_field( 'reset_plugin', 'Reset Plugin', array(
			'AmazonLinkCheckerOptionsPage',
			'reset_plugin'
		), 'main_options', 'azlc_additional_opts' );
		add_settings_field( 'debug', 'Debug Mode', array(
			'AmazonLinkCheckerOptionsPage',
			'debug_option'
		), 'main_options', 'azlc_additional_opts' );

		//new to version 1.0.1
		add_action('add_option', array('AmazonLinkCheckerCore', 'mailing_list'), 10, 3);

		//new to version 1.0.3
		if(is_multisite()) {
			add_settings_field('same_on_all_sites', '[Multisite] Use same settings on all sites',
			array('AmazonLinkCheckerOptionsPage', 'multisite_same_for_all'),  'main_options', 'azlc_additional_opts');
		}

	}


	public static function my_plugin_menus() {

		$tools_page_menu_name         = 'Amazon Links';

		//add_management_page( $page_title, $menu_title, $capability, $menu_slug, $function );
		// adds a submenu to the tools section
		add_management_page( AZLC_PLUGIN_TOOLS_PAGE_NAME, $tools_page_menu_name, 'manage_options', AZLC_MENU_SLUG_FOR_TOOLS, array(
			'AmazonLinkCheckerToolsPage',
			'tools_page'
		) );


		//add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);
		// adds a submenu to the options section
		add_options_page( AZLC_PLUGIN_SETTINGS_PAGE_NAME, AZLC_PLUGIN_NAME, 'manage_options', AZLC_MENU_SLUG_FOR_SETTINGS, array(
			'AmazonLinkCheckerOptionsPage',
			'options_page'
		) );
	}



	/**
	 * @return array
	 */
	public static function load_options() {

		$options                   = get_option( 'azlc_plugin_options' );
		$options['AWSSecretKey']   = isset( $options['AWSSecretKey'] ) ? $options['AWSSecretKey'] : '';
		$options['AWSAccessKeyId'] = isset( $options['AWSAccessKeyId'] ) ? $options['AWSAccessKeyId'] : '';
		$options['associate_tag']  = isset( $options['associate_tag'] ) ? $options['associate_tag'] : '';

		return $options;
	}

	public static function activate($network_wide) {

		if(is_multisite() && $network_wide) { // running on multi site with network install
		/* @var $wpdb WPDB */
			global $wpdb;
			$activated = array();
			$sql = "SELECT blog_id FROM $wpdb->blogs";
			$blog_ids = $wpdb->get_col($sql);
			foreach($blog_ids as $blog_id) {
				switch_to_blog($blog_id);
				AmazonLinkCheckerCore::implement_activation();
				$activated[] = $blog_id;
			}
			restore_current_blog();
			update_site_option('azlc_multisite_activated', $activated);

		} else { // running on a single blog
			AmazonLinkCheckerCore::implement_activation();
		}

		// this sets a transiet and should only be done once
			self::activate_about_page();

	}

	public static function implement_activation() {
/** @var $azlc_logger AZLC_Logger */
		global $azlc_logger, $wpdb;
		$azlc_logger->write( "Plugin Activated for: " . $wpdb->blogid);
		auto_fill_aws_creds();
		// IMPORTANT: have to instantiate a new instance, otherwise multisite install won't be correct
		$azlc_database = new AmazonLinkCheckerDatabase();
		$azlc_database->install();

		$internal_options_array = get_option( 'azlc_plugin_options_internal' );
		if ( $internal_options_array === false ) {
			$internal_options_array = array();
		}
		// set initial internal options and defaults
		$internal_options_array['pages_parsed']  = 0;
		$internal_options_array['continual_row'] = 0;
		$internal_options_array['az_ok']         = 0;
		$internal_options_array['email_footer'] = <<<EOD
 <p>
Thank you for using Check Amazon Links!
 
EOD;
		update_option( "azlc_plugin_options_internal", $internal_options_array );

		$azlc_locked_options = array( 'locked' => 0, 'time_locked' => time() );
		update_option( "azlc_locked_options", $azlc_locked_options );


	}

	public static function deactivate($network_wide) {
		if(is_multisite()) {
			$activated = get_site_option('azlc_multisite_activated');
			if($activated === 'false') {
					AmazonLinkCheckerCore::deactivate_implemented();
			} else {
			foreach($activated as $site) {
				switch_to_blog($site);
				AmazonLinkCheckerCore::deactivate_implemented();
			}
			restore_current_blog();
			delete_site_option('azlc_multisite_activated');
			}
		} else {
			AmazonLinkCheckerCore::deactivate_implemented();
		}
	}

	public static function deactivate_implemented() {
				wp_clear_scheduled_hook( 'azlc_send_email' );
				wp_clear_scheduled_hook( 'azlc_wk');
	}


}
endif;

if ( ! class_exists( 'AmazonLinkCheckerOptionsPage' ) ) :
class AmazonLinkCheckerOptionsPage {

	public static function options_page() {

		if ( get_option( 'azlc_plugin_options' ) ) {
			echo '<div class="updated"><p>Visit the
 <A href="tools.php?page=amazon_link_checker_menu_tools">Check Amazon Links Tools page</A> to check your links.</p></div>';
		}


		if ( ! get_option( "azlc_already_installed" ) ) {

			?>
			<div class="updated">
				<h1>Check Amazon Links Successfully Installed!</h1>

				<h2>Please Configure Required Options</h2>

				<P>The Check Amazon Links plugin has been successfully installed, however, the below options have to be
					set before any of your Amazon links can be checked.
					After you enter your information below and click on "Save Changes," the plugin will automatically
					start checking your Amazon links.

				</P>
			</div>
			<?php
			add_option( 'azlc_already_installed', true );
		}
		?>
		<div><h2>Check Amazon Links Plugin Options</h2>

			<form action="options.php" method="post">
				<?php settings_fields( 'amazon_link_plugin_options' ); ?>


				<?php do_settings_sections( 'main_options' ); ?>
				<?php //do_settings_sections( 'azlc_additional_opts' ); ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<div>
			<h2>After saving changes, visit the <A href="tools.php?page=amazon_link_checker_menu_tools">Amazon Link
					Checker Tools page</A> to check your links.</h2>
		</div>
		<?php
	}

	/*  ************* FUNCTIONS USED BY SETTINGS API ********************
		These make creating the settings admin page a breeze!
   *********************************************************************
	*/

	public static function plugin_section_text() {
		?><p>This plugin uses the official Amazon Product Advertising API to check your site's Amazon links.
			This API is very easy to sign up for. Just visit <A
				href="https://affiliate-program.amazon.com/gp/advertising/api/detail/main.html">
				https://affiliate-program.amazon.com/gp/advertising/api/detail/main.html</a></p>
		<p>
			After signing up, Amazon will give you the Access Key ID & AWS Secret Key. Just copy and paste them into the
			fields below.
		</p>
		<p>Am Amazon Associate Tag is required too.
			If you don't already have one, you can sign up at <A
				href="https://affiliate-program.amazon.com/gp/associates/network/main.html">
				https://affiliate-program.amazon.com/gp/associates/network/main.html</A>
		</p>


		<p>All fields below are <b>required</b> for this plugin to work. </p>
		<?php
	}


	public static function associate_tag_string() {
		$options = get_option( 'azlc_plugin_options' );
		$tag     = isset( $options['associate_tag'] ) ? esc_html($options['associate_tag']) : '';
		echo "<input id='associate_tag' name='azlc_plugin_options[associate_tag]' size='40' type='text'
    value='{$tag}' />";
	}

	public static function access_key_string() {
		$options = get_option( 'azlc_plugin_options' );
		$key     = isset( $options['AWSAccessKeyId'] ) ? esc_html($options['AWSAccessKeyId']) : '';
		echo "<input id='AWSAccessKeyId' name='azlc_plugin_options[AWSAccessKeyId]' size='40' type='text'
    value='{$key}' />";
	}

	public static function secret_key_string() {
		$options = get_option( 'azlc_plugin_options' );
		$key     = isset( $options['AWSSecretKey'] ) ? esc_html($options['AWSSecretKey']) : '';
		echo "<input id='AWSSecretKey' name='azlc_plugin_options[AWSSecretKey]' size='40' type='text'
    value='{$key}' />";
	}

	//form for: "Lookup Amazon Links in Background on Admin Pages"
	public static function background_ajax_admin_option() {
		$options = get_option( 'azlc_plugin_options' );
		$opt     = isset( $options['background_admin'] ) ? $options['background_admin'] : 1;

		?><select name='azlc_plugin_options[background_admin]'>
		<option value="1" <?php if ( $opt == 1 ) {
			echo "selected";
		} ?> >Yes
		</option>
		<option value="0" <?php if ( $opt == 0 ) {
			echo "selected";
		} ?> >No
		</option>
		</select>
		<?php
	}

	public static function default_region() {
		$options = get_option( 'azlc_plugin_options' );
		$opt     = isset( $options['region'] ) ? $options['region'] : 'com';
		?><select name='azlc_plugin_options[region]'>
		<?php
		$regions = array( 'ca', 'com', 'co.uk', 'de', 'fr', 'co.jp' );
		foreach ( $regions as $region ) {
			echo "<option value='$region'";
			if ( strcmp( $opt, $region ) == 0 ) {
				echo "selected";
			}
			echo ">" . $region . "</option>";
		}
		?>
		</select>  <i>Used when automatic detection fails.</i>
		<?php
	}

	public static function email_address() {
		$options     = get_option( 'azlc_plugin_options' );
		$admin_email = get_option( 'admin_email', '' );
		$opt         = isset( $options['email'] ) ? esc_html($options['email']) : $admin_email;
		echo "<input id='azlc_email' name='azlc_plugin_options[email]' size='40' type='text'
    value='{$opt}' />";
	}

	public static function mailing_list_option() {
	$options     = get_option( 'azlc_plugin_options' );
	echo  "<input type='checkbox' id='mailing_list_cb' name='azlc_plugin_options[mailing_list]'  value='1'";
		// default is checked box
		$opt_in = isset( $options['mailing_list']) ? $options['mailing_list'] : 1;
		echo checked( 1, $opt_in , false );
	 echo"> <label for='mailing_list_cb'>Yes, sign me up to your mailing list so I can receive discounts on premium plugins and
 news about
improvements. </label>
<br>";
	}

	public static function email_option() {
		$options = get_option( 'azlc_plugin_options' );
		$opt     = isset( $options['send_email'] ) ? $options['send_email'] : 1;

		?><select name='azlc_plugin_options[send_email]'>
		<option value="1" <?php if ( $opt == 1 ) {
			echo "selected";
		} ?> >Yes
		</option>
		<option value="0" <?php if ( $opt == 0 ) {
			echo "selected";
		} ?> >No
		</option>
		</select>
		<i>Receive a daily email if out of stock products are detected.</i>
		<?php
	}

	public static function truncate() {
	$options = get_option( 'azlc_plugin_options' );
	$opt     = isset( $options['truncate'] ) ? $options['truncate'] : 7;
	?><select name='azlc_plugin_options[truncate]'>
		<option value="7" <?php if ( $opt == 7 ) {
			echo "selected";
		} ?> > 7 Days
		</option>
		<option value="30" <?php if ( $opt == 30 ) {
			echo "selected";
		} ?> >30 Days
		</option>
		<option value="90" <?php if ( $opt == 90 ) {
			echo "selected";
		} ?> >90 Days
		</option>
		<option value="180" <?php if ( $opt == 180 ) {
			echo "selected";
		} ?> >180 Days
		</option>
		<option value="0" <?php if ( $opt == 0 ) {
			echo "selected";
		} ?> >Everything
		</option>
		</select>
		<i>If you have lots of Amazon links, the database may get too big.  To prevent this issue, old price history is deleted.</i>
		<?php

	}

	//form for: "Lookup Amazon Links in Background on All Front-end Pages"
	public static function background_ajax_front_option() {
		$options = get_option( 'azlc_plugin_options' );
		$opt     = isset( $options['background_front'] ) ? $options['background_front'] : 1;
		?><select name='azlc_plugin_options[background_front]'>
		<option value="1" <?php if ( $opt == 1 ) {
			echo "selected";
		} ?> >Yes
		</option>
		<option value="0" <?php if ( $opt == 0 ) {
			echo "selected";
		} ?> >No
		</option>
		</select>
		<?php
	}

	//form for: "Turn Debug On"
	public static function debug_option() {
		$options = get_option( 'azlc_plugin_options' );
		$opt     = isset( $options['debug'] ) ? $options['debug'] : 0;
		?><select name='azlc_plugin_options[debug]'>
		<option value="1" <?php if ( $opt == 1 ) {
			echo "selected";
		} ?> >Yes
		</option>
		<option value="0" <?php if ( $opt == 0 ) {
			echo "selected";
		} ?> >No
		</option>
		</select><i> Turns on Logging for troubleshooting. </i>
		<?php

	}

	// form for "Minimum Sleep Time Between Amazon Requests (seconds)"
	public static function min_sleep_time() {
		$options = get_option( 'azlc_plugin_options' );
		$opt     = isset( $options['min_sleep_time'] ) ? esc_html($options['min_sleep_time']) : 30;
		echo "<input id='min_sleep_time' name='azlc_plugin_options[min_sleep_time]' size='4' type='text'
    value='{$opt}' /> <i> seconds</i>";
	}

	public static function multisite_same_for_all() {
		$opt     = get_option( 'azlc_multisite_same' );
		$toggle = isset($opt['toggle']) ? $opt['toggle'] :  1;
		?><select name='azlc_multisite_same[toggle]'>
		<option value="1" <?php if ( $toggle == 1 ) {
			echo "selected";
		} ?> >Yes
		</option>
		<option value="0" <?php if ( $toggle == 0 ) {
			echo "selected";
		} ?> >No
		</option>
		</select> <i>Selecting yes will update all sites to these options.</i>
		<?php
	}


	//form for "Minimum Sleep Time Between Ajax Requests for Parsing Posts(seconds)"
	public static function ajax_min_sleep_time_for_parsing() {
		$options = get_option( 'azlc_plugin_options' );
		$opt     = isset( $options['ajax_sleep_time_parsing'] ) ? esc_html($options['ajax_sleep_time_parsing']) : 10;
		echo "<input id='ajax_sleep_time_parsing' name='azlc_plugin_options[ajax_sleep_time_parsing]' size='4' type='text'
    value='{$opt}' /> <i>seconds.  (For Parsing Posts.)</i>";
	}


	// form for "Reset Plugin (Setting to Yes will force all posts to be parsed for links again.)"
	public static function reset_plugin() {
		$options = get_option( 'azlc_plugin_options' );
		$opt     = isset( $options['reset'] ) ? $options['reset'] : 0;
		?><select name='azlc_plugin_options[reset]'>
		<option value="1" <?php if ( $opt == 1 ) {
			echo "selected";
		} ?> >Yes
		</option>
		<option value="0" <?php if ( $opt == 0 ) {
			echo "selected";
		} ?> >No
		</option>
		</select> <i>Setting to Yes will force all posts to be parsed for links again.</i>
		<?php
	}

	public static function additional_options() {
		echo "<h3>Additional Options</h3><p>In most cases, you do not need to change these settings.
		<BR><B>Warning:</B> Lowering the sleep times might cause server overload.  It is not recommended.</p>";
	}

}
endif;

if ( ! class_exists( 'AmazonLinkCheckerToolsPage' ) ) :
class AmazonLinkCheckerToolsPage {

	/*
 * ********* CREATES TOOLS PAGE  (admin page)  *******************
 *  Displays a table.
 * *****************************************************************
 */

	public static function tools_page() {

		echo '<div id="azlc_tools_page">';

	?>
			<h2>Check Amazon Links</h2>

			<p>If you have already filled in <A href="options-general.php?page=amazon_link_checker_menu_settings">the
					options</A>, then this plugin will automatically search your blog for links and check them in the
				background. This plugin is designed to use low resources.
				If you just installed this plugin, you may want to come back in a few hours to check its progress.
				Also, the look-ups are limited to once per day per product to keep resources low. We are working on a
				 premium plugin that will have this option and others configurable. If you have any ideas about how
				 to improve this plugin, <a href="http://www.linsoftware.com/contact/">please let us know.</a> Thank
				 you!
			</p>

			<P>
				<b>Tip:</b> Leave this page open and links will be checked in the background. Reload this page to update
				the table.
			</P>

			<div id="az_check">
				<?php
				$internal_opts = get_option( 'azlc_plugin_options_internal' );
				if ( $internal_opts['az_ok'] == 1 ) {

					echo "<p class='az_success'>&#x2713 Your Amazon API Credentials are working!
 					Just give this plugin some time to find & check your links.</p>";
				}
				?>

			</div>

			<div id="azlc_link_lookup_section">

				<div id="azlc_status"></div>
				<div id="azlc_status2"></div>

			</div>

			<div id="azlc_link_instances_section">
				<?php

				AZLC_Utility::load_sorted_links_table( 100, 0, 'fuzzy_status', 'DESC' );

				?>

			</div>


			<div>
			<p id="linsoftware_qc"></p>
			
				<h1>Need Help?</h1>
							<uL>
						<li>1. We are working to improve this plugin and your feedback is very helpful.
						Please use <A href="http://www.linsoftware.com/contact/">this form</A> to send us an email and be as specific as possible about your problem.
						</li>
						<li>2.  You can also reach us at Linnea DOT Wilhelm AT gmail DOT com</li>
</uL>

<h1>Like this Plugin?</h1>
Awesome!  Glad we can be of help!  More features are currently being developed to make this plugin much better!

<h3>How you can help:</h3>
<ul>
<li>1. <a href="https://wordpress.org/support/view/plugin-reviews/check-amazon-link">Write a review.</a>  Please!
If this plugin has helped you, please take a couple of minutes to write a review. </li>
<li>2.  <A href="http://www.linsoftware.com/contact/">Tell us</a> about a missing feature or a problem. This
plugin is still in active development!</li>
</ul>

			</div>
		</div>
		<?php


	}


}
endif;


//  This section enables support for paulstuttard's Amazon Link plugin
//  It does not change the functionality of paulstuttard's Amazon Link plugin
//  But it does add support for checking the stock status of links created with that plugin

add_shortcode( 'amazon', 'third_party_amazon_shortcode' );

function third_party_amazon_shortcode($atts, $content, $tag) {
if(defined('DOING_AZLC_AJAX')) {
if(DOING_AZLC_AJAX==1) {
	$asin = '';
	$channel = 'default';
	foreach($atts as $key=>$value) {
	if(strcmp(strtolower($key), 'asin') == 0) {
		$asin =  $value;
	}
	if(strcmp(strtolower($key), 'chan') == 0) {
		$channel =  $value;
	}

	parse_str(html_entity_decode($value), $search_array);
		if(array_key_exists('asin', $search_array)) {
			$asin =  $search_array['asin'];
	}
		if(array_key_exists('chan', $search_array)) {
			$channel =  $search_array['chan'];
	}

}

$amazonLinkOpts = get_option('AmazonLinkOptions', array('default_cc' => 'us'));
$amazonChannels = get_option('AmazonLinkChannels', array('tag_us' => ''));

$default_country = 'tag_' . $amazonLinkOpts['default_cc'] ;

$default_tag = '';

$url_domain = get_domain_extension($amazonLinkOpts['default_cc']);



if(isset($amazonChannels[$channel][$default_country])) {
	$default_tag = $amazonChannels[$channel][$default_country];
}

	if(!empty($asin)) {
    	return '<a href="http://www.amazon.' . $url_domain . '/dp/' . $asin .'?tag=' . $default_tag . '">Amazon
    	Link</A>';
	} else {
		return '';
	}

} }
 else {
	$ret = '[amazon';
	 foreach($atts as $key=>$value) {
	 $ret .= ' ' . $key . '=' . $value;
	 }
	 $ret .= ']';
	return $ret;
	}
	return '';
}


function auto_fill_aws_creds() {
$options                   = get_option( 'azlc_plugin_options', array(
"AWSSecretKey" => '',
"AWSAccessKeyId" => '',
"associate_tag" => '',
"region" => ''
) );

$amazonLinkOpts = get_option('AmazonLinkOptions', array('default_cc' => 'us'));
$amazonChannels = get_option('AmazonLinkChannels', array('tag_us' => ''));

if(isset($amazonLinkOpts['pub_key']) && empty($options["AWSAccessKeyId"])) {
$options["AWSAccessKeyId"] =$amazonLinkOpts['pub_key'];
}

if(isset($amazonLinkOpts['priv_key']) && empty($options["AWSSecretKey"])) {
$options["AWSSecretKey"]=$amazonLinkOpts['priv_key'];
}

if(isset($amazonLinkOpts['default_cc']) && empty($options["associate_tag"])) {
	$tag_name = 'tag_' . $amazonLinkOpts['default_cc'] ;
	if(isset($amazonChannels['default'][$tag_name])) {
		$options["associate_tag"] =$amazonChannels['default'][$tag_name];
	}
}

if(isset($amazonLinkOpts['default_cc']) && empty($options["region"])) {
$options["region"] = get_domain_extension($amazonLinkOpts['default_cc']);

}

update_option('azlc_plugin_options', $options);
}

function get_domain_extension($string) {
if( strcmp($string, 'us')===0) {
$url_domain = 'com';
} elseif (strcmp($string, 'uk')===0) {
$url_domain = 'co.uk';
} else {
$url_domain =$string;
}
return $url_domain;
}