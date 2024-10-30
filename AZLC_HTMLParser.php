<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
} // Exit if accessed directly

require_once( "simplehtmldom/simple_html_dom.php" );
require_once( "AZLC_Logger.php" );

if ( ! class_exists( 'AZLC_HTMLParser' ) ) :
	class AZLC_HTMLParser {

		var $html;
		var $supports_curl = false;

		function __construct( $content ) {
			$this->html = $content;
			if(function_exists('curl_version')) {
				$this->supports_curl = true;
			}
		}


		/**
		 * @return array[][]
		 */
		function extractLinks() {
			$data       = array();
			$this->html = str_get_html( $this->html );

			if($this->html===FALSE) {
				//str_get_html returns false if the string is too large or empty
				//return empty array if the html is a boolean
				return $data;
			}


			$i          = 0;
			global $azlc_logger;
			$azlc_logger->write( "AZLC_HTMLParser extract links" );

			// extract links
			foreach ( $this->html->find( 'a' ) as $element ) {

				global $azlc_logger;
				$azlc_logger->write( "AZLC_HTMLParser Logger in Foreach loop" );
				$url = $element->href;
				$url_parts = parse_url( $url );
				if($url_parts===FALSE || !array_key_exists ('host', $url_parts)) {
					//url does not contain a host name
					// it's not an amazon link, so skip
					continue;
				}
				// periods are added to the search string to avoid returning incorrect urls
				// for example: http://amazon.myblog.com would NOT be a match
				if ( stripos( $url_parts['host'], ".amazon." ) !== false ) {
					$data[ $i ]['url']       = urldecode( $element->href );
					$data[ $i ]['link_text'] = $element->innertext;
					$data[ $i ]['link_code'] = $element->outertext;
					$data[ $i ]['link_type'] = "a";
					$i ++;
				}
				// handle Amazon Short Links urls
				// this is commented out due to concerns over violating Amazon's TOS
				// it would be nice to be able to check shortcodes
				// so if you're reading this and have any thoughts, send them to the author's email
				//if ( stripos( $url_parts['host'], "amzn.to" ) !== false && $this->supports_curl ) {
				//	$finalURL = $this->getFinalUrl($url);
				//	if(empty($finalURL)) {
				//		break;
				//	}
				//	$data[ $i ]['url']       = $finalURL;
				//	$data[ $i ]['link_text'] = $element->innertext;
				//	$data[ $i ]['link_code'] = $element->outertext;
				//	$data[ $i ]['link_type'] = "a";
				//	$i ++;
				//}

			}

			// extract iframes
			foreach ( $this->html->find( 'iframe' ) as $element ) {
				$azlc_logger->write( "AZLC_HTMLParser Logger in Foreach loop - found an IFRAME" );
				$url       = $element->src;
				$url_parts = parse_url( $url );

				if(! array_key_exists('host', $url_parts )) {
					//url does not contain a host name
					// it's not an amazon link, so skip
					continue;
				}

				if ( stripos( $url_parts['host'], ".amazon-adsystem." ) !== false ) {
					$data[ $i ]['url']       = urldecode( $element->src );
					$data[ $i ]['link_text'] = "";
					$data[ $i ]['link_code'] = $element->outertext;
					$data[ $i ]['link_type'] = "iframe";
					$i ++;
				}

			}

			// extract amazon forms
			foreach ( $this->html->find( 'form' ) as $form ) {
				$azlc_logger->write( "Found a form" );
				$url       = $form->action;
				$url_parts = parse_url( $url );
				if($url_parts===FALSE || !array_key_exists ('host', $url_parts)) {
						continue;
				}
				if ( stripos( $url_parts['host'], ".amazon." ) !== false ) {

					$innerhtml = str_get_html( $form->innertext );
					foreach ( $innerhtml->find( 'input' ) as $input ) {
						if ( stripos( $input->name, "AssociateTag" ) !== false ) {
							$tag = $input->value;
						}
						if ( stripos( $input->name, "ASIN" ) !== false ) {

							// get default region $options['region']
							$options = get_option( 'azlc_plugin_options', array( 'region' => 'com' ) );

							// create url since the url for the form doesn't contain the asin
							$url_custom = "http://www.amazon." . $options['region'] . "/dp/" . $input->value;
							if ( isset( $tag ) ) {
								$url_custom .= "?tag=" . $tag;
							}

							$data[ $i ]['url']       = $url_custom;
							$data[ $i ]['link_text'] = "";
							$data[ $i ]['link_code'] = "";
							$data[ $i ]['link_type'] = "form";
						}
					}
					$i ++;
				}

			}


			return $data;
		}

		function getFinalUrl($url) {
			$userAgent= 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101
	Safari/537.36';

			$options = array(CURLOPT_URL => $url,
			                 CURLOPT_RETURNTRANSFER => TRUE,
							CURLOPT_USERAGENT => $userAgent,
							CURLOPT_HEADER => true);



			$finalUrl = $this->curl_get_final_url($options);

			return urldecode($finalUrl);
		}


		/**
		 * @param array $curlOptions
		 * @param array $curlHeaders
		 * @param array $postFields
		 *
		 * @return mixed
		 */
		function curl_get_final_url($curlOptions='', $curlHeaders='', $postFields='') {
			$newUrl = '';
			$maxRedirection = 10;
			do {
				if ($maxRedirection<1)  {
					return '';
				}
				$ch = curl_init();
				if (!empty($curlOptions)) curl_setopt_array($ch, $curlOptions);
				if (!empty($curlHeaders)) curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
				if (!empty($postFields)) {
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
				}

				if (!empty($newUrl)) curl_setopt($ch, CURLOPT_URL, $newUrl); // redirect needed

				$curlResult = curl_exec($ch);
				$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				if ($code == 301 || $code == 302 || $code == 303 || $code == 307)
				{
					preg_match('/Location:(.*?)\n/', $curlResult, $matches);
					$newUrl = trim(array_pop($matches));
					curl_close($ch);

					$maxRedirection--;
					continue;
				}
				else // no more redirection
				{
					$code = 0;
					curl_close($ch);
				}
			}
			while($code);
			return $newUrl;
		}
	}

endif;