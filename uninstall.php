<?php
/**
 * Created by PhpStorm.
 * User: Linnea
 * Date: 6/6/2015
 * Time: 11:35 AM
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
} // Exit if accessed directly
// If uninstall is not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}
if (is_multisite()) {
	global $wpdb;
	$sql = "SELECT blog_id FROM $wpdb->blogs";
	$blog_ids = $wpdb->get_col($sql);
	foreach($blog_ids as $blog_id) {
		azlc_uninstall();
	}
} else {
	azlc_uninstall();
}

function azlc_uninstall() {
	delete_option( 'azlc_already_installed' );
	delete_option( 'azlc_plugin_options' );
	delete_option( 'azlc_version' );
	delete_option( 'azlc_db_version' );
	delete_option( 'azlc_plugin_options_internal' );
	delete_option( 'azlc_locked_options' );

	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}azlkch_product_data" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}azlkch_products" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}azlkch_post_status" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}azlkch_link_instances" );
}