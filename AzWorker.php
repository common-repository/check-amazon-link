<?php
/**
 * Created by PhpStorm.
 * User: Linnea
 * Date: 5/7/2015
 * Time: 10:12 AM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
} // Exit if accessed directly
require_once( "aws_signed_request.php" );
require_once( "amazon-link-checker.php" );
require_once( "AmazonLinkCheckerDatabase.php" );
require_once( "AZLC_semaphore.php" );

if ( ! class_exists( 'AzWorker' ) ) :

	/**
	 * Class AzWorker
	 */
	class AzWorker {


		/**
		 * @var
		 */
		var $azlkch_options;

		/**
		 * @var int The length of time to sleep between lookups in seconds
		 */
		var $sleep = 5;


		/**
		 * @param $options
		 */
		function __construct( $options ) {
			$this->azlkch_options = $options;

		}


		function prepareForWork() {

			//Close the session to prevent lock-ups.
			//PHP sessions are blocking. session_start() will wait until all other scripts that are using the same session
			//are finished. As a result, a long-running script that unintentionally keeps the session open can cause
			//the entire site to "lock up" for the current user/browser. WordPress itself doesn't use sessions, but some
			//plugins do, so we should explicitly close the session (if any) before starting the worker.
			if ( session_id() != '' ) {
				session_write_close();
			}


			global $azlc_logger;
			// throw exception if locked
			if ( ! AZLC_semaphore::not_locked() ) {
				$azlc_logger->write( "locked so throwing an exception" );
				throw new Exception( "Locked" );
			}
			AZLC_semaphore::lock();


			//Don't stop the script when the connection is closed
			ignore_user_abort( true );

			// We don't use this code because we do send a reply back to the server
			// however, I'm saving this bit here because it may be useful in the future
			//
			//Close the connection as per http://www.php.net/manual/en/features.connection-handling.php#71172
			//This reduces resource usage.
			//(Disable when debugging or you won't get the FirePHP output)
			/*
			if (
				!headers_sent()
				&& (defined('DOING_AJAX') && constant('DOING_AJAX'))
				&& (!defined('BLC_DEBUG') || !constant('BLC_DEBUG'))
			){
				@ob_end_clean(); //Discard the existing buffer, if any
				header("Connection: close");
				ob_start();
				echo ('Connection closed'); //This could be anything
				$size = ob_get_length();
				header("Content-Length: $size");
				ob_end_flush(); // Strange behaviour, will not work
				flush();        // Unless both are called !
			}
		}
		*/


			// check that options are not empty, if any are empty, throw exception

			if ( empty( $this->azlkch_options['AWSAccessKeyId'] ) || empty( $this->azlkch_options['AWSSecretKey'] ) || empty( $this->azlkch_options['associate_tag'] ) ) {

				$azlc_logger->write( "Exception option not filled in." );
				throw new UnexpectedValueException( "Options not filled in" );
			}

		}

		function AZcredentials_are_working() {
			// this looks up a harry potter book to check if the AZ credentials for the Amazon Product API are working
			$asin   = '059035342X'; // ASIN for "Harry Potter and the Sorcerer's Stone"
			$region = 'com';
			$params = array(
				"Service"       => "AWSECommerceService",
				"Operation"     => "ItemLookup",
				"ResponseGroup" => "ItemAttributes",
				"ItemId"        => $asin,
				"IdType"        => "ASIN",
			);
			$url    = azlc_aws_signed_request( $region, $params, $this->azlkch_options['AWSAccessKeyId'], $this->azlkch_options['AWSSecretKey'],
				$this->azlkch_options['associate_tag'], '2011-08-01' );

			$response = wp_remote_get( $url );
			$body     = wp_remote_retrieve_body( $response );
			$xml      = simplexml_load_string( $body );

			$title = isset( $xml->Items->Item->ItemAttributes->Title ) ? (String) ( $xml->Items->Item->ItemAttributes->Title ) : null;

			if ( ! is_null( $title ) ) {
				return stripos( $title, 'Harry' ) !== false;
			} else {
				return false;
			}
		}

		/**
		 * @param String $asin
		 * @param String $region
		 */
		function getProductDetailsAndStore( $asin, $region ) {
			/* @var $wpdb WPDB */
			global $wpdb, $azlc_database, $azlc_logger;
			// check if asin already in database, if so, skip this
			$query = $wpdb->prepare( "SELECT asin FROM $azlc_database->product_table WHERE asin = %s", $asin );

			$wpdb->get_results( $query );
			if ( $wpdb->num_rows > 0 ) {
				$azlc_logger->write( "returning out of getProductDetailsAndStore because number of rows greater than 0" );

				return;
			}


			$params = array(
				"Service"       => "AWSECommerceService",
				"Operation"     => "ItemLookup",
				"ResponseGroup" => "ItemAttributes",
				"ItemId"        => $asin,
				"IdType"        => "ASIN",
			);
			$url    = azlc_aws_signed_request( $region, $params, $this->azlkch_options['AWSAccessKeyId'], $this->azlkch_options['AWSSecretKey'],
				$this->azlkch_options['associate_tag'], '2011-08-01' );

			global $azlc_logger;
			$azlc_logger->write( $url );
			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) {
				$error_string = $response->get_error_message();

				$azlc_logger->write(
					'<div id="message" class="error"><p>' . $error_string . '</p>
            <P>' . $url . '</P>
            <P>The Region: ' . esc_html( $region ) . '</P>'
					. '<pre>' . print_r( $this->azlkch_options ) . '</pre>
            </div>' );
			} else {
				$azlc_logger->write( "did not get any erro" );
			}

			$body = wp_remote_retrieve_body( $response );
			$xml  = simplexml_load_string( $body );

			$title        = isset( $xml->Items->Item->ItemAttributes->Title ) ? (String) ( $xml->Items->Item->ItemAttributes->Title ) : null;
			$productGroup = isset( $xml->Items->Item->ItemAttributes->ProductGroup ) ? (String) ( $xml->Items->Item->ItemAttributes->ProductGroup ) : null;

			$res = $wpdb->insert(
				$azlc_database->product_table,
				array(
					'asin'          => $asin,
					'title'         => $title,
					'product_group' => $productGroup,
					'region'        => $region
				) );
			$azlc_logger->write( "The database result was: " . $res );

			$azlc_logger->write( $wpdb->last_query );

		}


		/**
		 * This function accepts an array of up to 10 asins
		 * the region must be the same for all of them!
		 *
		 * @param $asins string|array
		 * @param $region string
		 *
		 * @return null|array  if successful, returns the response array from the get request
		 */
		function getProductDetailsFromAmazon($asins, $region) {

			if(is_array($asins)) {
				// make it a comma separated list (String)
				$asins = implode(",", $asins);
			}

			$params = array(
				"Service"       => "AWSECommerceService",
				"Operation"     => "ItemLookup",
				"ResponseGroup" => "ItemAttributes, OfferSummary, VariationSummary",
				"ItemId"        => $asins,
				"IdType"        => "ASIN",
			);
			$url    = azlc_aws_signed_request( $region, $params, $this->azlkch_options['AWSAccessKeyId'], $this->azlkch_options['AWSSecretKey'],
				$this->azlkch_options['associate_tag'], '2011-08-01' );

			global $azlc_logger;
			$azlc_logger->write( $url );
			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) {
				$error_string = $response->get_error_message();

				$azlc_logger->write(
					'<div id="message" class="error"><p>' . $error_string . '</p>
            <P>' . $url . '</P>
            <P>The Region: ' . esc_html( $region ) . '</P>'
					. '<pre>' . print_r( $this->azlkch_options ) . '</pre>
            </div>' );
				return null;
			} else {
				$azlc_logger->write( "did not get any erro" );
				return $response;
			}
		}

		function saveProductDetails($response_array, $region) {
			/* @var $wpdb WPDB */
			global $wpdb;
			global $azlc_database;
			if($response_array) {
				$body = wp_remote_retrieve_body( $response_array );
				$data = $this->parseAmazonXMLDataforProductDetails($body);

				//for each item details
				//update product_table if title is different
				//insert into prices table
				if(!empty($data['items'])) {
					foreach($data['items'] as $item) {

					//check if title is different
					$title = $wpdb->get_var("SELECT title FROM " . $azlc_database->product_table . "
					 WHERE asin = '" . $item['asin'] . "'");
						if($title) {
							if(strcmp($title, $item['Title'])!==0) {
								//they are different, we need to update database
								//$wpdb->update( $table, $data, $where, $format = null, $where_format = null )
								$wpdb->update($azlc_database->product_table, array('title'=>$item['Title']), array
								('asin' => $item['asin']));
							}
						} else {
							$abstract = $item['variation'] > 0 ? 1 : 0;
							$item['abstract'] = $abstract;


							$wpdb->insert(
								$azlc_database->product_table,
								array(
									'asin'          => $item['asin'],
									'title'         => $item['Title'],
									'product_group' => $item['ProductGroup'],
									'region'        => $region,
									'abstract'      => $abstract
								) );
						}

						//insert into prices database

						$item['stock_status'] = AZLC_Utility::fuzzy_stock_status( $item );


						$wpdb->insert(
							$azlc_database->product_data_table,
							array(
								'asin'                   => $item['asin'],
								'TotalNew'               => $item['TotalNew'],
								'TotalUsed'              => $item['TotalUsed'],
								'TotalCollectible'       => $item['TotalCollectible'],
								'TotalRefurbished'       => $item['TotalRefurbished'],
								'LowestUsedPrice'        => $item['LowestUsedPrice'],
								'LowestCollectiblePrice' => $item['LowestCollectiblePrice'],
								'LowestNewPrice'         => $item['LowestNewPrice'],
								'LowestRefurbishedPrice' => $item['LowestRefurbishedPrice'],
								//'error_code'             => $item['error_code'],
								//'error_message'          => $item['error_message'],
								'time_of_retrieval'      => current_time( 'mysql' ),
								'stock_status'           => $item['stock_status']
							) );


					}

					}
			}

		}




		/**
		 * Parses Amazon XML response into a multidimensional array
		 * This function will work with single or multiple ASIN lookups
		 *
		 * Returns a multidimensional array
		 * The inner Items array contains the price data
		 * The inner Errors array contains an array of error messages
		 * @param $xml
		 *
		 * @return array  array("items"=>, "errors"=>)
		 * @throws Exception
		 */
		function parseAmazonXMLDataforProductDetails($xml) {
			$pxml = simplexml_load_string($xml);
			$data = array("items"=>array(), "errors" =>array());

			if ($pxml === FALSE) {
				$data["errors"][] = "Response could not be parsed.";
				return $data;
			}


			$isValid = (string) $pxml->Items->Request->IsValid;
			if(strcmp('True', $isValid)!==0) {
				$error_string = "Invalid Request";
				if(isset($pxml->ItemLookupErrorResponse->Error->Code)) {
					$error_string .= ", Error Code: " . (string) $pxml->ItemLookupErrorResponse->Error->Code;
				}
				if(isset($pxml->ItemLookupErrorResponse->Error->Message)) {
					$error_string .= ", Error Message: " . (string) $pxml->ItemLookupErrorResponse->Error->Message;
				}
				$data["errors"][] = $error_string;
				return $data;
			}


			$i = 0;
			if(isset($pxml->Items->Request->Errors->Error)) {
				foreach ( $pxml->Items->Request->Errors->Error as $error ) {
					$error_string = 'Amazon Response Contains Error: ';
					if ( isset( $error->Code ) ) {
						$error_string .= "Error Code: " . (string) $error->Code;
					}
					if ( isset( $error->Message ) ) {
						$error_string .= ", Error Message: " . (string) $error->Message;
					}
					$data["errors"][ $i ] = $error_string;
					$i ++;
				}
			}

			$i = 0;
			foreach ($pxml->Items->Item as $item){
				if(!isset($item->ASIN)) {
					continue;
				}

				$data['items'][$i]['asin'] = (string) $item->ASIN;

				if (isset($item->ItemAttributes->Title)) {
					$data['items'][$i]['Title']  =  (string) $item->ItemAttributes->Title;
				} else {
					$data['items'][$i]['Title']   = Null;
				}

				if (isset($item->ItemAttributes->ProductGroup)) {
					$data['items'][$i]['ProductGroup'] =  (string)$item->ItemAttributes->ProductGroup;
				} else {
					$data['items'][$i]['ProductGroup'] = Null;
				}


				if (isset($item->OfferSummary->LowestUsedPrice->Amount)) {
					$data['items'][$i]['LowestUsedPrice'] =  (string)$item->OfferSummary->LowestUsedPrice->Amount;
				} else {
					$data['items'][$i]['LowestUsedPrice'] = Null;
				}


				if (isset($item->OfferSummary->LowestCollectiblePrice->Amount)) {
					$data['items'][$i]['LowestCollectiblePrice'] =  (string)$item->OfferSummary->LowestCollectiblePrice->Amount;
				} else {
					$data['items'][$i]['LowestCollectiblePrice'] = Null;
				}



				if (isset($item->OfferSummary->LowestRefurbishedPrice->Amount)) {
					$data['items'][$i]['LowestRefurbishedPrice'] =  (string)$item->OfferSummary->LowestRefurbishedPrice->Amount;
				} else {
					$data['items'][$i]['LowestRefurbishedPrice'] = Null;
				}


				if (isset($item->OfferSummary->LowestNewPrice->Amount)) {
					$data['items'][$i]['LowestNewPrice'] =  (string)$item->OfferSummary->LowestNewPrice->Amount;
				} else {
					$data['items'][$i]['LowestNewPrice'] = Null;
				}


				if (isset($item->Offers->TotalOffers)) {
					$data['items'][$i]['TotalOffers'] =  (string)$item->Offers->TotalOffers;
				} else {
					$data['items'][$i]['TotalOffers'] = Null;
				}

				if (isset($item->OfferSummary->TotalNew)) {
					$data['items'][$i]['TotalNew'] =  (string)$item->OfferSummary->TotalNew;
				} else {
					$data['items'][$i]['TotalNew'] = Null;
				}


				if (isset($item->OfferSummary->TotalUsed)) {
					$data['items'][$i]['TotalUsed'] =  (string)$item->OfferSummary->TotalUsed;
				} else {
					$data['items'][$i]['TotalUsed'] = Null;
				}

				if (isset($item->OfferSummary->TotalCollectible)) {
					$data['items'][$i]['TotalCollectible'] =  (string)$item->OfferSummary->TotalCollectible;
				} else {
					$data['items'][$i]['TotalCollectible'] = Null;
				}

				if (isset($item->OfferSummary->TotalRefurbished)) {
					$data['items'][$i]['TotalRefurbished'] = (string) $item->OfferSummary->TotalCollectible;
				} else {
					$data['items'][$i]['TotalRefurbished'] = Null;
				}

				//VariationSummary->LowestPrice->Amount

				if (isset($item->VariationSummary->LowestPrice->Amount)) {
					$data['items'][$i]['variation'] = (string) $item->VariationSummary->LowestPrice->Amount;
				} else {
					$data['items'][$i]['variation'] = 0;
				}
				$i++;
			}

			return $data;
		}





		/**
		 * @param $asin
		 * @param $region
		 */
		function getCurrentPricesAndStore( $asin, $region ) {

			global $azlc_logger;
			$azlc_logger->write( "In getCurrentPricesAndStore... with this asin: " . esc_html( $asin ) . " and this region " . $region );


			// clean table first, if necessary
			AZLC_Utility::clean_data_table( $asin );

			$params = array(
				"Service"       => "AWSECommerceService",
				"Operation"     => "ItemLookup",
				"ResponseGroup" => "OfferSummary",
				"ItemId"        => $asin,
				"IdType"        => "ASIN",
			);

			$url = azlc_aws_signed_request( $region, $params, $this->azlkch_options['AWSAccessKeyId'], $this->azlkch_options['AWSSecretKey'],
				$this->azlkch_options['associate_tag'], '2011-08-01' );

			$azlc_logger->write( "url: " . $url );

			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) {
				$error_string = $response->get_error_message();
				$azlc_logger->write( "error: " . $error_string );
			}

			$body = wp_remote_retrieve_body( $response );
			$xml  = simplexml_load_string( $body );

			$data         = array();
			$data['asin'] = $asin;
			if ( $this->valid_request( $xml ) ) {

				$data['TotalNew']         = isset( $xml->Items->Item->OfferSummary->TotalNew ) ? (String) ( $xml->Items->Item->OfferSummary->TotalNew ) : null;
				$data['TotalUsed']        = isset( $xml->Items->Item->OfferSummary->TotalUsed ) ? (String) ( $xml->Items->Item->OfferSummary->TotalUsed ) : null;
				$data['TotalCollectible'] = isset( $xml->Items->Item->OfferSummary->TotalCollectible ) ? (String) ( $xml->Items->Item->OfferSummary->TotalCollectible ) : null;
				$data['TotalRefurbished'] = isset( $xml->Items->Item->OfferSummary->TotalRefurbished ) ? (String) ( $xml->Items->Item->OfferSummary->TotalRefurbished ) : null;
				$data['LowestUsedPrice']  = isset( $xml->Items->Item->OfferSummary->LowestUsedPrice->Amount ) ? (int) ( $xml->Items->Item->OfferSummary->LowestUsedPrice->Amount ) : null;
				//TODO add to database this field
				$data['LowestRefurbishedPrice'] = isset( $xml->Items->Item->OfferSummary->LowestRefurbishedPrice->Amount ) ? (int) ( $xml->Items->Item->OfferSummary->LowestRefurbishedPrice->Amount ) : null;
				$data['LowestNewPrice']         = isset( $xml->Items->Item->OfferSummary->LowestNewPrice->Amount ) ? (int) ( $xml->Items->Item->OfferSummary->LowestNewPrice->Amount ) : null;
				$data['LowestCollectiblePrice'] = isset( $xml->Items->Item->OfferSummary->LowestCollectiblePrice->Amount ) ? (int) ( $xml->Items->Item->OfferSummary->LowestCollectiblePrice->Amount ) : null;
				$data['error_code']             = isset( $xml->Items->Errors->Error->Code ) ? (string) ( $xml->Items->Errors->Error->Code ) : null;
				$data['error_message']          = isset( $xml->Items->Errors->Error->Message ) ? (string) ( $xml->Items->Errors->Error->Message ) : null;
				global $wpdb, $azlc_database;


				// **** EXTRA WORK ON ASINs that APPEAR OUT OF STOCK ******//
				// if item is out of stock, check to see if this is an abstract variations ASIN
				// this will save us from false positives
				//  http://docs.aws.amazon.com/AWSECommerceService/latest/DG/RG_VariationSummary.html
				// A variation is a child ASIN. The parent ASIN is an abstraction of the children items. For example, a shirt is a parent ASIN and parent ASINs cannot be sold.
				// This check should be done with every lookup because even abstract ASINs can become invalid/broken links

				if ( $data['TotalNew'] == 0 && $data['TotalUsed'] == 0 && $data['TotalRefurbished'] == 0 && $data['TotalCollectible'] == 0 ) {

					//todo:option  change this to the option of minimum time to sleep between amazon requests
					sleep( 1 );
					$params = array(
						"Service"       => "AWSECommerceService",
						"Operation"     => "ItemLookup",
						"ResponseGroup" => "VariationSummary",
						"ItemId"        => $asin,
						"IdType"        => "ASIN",
					);

					$url = azlc_aws_signed_request( $region, $params, $this->azlkch_options['AWSAccessKeyId'], $this->azlkch_options['AWSSecretKey'],
						$this->azlkch_options['associate_tag'], '2011-08-01' );

					$response = wp_remote_get( $url );

					if ( is_wp_error( $response ) ) {
						$error_string = $response->get_error_message();
						$azlc_logger->write( "error: " . $error_string );
					}

					$body = wp_remote_retrieve_body( $response );
					$xml  = simplexml_load_string( $body );


					if ( $this->valid_request( $xml ) ) {
						global $azlc_logger;
						$azlc_logger->write( "We are checking the variationsummary" );
						$variation_low_price = isset( $xml->Items->Item->VariationSummary->LowestPrice->Amount ) ? (int) ( $xml->Items->Item->VariationSummary->LowestPrice->Amount ) : 0;
					} else {
						$variation_low_price = 0;
					}
					if ( $variation_low_price > 0 ) {
						//YES, this is an abstract ASIN for a variation page!
						$data['abstract'] = 1;
						/* @var $wpdb WPDB */
						global $wpdb;
						$wpdb->update( $azlc_database->product_table, array( 'abstract' => 1 ), array( 'asin' => $asin ) );
					} else {
						/* @var $wpdb WPDB */
						global $wpdb;
						//we should record that we checked and this is NOT a valid abstract ASIN
						$wpdb->update( $azlc_database->product_table, array( 'abstract' => 0 ), array( 'asin' => $asin ) );
					}

				}

				$data['stock_status'] = AZLC_Utility::fuzzy_stock_status( $data );


				$wpdb->insert(
					$azlc_database->product_data_table,
					array(
						'asin'                   => $data['asin'],
						'TotalNew'               => $data['TotalNew'],
						'TotalUsed'              => $data['TotalUsed'],
						'TotalCollectible'       => $data['TotalCollectible'],
						'TotalRefurbished'       => $data['TotalRefurbished'],
						'LowestUsedPrice'        => $data['LowestUsedPrice'],
						'LowestCollectiblePrice' => $data['LowestCollectiblePrice'],
						'LowestNewPrice'         => $data['LowestNewPrice'],
						'LowestRefurbishedPrice' => $data['LowestRefurbishedPrice'],
						'error_code'             => $data['error_code'],
						'error_message'          => $data['error_message'],
						'time_of_retrieval'      => current_time( 'mysql' ),
						'stock_status'           => $data['stock_status']
					) );

				$azlc_logger->write( "the last query : " . $wpdb->last_query );


			} else {
				$azlc_logger->write( "invalid request" );
			}

		}

		function valid_request( $xml ) {
			if ( ! isset( $xml->Items->Request->IsValid ) ) {
				return false;
			}
			if ( (String) $xml->Items->Request->IsValid === 'True' ) {
				return true;
			} else {
				return false;
			}
		}

	


		/**
		 * @param array $object The Array of Results Returned from the Database
		 * @param string $context The name of the calling function or other context description
		 */
		function checkdatabaseresult( $object, $context = '' ) {
			global $azlc_logger, $wpdb;
			if ( $object == null ) {
				$azlc_logger->write( $context . ": Database Result Object was NULL, Last WPDB error message: " . $wpdb->last_error );
				$azlc_logger->write( $context . " Last sql: " . $wpdb->last_query );
			} elseif ( empty( $object ) ) {
				$azlc_logger->write( $context . ": Database Result was EMPTY, Last WPDB error message: " . $wpdb->last_error );
			} else {
				$azlc_logger->write( $context . ": Received an array of results with the following lenth: " . count( $object ) );

			}
		}


		public function already_parse( $post_ID ) {
			/* @var $wpdb WPDB */
			global $wpdb, $azlc_database;
			$query = $wpdb->prepare( "SELECT id FROM $azlc_database->post_status_table WHERE post_id = %d AND completed = 1", $post_ID );
			$wpdb->get_results( $query );

			return $wpdb->num_rows > 0;
		}

		// TODO HERE

		public function parse_single_post( $post_ID, $reparse = false, $relookup = 0 ) {
			// if post is already parsed, return asins and regions from database, no need to reparse
			if ( ! $reparse ) {
				if ( $this->already_parse( $post_ID ) ) {
					global $azlc_logger, $azlc_database;
					$azlc_logger->write( "post already parsed, returning with asins already in database" );
					/* @var $wpdb WPDB */
					global $wpdb;
					$query          = $wpdb->prepare( "SELECT asin, region FROM $azlc_database->link_instances_table WHERE post_id = %d", $post_ID );
					$count          = 0;
					$asins          = $wpdb->get_results( $query );
					$data_to_return = array();
					foreach ( $asins as $key => $value ) {

						// if relookup=0 then check to see if asin has recently been looked up
						// if its been recently looked up, skip this iteration
						// and continue to next one
						if ( $relookup == 0 ) {
							global $azlc_logger;
							$azlc_logger->write( "1 Already looked up " + esc_html( $value->asin ) );
							if ( $this->recently_lookedup( $value->asin ) ) {
								continue;
							}
						}

						$count ++;
						$x                    = "link" . $count;
						$data_to_return[ $x ] = array( "asin" => $value->asin, "region" => $value->region );
					}
					$data_to_return['count'] = $count;

					return $data_to_return;
				}
			}
			$data = array();
			global $azlc_logger;
			$azlc_logger->write( "in parse_single_post and the post id is: " . esc_html( $post_ID ) );
			/* @var $wpdb WPDB */
			global $wpdb, $azlc_database;
			$wpdb->insert(
				$azlc_database->post_status_table,
				array(
					'post_id'      => $post_ID,
					'completed'    => 0,
					'time_updated' => current_time( 'mysql' )
				) );
			$row_id = $wpdb->insert_id;

			// first, delete any entries for this post_id so that our database doesn't get duplicates
			$azlc_database->deleteLinkInstances( $post_ID );

			try {
				$content = get_post_field( 'post_content', $post_ID );

				if(empty($content)) {
					throw new Exception("Post Content Empty");
				}
				define('DOING_AZLC_AJAX', '1');
				$content = do_shortcode( $content );

				$parser = new AZLC_HTMLParser( $content );

				$data = $parser->extractLinks();
			} catch ( Exception $ex ) {
				// we don't want to try to parse this post again!
				$rows_updated = $this->markPostAsCompleted( $wpdb, $azlc_database, $row_id );
				$azlc_logger->write( "Exception: " . $ex->getMessage() );
				$options       = get_option( 'azlc_plugin_options' );
				$x['interval'] = $options['ajax_sleep_time_parsing'];
				$x['success'] = 1; //this is a hack, lots of hacks in this section to deal with empty posts
				$x['az']      = 1;
				$this->finish();
				echo json_encode($x);
				wp_die();
			}
			$data_to_return = array();
			$count          = 0;
			foreach ( $data as $d ) {

				$d["post_id"]  = $post_ID;
				$link_instance = new AZLC_LinkInstance( $d );
				if(empty($link_instance->asin)) {
					// if asin is empty, do not save this link instance
					continue;
				}
				$count ++;
				$x = "link" . $count;

				$link_instance->save();

				// if relookup=0 then check to see if asin has recently been looked up
				// if its been recently looked up, skip this iteration
				// and continue to next one
				if ( $relookup == 0 ) {
					if ( $this->recently_lookedup( $link_instance->asin ) ) {
						continue;
					}
				}

				$data_to_return[ $x ] = array( "asin" => $link_instance->asin, "region" => $link_instance->region );


			}

			$data_to_return['count'] = $count;
			// record finish of parse_post

			$rows_updated = $this->markPostAsCompleted( $wpdb, $azlc_database, $row_id );

			$azlc_logger->write( "rows updated for table post_status: " . $rows_updated );

			return $data_to_return;

		}


		public function reset() {
			$options       = get_option( 'azlc_plugin_options' );
			$internal_opts = get_option( 'azlc_plugin_options_internal' );
			global $wpdb, $azlc_database;
			$wpdb->query( "TRUNCATE TABLE " . $azlc_database->post_status_table );
			$wpdb->query( "TRUNCATE TABLE " . $azlc_database->link_instances_table );
			$wpdb->query( "TRUNCATE TABLE " . $azlc_database->product_data_table );
			$wpdb->query( "TRUNCATE TABLE " . $azlc_database->product_table );
			$internal_opts['pages_parsed'] = 0;
			$options['reset']              = 0;
			update_option( "azlc_plugin_options", $options );
			update_option( "azlc_plugin_options_internal", $internal_opts );
		}


		public function recently_lookedup( $asin ) {
			// return true if asin has been looked up within the last day
			// this helper function helps the program avoid looking up the same asins over and over
			// once price lookup per day is usually enough

			/* @var $wpdb WPDB */
			global $wpdb;
			global $azlc_database;
			$query  = $wpdb->prepare( "SELECT id FROM $azlc_database->product_data_table WHERE time_of_retrieval > now() - INTERVAL 1 DAY AND asin = %s", $asin );
			$result = $wpdb->get_results( $query );
			$this->checkdatabaseresult( $result, 'recently_lookedup' );

			return $wpdb->num_rows > 0;
		}


		public function get_products_by_post_id( $id ) {
			/* @var $wpdb WPDB */
			global $wpdb, $azlc_database;
			//todo santize $id
			$query   = $wpdb->prepare( "SELECT asin FROM $azlc_database->link_instances_table WHERE post_id = %d", $id );
			$results = $wpdb->get_results( $query, ARRAY_A );
			$asins   = array();
			foreach ( $results as $r ) {
				$asins[] = $r['asin'];
			}

			return $asins;

		}


		public function update_priority_queue() {
			// first, do housework on setting up the priority queue
			// $pids is an array of post_ids, or its empty
			$pids = get_option( 'azlc_pids', array() );
			while ( ! empty( $pids ) ) {
				$post_id = array_pop( $pids );
				// get each product featured on that post
				$asins = $this->get_products_by_post_id( $post_id );
				// check each product to see if its been looked up recently
				// if not, add the asin to the priority queu
				foreach ( $asins AS $a ) {
					if ( ! $this->recently_lookedup( $a ) ) {
						$this->add_to_priority_queue( $a );
					}
				}
			}
			update_option( 'azlc_pids', $pids );
		}

		public function get_region( $asin ) {
			/* @var $wpdb WPDB */
			global $wpdb, $azlc_database;
			$query = $wpdb->prepare( "SELECT region FROM $azlc_database->link_instances_table WHERE asin = %s LIMIT 1", $asin );

			return $wpdb->get_var( $query );
		}

		public function lookup_next_asin() {
			//goal: look up as many as 10 asins at a time
			/* @var $wpdb WPDB */
			global $azlc_database, $wpdb, $azlc_logger;

			// first, do housework
			$this->update_priority_queue();

			$priority_queue = get_option( 'azlc_prq', array() );
			if ( ! empty( $priority_queue ) ) {
				$asins = array();
				$asins[] = array_pop( $priority_queue );
				$region = $this->get_region( $asins[0] );
				$i=0;
				if(is_null($region)) {
					$region = $this->getDefaultRegion();
				}
				while($i<10 && !empty($priority_queue)) {
					$next_asin = array_pop($priority_queue);
					$region2 = $this->get_region($next_asin);
					if(strcmp($region, $region2)!==0) {
						//if the regions are not the same, we can't look them up in one request
						//put it back on the priority queue for next time
						$priority_queue[] = $next_asin;
						break;
					}
					$asins[] = $next_asin;
					$i++;
					//todo: consider adding more asins if this is less than 10!
				}
				update_option( 'azlc_prq', $priority_queue );
				// now we should have an array of up to 10 asins with all the same region
			} else {
				$internal_options_array = get_option( 'azlc_plugin_options_internal' );
				$begin_row              = $internal_options_array['continual_row'];
				$time = current_time('mysql');
				$query                  = "SELECT DISTINCT asin, region FROM " . $azlc_database->link_instances_table . "
				 WHERE asin NOT IN (SELECT asin FROM " . $azlc_database->product_data_table .
				                          " WHERE time_of_retrieval >
				 ( '" . $time . "'
				 - INTERVAL + 1 DAY)) ORDER BY post_id, region LIMIT " . $begin_row . " , 10";

				$items  = $wpdb->get_results( $query, ARRAY_A );



				if ( is_null( $items ) || empty($items) ) {

					//check if table is empty, if empty, exit (prevents inifinite loop)
					$count = $wpdb->get_var( "SELECT COUNT(*) FROM " . $azlc_database->link_instances_table );
					if ( $count == 0 ) {
						$azlc_logger->write( "links table empty" );

						return;
					}

					// if table isn't empty, then it must mean that we got to the end of the table
					// reset it to 0
					$azlc_logger->write( "table has data, but row was null, resetting to 0" );
					if($internal_options_array['continual_row'] !==0) {
						$internal_options_array['continual_row'] = 0;
						update_option( "azlc_plugin_options_internal", $internal_options_array );
					}
					//items is empty, no need to continue further
					return;

				}


				$region = $items[0]['region'];
				if(is_null($region)) {
					$region = $this->getDefaultRegion();
				}
				$asins  = array();


				foreach ( $items as $item ) {
					if ( strcmp( $item['region'], $region ) === 0 ) {
						$asins[] = $item['asin'];
					} else {
						break;
					}
				}

				$internal_options_array['continual_row']  = $internal_options_array['continual_row'] + count($asins);
				update_option( 'azlc_plugin_options_internal', $internal_options_array );
			}

			if(!empty($asins)) {
				$res = $this->getProductDetailsFromAmazon($asins, $region);
				$this->saveProductDetails($res, $region);
			}

		}


		public function getDefaultRegion() {
			$options = get_option( 'azlc_plugin_options', array( 'region' => 'com' ) );
			return $options['region'];
		}



		public function add_to_priority_queue( $asin ) {
			$priority_queue   = get_option( 'azlc_prq', array() );
			$priority_queue[] = $asin;
			update_option( 'azlc_prq', $priority_queue );

		}

		public static function get_asins_not_looked_up( $max_number = '30', $timeframe = '7' ) {
			global $azlc_database, $wpdb;
			/* @var $wpdb WPDB */
			$query   = $wpdb->prepare( "SELECT DISTINCT asin FROM $azlc_database->link_instances_table WHERE asin NOT IN
		(SELECT asin FROM $azlc_database->product_data_table WHERE
		time_of_retrieval >= DATE_SUB(NOW(), INTERVAL %d DAY)) LIMIT %d", $timeframe, $max_number );
			$results = $wpdb->get_results( $query, ARRAY_A );
			$asins   = array();
			foreach ( $results as $r ) {
				$asins[] = $r['asin'];
			}

			return $asins;
		}

		public function finish() {
			AZLC_semaphore::unlock();
		}

		/**
		 * @param $wpdb
		 * @param $azlc_database
		 * @param $row_id
		 *
		 * @return mixed
		 */
		public function markPostAsCompleted( $wpdb, $azlc_database, $row_id ) {
			$rows_updated = $wpdb->update( $azlc_database->post_status_table, array( 'completed' => 1 ), array( 'id' => $row_id ), $format = null, $where_format = null );

			return $rows_updated;
		}

	}

endif;





