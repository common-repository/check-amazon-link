<?php
/**
 * Created by PhpStorm.
 * User: Linnea
 * Date: 5/31/2015
 * Time: 9:41 AM
 */

if ( ! defined( 'ABSPATH' ) ) {exit();} // Exit if accessed directly


require_once( "AZLC_Logger.php" );

if ( ! class_exists( 'AZLC_Utility' ) ) :

class AZLC_Utility {


	/**
* @param int $num_of_rows
* @param int $offset
* @param string $first_column_to_sort_by
* @param string $first_column_order
* @param string $second_column_to_sortby
* @param string $second_column_order
*/static function load_sorted_links_table($num_of_rows = 1000, $offset = 0, $first_column_to_sort_by = null, $first_column_order = null, $second_column_to_sortby = null, $second_column_order = null) {
		global $wpdb, $azlc_database, $azlc_logger;

		$offset_safe = filter_var( $offset, FILTER_SANITIZE_NUMBER_INT );
		$num_of_rows_safe = filter_var( $num_of_rows, FILTER_SANITIZE_NUMBER_INT );

		$possible_columns_to_sort_by = array(
			'post_title',
			'title',
			'affiliate_tag',
			'fuzzy_status',
			'time_of_retrieval'
		);

		// ***************** SET SORTING DEFAULTS **************************** //
		// if $first_column_to_sort_by is not a valid choice
		// then automatically sort it by fuzzy_status

		// first check to see if the first and second columns to sort by are the same
		// if they are the same, this will cause problems, so fix:
		if(strcmp($first_column_to_sort_by, $second_column_to_sortby)===0) {
			// they are the same, so set second to null
			$second_column_to_sortby = null;
		}

		if (array_search($first_column_to_sort_by, $possible_columns_to_sort_by) === false) {
			$first_column_to_sort_by = 'fuzzy_status';
			$azlc_logger->write("first column not specified, using default");
		}
		if(strcmp($first_column_order, 'ASC')!==0 && strcmp($first_column_order, 'DESC')!==0 ) {
			$first_column_order = 'DESC';
		}

		if(array_search($second_column_to_sortby, $possible_columns_to_sort_by) === false) {
			$second_column_to_sortby = strcmp( ( $second_column_to_sortby ), 'time_of_retrieval' ) === 0 ? 'fuzzy_status' : 'time_of_retrieval';
		}

		if(strcmp($second_column_order, 'ASC')!==0 && strcmp($second_column_order, 'DESC')!==0 ) {
			$second_column_order = 'DESC';
		}


		$order_by_clause = ' ';

		if(isset($first_column_to_sort_by) && isset($first_column_order)) {
				$order_by_clause .= "ORDER BY " . $first_column_to_sort_by . " " . $first_column_order;
			if(isset($second_column_to_sortby) && isset($second_column_order)) {
				$order_by_clause .= ", " . $second_column_to_sortby . " " . $second_column_order;
			}
		}

		/* @var $wpdb WPDB */

		// get total number of link instances
		$count_of_all_link_instances = $wpdb->get_var("SELECT count(DISTINCT id) FROM " . $azlc_database->link_instances_table);

		// get the total number of products looked up
		$num_of_looked_up_products_7day = $wpdb->get_var("SELECT count(DISTINCT asin) FROM "  . $azlc_database->product_data_table ."
WHERE time_of_retrieval >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

		// get the total number of unique products
		$num_of_products = $wpdb->get_var("SELECT count(DISTINCT asin) FROM "  . $azlc_database->link_instances_table);

		if($num_of_products>0) {
		$percentage = ($num_of_looked_up_products_7day / $num_of_products) * 100;
		} else {
		$percentage = 0;
		}
		$internal_options_array = get_option( 'azlc_plugin_options_internal' );
			$still_parsing =  $internal_options_array['pages_parsed'] == 0 ? true : false;
		// put together a sentence showing these stats
		$stats = sprintf("Current Status: %.0f%% of your products have been price checked in the last 7 days
 <br>(%d of %d unique products that you linked to.)", $percentage,
	$num_of_looked_up_products_7day, $num_of_products);
	if($still_parsing) {
	$stats .= ' This plugin is still working on parsing your posts.';
	}

		$link_instances_unique = $wpdb->get_results( "SELECT * FROM (SELECT li.id AS id, li.post_id AS post_id, li.asin AS asin, li.affiliate_tag AS affiliate_tag,
li.post_title AS post_title,
li.region AS region,
 pr.title AS title, pr.product_group AS product_group, pr.abstract AS abstract,
 TotalNew, TotalUsed, TotalCollectible, TotalRefurbished,
	LowestUsedPrice, LowestNewPrice, LowestCollectiblePrice, LowestRefurbishedPrice, error_code, error_message, time_of_retrieval, stock_status AS fuzzy_status
	FROM  " . $azlc_database->link_instances_table . " li LEFT JOIN " . $azlc_database->product_table . " pr ON li.asin=pr.asin
	LEFT JOIN " . $azlc_database->product_data_table . " pdt ON pdt.asin=pr.asin ORDER BY time_of_retrieval DESC) temp_table
	GROUP BY id " . $order_by_clause   . " LIMIT " . $offset_safe . ", " . $num_of_rows_safe, ARRAY_A );


		//get column sorty by position

		$first_column_key = array_search($first_column_to_sort_by, $possible_columns_to_sort_by);
		$second_column_key = array_search($second_column_to_sortby, $possible_columns_to_sort_by);

		// ****** DONE SORTING ARRAY *****//


		?>

		<div id="amz_table_pages">

			<form id="amz_table_options" action="">

			<p><?php echo( $stats ); ?></p>
				<h3>


					<?php echo( $count_of_all_link_instances ); ?> Links Found
					<?php if($count_of_all_link_instances==0) {echo " - Check back in 10 Minutes";}
					?>
					<?php



					if ( isset( $num_of_rows_safe ) && $num_of_rows_safe > 0 && $count_of_all_link_instances > $num_of_rows_safe ) {
						$number_of_pages = ceil( $count_of_all_link_instances / $num_of_rows_safe );
						$page_number     = $offset_safe / $num_of_rows_safe + 1;
					} else {

						$number_of_pages = - 1;
						$page_number     = - 1;
					}

					$show_previous_link = $page_number != - 1 && $page_number > 1 ? true : false;
					$show_next_link     = $page_number != - 1 && $page_number != $number_of_pages ? true : false;

					echo " | "; ?>
					<select id="azlc_num_of_rows" name="num_of_rows">
						<option value="25"  <?php if ( $num_of_rows_safe == 25 ) {
							echo " selected";
						} ?> >25
						</option>
						<option value="50"  <?php if ( $num_of_rows_safe == 50 ) {
							echo " selected";
						} ?> >50
						</option>
						<option value="100"  <?php if ( $num_of_rows_safe == 100 ) {
							echo " selected";
						} ?> >100
						</option>
						<option value="200" <?php if ( $num_of_rows_safe == 200 ) {
							echo " selected";
						} ?> >200
						</option>
						<option value="1000" <?php if ( $num_of_rows_safe == 1000 ) {
							echo " selected";
						} ?> >1000
						</option>
					</select>


					<?php echo " Results per Page ";
					if ($page_number != - 1 && $number_of_pages != - 1) {
					echo "|  Showing page ";

					?>
					<select name="page_number" id="azlc_page_number">
						<?php for ( $i = 1; $i <= $number_of_pages; $i ++ ) {
							echo "<option value=" . $i;

							if ( $page_number == $i ) {
								echo " selected";
							}

							echo ">" . $i . "</option>";
						}
						echo " </select>";

						echo " of " . $number_of_pages;
						}
						echo "<div id=\"azlc_top_table_nav\">";
						AZLC_Utility::do_table_page_links( $show_previous_link, $show_next_link );
						echo "</div>";
						?>
			</form>
			</h3></div>
		<div id="az_table_container">
		<table id="az_link_instances" style="width:100%">

		<colgroup>
			<col class="post">
			<col class="product">
			<col class="stock">
			<col class="tag">
			<col class="checked">

		</colgroup>


		<tr>
			<th id="post_header"  <?php echo AZLC_Utility::add_sort_order_attr(0, $first_column_key,  $first_column_order, $second_column_key, $second_column_order) ?>
				>Post/Page Title</th>
			<th id="product_header" <?php echo AZLC_Utility::add_sort_order_attr(1, $first_column_key,  $first_column_order, $second_column_key, $second_column_order) ?>
				>Product Title</th>
			<th id="stock_header"  <?php echo AZLC_Utility::add_sort_order_attr(3, $first_column_key,  $first_column_order, $second_column_key, $second_column_order) ?>
				>Stock Status</th>
			<th id="tag_header"  <?php echo AZLC_Utility::add_sort_order_attr(2, $first_column_key,  $first_column_order, $second_column_key, $second_column_order) ?>
				>Affiliate Tag</th>
			<th id="time_header"  <?php echo AZLC_Utility::add_sort_order_attr(4, $first_column_key,  $first_column_order, $second_column_key, $second_column_order) ?>
				>Last Checked</th>
		</tr>

		<?php



		foreach ( $link_instances_unique as $value ) {

			// change it to an object so that I don't have to fix the rest of this code!
			$value  = (object) $value;

			echo "<tr>";
			if(isset($value->post_id)) {
				echo "<td><a href='" . get_site_url() . "?p=" . $value->post_id .
				"' target='_blank'>$value->post_title</a></td>";
			} else {
				echo "<td></td>";
			}
			echo "<td>";
			$url_safe = esc_url("http://www.amazon." . $value->region . "/gp/product/" . $value->asin);
			if (empty( $value->title ) ) {
				if(isset($value->asin) & isset($value->region)) {
					echo '<A href="'. $url_safe . '" target="_blank">' . esc_html($value->asin) . "</a></td>";
				} else { 
					echo "";
				}
			} else {
				echo '<A href="'. $url_safe . '" target="_blank">' . esc_html($value->title) . "</a></td>";
			}

			if(isset($value->fuzzy_status)) {
				echo "<td " . self::addClasses( $value ) . ">" . esc_html($value->fuzzy_status);

				if(isset($value->abstract)) {
					if($value->abstract==1) {
						echo "<sup> *</sup>";
					}
				}

				echo " </td>";
			} else {
				echo "<td></td>";
			}

			$tag = ! empty( $value->affiliate_tag ) ? esc_html($value->affiliate_tag) : "none";
			echo "<td>" . $tag . "</td>";

			$fuzzy_time_updated = ! isset( $value->time_of_retrieval ) ? "Never" : AZLC_Utility::fuzzy_delta( time() - strtotime( $value->time_of_retrieval ), 'ago' );
			echo "<td>" . esc_html($fuzzy_time_updated) . "</td>";
			echo "</tr>";
			$link_instances_already_displayed[] = $value->id;

			}

		echo "</table>";
		echo "<div id='azlc_loading'> <h3>Refreshing Table...</h3> <img src='" . plugins_url( 'images/ajax-loader.gif', __FILE__ ) ."'></div>";
		echo "</div>";

		echo "<div id=\"azlc_bottom_table_nav\"> <h3>";
		AZLC_Utility::do_table_page_links( $show_previous_link, $show_next_link );
		echo "</h3></div>";
		echo "<P id='azlc_footer'>* An asterisk next to the words 'No Data' indicate that your link points to a product with an abstract or parent ASIN.  If you would like the stock status checked, replace
  this link with a link for a specific variation of the product.  (Choose a color, size, or other option, and link to that.)  For more info,
   <A href='http://docs.aws.amazon.com/AWSECommerceService/latest/DG/RG_VariationSummary.html'>see Amazon's page.</a></P>";
echo "<P></P>";


	}


	static function do_table_page_links( $show_previous_link, $show_next_link ) {
		if ( $show_previous_link ) {
			?> [<a href="#" id="azlc_prev_page">Previous Page</a>] <?php
		}
		if ( $show_next_link ) {
			?> [<a href="#" id="azlc_next_page">Next Page</a>] <?php
		}
	}

	public static function fuzzy_stock_status( $data) {

		$data = (array) $data;
			if(isset($data['abstract'])) {
				if ( $data['abstract'] == 1 ) {
					// the leading space is intentional, to help with sort order
					return " No Data";
				}
			}

			if ( isset( $data['TotalNew'] ) ) {
				if ( $data['TotalNew'] > 0 ) {
					return 'In Stock';
				}
			}

			if ( isset( $data['TotalUsed'] ) && isset( $data['TotalCollectible'] ) && isset( $data['TotalRefurbished'] ) ) {
				if ( $data['TotalUsed'] > 0 ||  $data['TotalCollectible']  > 0 || $data['TotalRefurbished'] > 0 ) {
					return "Limited";
				} else {
					return "Out of Stock";
				}
			} else {
				// the space is intentional, to help with sort order
				return " No Data";
			}



	}


	/**
	 * Format a time delta using a fuzzy format, e.g. '2 minutes ago', '2 days', etc.
	 *
	 * @param int $delta Time period in seconds.
	 * @param string $template Optional. The output template to use.
	 *
	 * @return string
	 */
	static function fuzzy_delta( $delta, $template = 'default' ) {
		$ONE_MINUTE = 60;
		$ONE_HOUR   = 60 * $ONE_MINUTE;
		$ONE_DAY    = 24 * $ONE_HOUR;
		$ONE_MONTH  = $ONE_DAY * 3652425 / 120000;
		$ONE_YEAR   = $ONE_DAY * 3652425 / 10000;

		$templates = array(
			'seconds' => array(
				'default' => _n_noop( '%d second', '%d seconds' ),
				'ago'     => _n_noop( '%d second ago', '%d seconds ago' ),
			),
			'minutes' => array(
				'default' => _n_noop( '%d minute', '%d minutes' ),
				'ago'     => _n_noop( '%d minute ago', '%d minutes ago' ),
			),
			'hours'   => array(
				'default' => _n_noop( '%d hour', '%d hours' ),
				'ago'     => _n_noop( '%d hour ago', '%d hours ago' ),
			),
			'days'    => array(
				'default' => _n_noop( '%d day', '%d days' ),
				'ago'     => _n_noop( '%d day ago', '%d days ago' ),
			),
			'months'  => array(
				'default' => _n_noop( '%d month', '%d months' ),
				'ago'     => _n_noop( '%d month ago', '%d months ago' ),
			),
		);

		if ( $delta < 1 ) {
			$delta = 1;
		}

		if ( $delta < $ONE_MINUTE ) {
			$units = 'seconds';
		} elseif ( $delta < $ONE_HOUR ) {
			$delta = intval( $delta / $ONE_MINUTE );
			$units = 'minutes';
		} elseif ( $delta < $ONE_DAY ) {
			$delta = intval( $delta / $ONE_HOUR );
			$units = 'hours';
		} elseif ( $delta < $ONE_MONTH ) {
			$delta = intval( $delta / $ONE_DAY );
			$units = 'days';
		} else {
			$delta = intval( $delta / $ONE_MONTH );
			$units = 'months';
		}

		return sprintf(
			_n(
				$templates[ $units ][ $template ][0],
				$templates[ $units ][ $template ][1],
				$delta
			),
			$delta
		);
	}


	/**
	 *
	 * helper function - used in creating the tools page table
	 * adds class names based on the database object's fields
	 *
	 * @param $object
	 *
	 * @return string
	 */
	public static function addClasses( $object ) {
		$classes = 'class = "stock_status';
		if(strcmp($object->fuzzy_status, 'Out of Stock')===0) {
			$classes .= ' out_of_stock';
		} elseif (strcmp($object->fuzzy_status, 'Limited')===0) {
			$classes .=  ' limited_stock';
		}
		$classes .= '"';

		return $classes;
	}



	private static function add_sort_order_attr($header_number, $first_column_key,  $first_column_order, $second_column_key, $second_column_order) {
		if ( $first_column_key === $header_number ) {
			return " sort_order='" . $first_column_order . "' ";
		} else if ( $second_column_key === $header_number ) {
			return " sort_order='" . $second_column_order . "' ";
		} else {
			return "";
		}
	}

	//todo: should this be cached and for how long?
	// maybe cache as an option that only gets updated once a day,
	// or gets updated when table loads
	public static function get_count_of_out_of_stock_links() {
		global $wpdb, $azlc_database;

		$query = "SELECT count(*) FROM (SELECT * FROM (SELECT li.id AS id, li.post_id AS post_id, li.asin AS asin, li.affiliate_tag AS affiliate_tag,
li.post_title AS post_title,
li.region AS region,
 pr.title AS title, pr.product_group AS product_group, pr.abstract AS abstract,
 TotalNew, TotalUsed, TotalCollectible, TotalRefurbished,
	LowestUsedPrice, LowestNewPrice, LowestCollectiblePrice, LowestRefurbishedPrice, error_code, error_message, time_of_retrieval, stock_status AS fuzzy_status
	FROM  " . $azlc_database->link_instances_table . "  li LEFT JOIN  " . $azlc_database->product_table . "  pr ON li.asin=pr.asin
	LEFT JOIN  " . $azlc_database->product_data_table . " pdt ON pdt.asin=pr.asin ORDER BY time_of_retrieval DESC) temp_table
	GROUP BY id) temp_table2 WHERE fuzzy_status LIKE 'Out of Stock'";
	/* @var $wpdb WPDB */
		return $wpdb->get_var($query);

		}


	/**
	 * This function deletes unnecessary rows from the product_data_table
	 * unnecessary rows are rows where the prices haven't changed at all
	 * or they've only changed by less than $1
	 * We want to save historical price data so
     * it can be used to display price charts to be used by a future plugin
	 *
*@param $asin
	 */
	public static function clean_data_table($asin) {
		/* @var $wpdb WPDB */
		global $wpdb, $azlc_logger, $azlc_database;


		// First check if we have a minimum number of rows for that asin

		$query = $wpdb->prepare("SELECT count(*) FROM $azlc_database->product_data_table WHERE asin = %s", $asin);
		/* @var $wpdb WPDB */
		$count = $wpdb->get_var($query);



		if($count<5) { // not enouch results to worry about cleaning database
			$azlc_logger->write("clean data table found only " . $count . " rows for asin " . $asin ." so we are skipping the cleaning process");
			return;
		}

		// delete rows where price hasn't changed
		$query = $wpdb->prepare("SELECT id, LowestUsedPrice, LowestCollectiblePrice, LowestRefurbishedPrice, LowestNewPrice
		         FROM $azlc_database->product_data_table WHERE asin = %s ORDER BY time_of_retrieval ASC", $asin);
		$results = $wpdb->get_results($query, ARRAY_A);
		$ids_of_rows_to_delete = array();
		$oldusedprice=-1;
		$oldcollprice = -1;
		$oldrefurbprice =-1;
		$oldnewprice = -1;

		foreach($results as $row) {

			//ignore pennies on items over 100 (which really means one dollar)
			if((int) $row['LowestUsedPrice'] >100 ) {
				$row['LowestUsedPrice'] = substr($row['LowestUsedPrice'], 0, -2);
			}
			if((int) $row['LowestCollectiblePrice'] >100 ) {
				$row['LowestCollectiblePrice'] = substr($row['LowestCollectiblePrice'], 0, -2);
			}
			if((int) $row['LowestRefurbishedPrice'] >100 ) {
				$row['LowestRefurbishedPrice'] = substr($row['LowestRefurbishedPrice'], 0, -2);
			}
			if((int) $row['LowestNewPrice'] >100 ) {
				$row['LowestNewPrice'] = substr($row['LowestNewPrice'], 0, -2);
			}
				if( (int) $row['LowestUsedPrice'] == $oldusedprice &&
				    (int) $row['LowestCollectiblePrice'] == $oldcollprice &&
				    (int) $row['LowestRefurbishedPrice'] == $oldrefurbprice &&
				    (int) $row['LowestNewPrice'] == $oldnewprice ) {
					$ids_of_rows_to_delete[] = $row['id'];
				}

			$oldnewprice = $row['LowestNewPrice'];
			$oldcollprice =  $row['LowestCollectiblePrice'];
			$oldrefurbprice = $row['LowestRefurbishedPrice'];
			$oldusedprice =  $row['LowestUsedPrice'];
		}
		if(!empty($ids_of_rows_to_delete)) {
			$string_of_rows_to_delete = implode( ", ", $ids_of_rows_to_delete );

			$delete_query = "DELETE FROM $azlc_database->product_data_table  WHERE id IN (" . esc_sql($string_of_rows_to_delete) . ")";
			$wpdb->query( $delete_query );
			$azlc_logger->write( "clean_data_table deleted the following number of rows: " . $wpdb->num_rows . " for asin: " . $asin );
		} else {
			$azlc_logger->write( "clean_data_table found NO rows to delete for: "  . $asin );
		}
	}
}

endif;