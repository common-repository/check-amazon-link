<?php
/**
 * Created by PhpStorm.
 * User: Linnea
 * Date: 5/2/2015
 * Time: 7:23 PM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
} // Exit if accessed directly
require_once( 'amazon-link-checker.php' );
require_once( 'AmazonLinkCheckerDatabase.php' );
require_once( 'AZLC_Logger.php' );

if ( ! class_exists( 'AZLC_LinkInstance' ) ) :
	class AZLC_LinkInstance {

		public $id = null;
		public $post_id = null;
		public $asin = null;
		public $link_type = null;
		public $link_code = null;
		public $link_text = null;
		public $affiliate_tag = null;
		public $time_updated = null;
		public $url = null;
		public $region = null;

		public function __construct( $arr = null ) {
			global $azlc_logger;
			$azlc_logger->write( "Creating a Link Instance" );
			// use is_array() to check if $arg is an array, and if so, set the values
			if ( is_array( $arr ) ) {

				if ( isset( $arr['post_id'] ) && isset( $arr['url'] ) && isset( $arr['link_type'] ) ) {
					if ( strcmp( $arr['link_type'], 'iframe' ) === 0 ) {
						$azlc_logger->write( "Creating a Link Instance -- got an iframe link" );
						// this is an iframe link, handle differently
						$this->post_id       = $arr['post_id'];
						$this->url           = $arr['url'];
						$this->affiliate_tag = $this->extractTag( $this->url, "tracking_id" );
						$this->region        = $this->extractRegion_iframe( $this->url );
						$this->asin          = $this->extractAsin_iframe( $this->url );
						$this->link_type     = $arr['link_type'];
						$azlc_logger->write( "Post id and url were set, here's the url:" . $this->url );
					} else {
						$this->link_type     = $arr['link_type'];
						$this->post_id       = $arr['post_id'];
						$this->url           = $arr['url'];
						$this->affiliate_tag = $this->extractTag( $this->url, "tag" );
						$this->region        = $this->extractRegion( $this->url );
						$this->asin          = $this->extractAsin( $this->url );
						$azlc_logger->write( "Post id and url were set, here's the url:" . $this->url );
					}
					if ( isset( $arr['link_code'] ) ) {
						$this->link_code = $arr['link_code'];

					}
					if ( isset( $arr['link_text'] ) ) {
						$this->link_text = $arr['link_text'];
					}
					if ( isset( $arr['id'] ) ) {
						$this->id = $arr['id'];
					} else {
						$this->id = uniqid( "amz", true );
					}
				} else {
					$this->id = uniqid( "amz", true );
				}

			}
		}

		public
		function save() {
			/** @var WPDB $wpdb */
			global $wpdb, $azlc_database;
			$wpdb->insert(
				$azlc_database->link_instances_table,
				array(
					'time_updated'  => current_time( 'mysql' ),
					'id'            => $this->id,
					'post_id'       => $this->post_id,
					'post_title'    => get_the_title( $this->post_id ),
					'asin'          => $this->asin,
					'link_type'     => $this->link_type,
					'link_code'     => $this->link_code,
					'link_text'     => $this->link_text,
					'affiliate_tag' => $this->affiliate_tag,
					'url'           => $this->url,
					'region'        => $this->region
				) );

			global $azlc_logger;
			$azlc_logger->write( "Save Function of AZLC_LinkInstance: Last Query: " . $wpdb->last_query );
			$azlc_logger->write( "Save Function of AZLC_LinkInstance: Last Error: " . $wpdb->last_error );


		}


		/**
		 * @param String
		 *
		 * @result String
		 *
		 * Note: this depends on an ASIN being exactly 10 characters
		 */
		private function extractAsin($url) {

			global $azlc_logger;

			//todo: expand this, refactor

			// Many Amazon links have the string /product/ prior to the ASIN
			$begin_product = stripos( $url, "/product/" );

			// Some Amazon links are formatted differently, for example:
			// http://www.amazon.com/Archer-Africa-William-Negley/dp/B001E0VEJ6/ref=sr_1_1?ie=UTF8&qid=1433094556&sr=8-1&keywords=archer+in+africa
			$begin_dp = stripos( $url, "/dp/" );

			$begin_offerlisting = stripos( $url, '/offer-listing/' );

			// some OLDER Amazon links look like this:
			// http://www.amazon.com/exec/obidos/ASIN1496106598/musiceducationon
			$begin_obidos =  stripos( $url, '/obidos/ASIN' );

			//support links generated with Wordpress Plugin EasyAzon
			//links look like this:
			// http://www.amazon.com/gp/aws/cart/add.html?ASIN.1=B0002D00QE&Quantity.1=1&AWSAccessKeyId=AKIAJKQZULH3I3UFF2QA&AssociateTag=musiceducationon
			$begin_cart =  stripos( $url, 'add.html?ASIN.1=');


			if ( ! $begin_product === false ) {
				$begin = $begin_product + 9;
			} elseif ( ! $begin_dp === false ) {
				$begin = $begin_dp + 4;
			} elseif ( ! $begin_offerlisting === false ) {
				$begin = $begin_offerlisting + 15;
			} elseif(! $begin_obidos === false) {
				$begin = $begin_obidos + 12;

			} elseif ( ! $begin_cart===false) {
				$begin = $begin_cart + 16;
			}

			if ( ! isset( $begin ) ) {
				return '';
			} else {
				return substr( $url, $begin, 10 );
			}

		}


		/**
		 * @param String
		 *
		 * @result String
		 */
		private
		function extractTag( $url, $label = 'tag' ) {
			//TODO: what if it's url encoded?  Maybe use a flag?
			$findthis = $label . "=";
			$length = strlen($findthis);
			// if no tag in the url, check to see if the url is the older OBIDOS format
			// otherwise return empty string
			if ( stripos( $url, $findthis) === false ) {
				$begin_obidos = stripos( $url, '/obidos/' );
				if( $begin_obidos!==FALSE) {
					$end_of_obidos_formatted_tag = stripos($url, '/', $begin_obidos+8);
					if($end_of_obidos_formatted_tag===TRUE) {
						$len_of_tag = $end_of_obidos_formatted_tag-($begin_obidos+8);
						return substr($url, $begin_obidos + 23, $len_of_tag );
					} else {
						return substr($url, $begin_obidos + 23);
					}
				} else {
					return '';
				}

			}

			$begin  = stripos( $url, $findthis ) + $length;
			$end = stripos($url, '&', $begin);
			if($end===FALSE) {
				return substr($url, $begin);
			}
			$length = $end - $begin;

			return substr( $url, $begin, $length );

		}

		private
		function extractRegion(
			$url
		) {
			$url_parts  = parse_url( $url );
			$amazon_pos = stripos( $url_parts['host'], "amazon." );
			if ( $amazon_pos === false ) {
				return '';
			} else {
				$begin = $amazon_pos + 7;

				return substr( $url_parts['host'], $begin );
			}


		}


		function extractTag_iframe( $url ) {
// if no tag in the url, return empty string (maybe NULL is better?)
			if ( stripos( $url, "&tracking_id=" ) === false ) {
				return '';
			}

			$begin  = stripos( $url, "&tracking_id" ) + 13;
			$end    = stripos( $url, "-20", $begin ) + 3;
			$length = $end - $begin;

			return substr( $url, $begin, $length );
		}

		function extractRegion_iframe( $url ) {
			if ( stripos( $url, "&region=" ) === false ) {
				//todo: maybe we should check for region elsewhere (?)
				$opts = get_option( 'azlc_plugin_options' );
				return $opts['region']; // returns default region
			}

			$begin  = stripos( $url, "&region=" ) + 8;
			$end    = stripos( $url, "&", $begin );
			$length = $end - $begin;
			$region = substr( $url, $begin, $length );
			if ( strcmp( $region, 'US' ) === 0 ) {
				$region = "com";
			}

			return $region;
		}


		function extractAsin_iframe( $url ) {
			$location = stripos( $url, "asins=" );
			if($location!==FALSE ) {
				$begin = stripos( $url, "asins=" ) + 6;
				return substr( $url, $begin, 10 );
			} else {
				return '';
			}

		}
	}
endif;