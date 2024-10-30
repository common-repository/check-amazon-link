// azlc version 1.1.4
jQuery(document).ready(function () {
            var
                status_area = jQuery("#azlc_status"),
                status_area_2 = jQuery("#azlc_status2"),
                totalAZLinks = 0,
                parsed_post_count = 0,
                link_count = 0,
                relookup = 0,
                $body = jQuery('body'),
                table_column_sorted_by = '',
                table_column_sort_order = '',
                $links_table = jQuery('#azlc_link_instances_section');

            jQuery(document).on("change", "#azlc_num_of_rows", request_table_update);
            jQuery(document).on("change", "#azlc_page_number", request_table_update);

            jQuery("#azlc_refresh_table").click(function (event) {
                request_table_update(0, table_column_sorted_by, table_column_sort_order);
                return false;
            });


            $body.on('click', '#azlc_prev_page', function () {
                request_table_update(-1, table_column_sorted_by, table_column_sort_order);
                return false;
            });

            $body.on('click', '#azlc_next_page', function () {
                request_table_update(1, table_column_sorted_by, table_column_sort_order);
                return false;
            });

            $body.on('click', 'table#az_link_instances th', function () {
                var $this = jQuery(this);
                var this_id = $this.attr('id');
                var this_sort_order = $this.attr('sort_order');

                if (typeof this_sort_order === 'undefined') {
                    this_sort_order = 'ASC'
                } else {
                    if (this_sort_order === 'ASC') {
                        this_sort_order = 'DESC'
                    } else {
                        this_sort_order = 'ASC'
                    }
                }

                var this_sort_column = '';
                if (this_id === 'post_header') {
                    this_sort_column = 'post_title';
                } else if (this_id === 'product_header') {
                    this_sort_column = 'title';
                } else if (this_id === 'stock_header') {
                    this_sort_column = 'fuzzy_status'
                } else if (this_id === 'tag_header') {
                    this_sort_column = 'affiliate_tag'
                } else if (this_id === 'time_header') {
                    this_sort_column = 'time_of_retrieval'
                }

                request_table_update(0, this_sort_column, this_sort_order);

            });



            function request_table_update(which_page, sort_column, sort_order) {
                which_page = which_page || 0;
                sort_column = sort_column || table_column_sorted_by;
                sort_order = sort_order || table_column_sort_order;
                var r = jQuery('#azlc_num_of_rows').val();
                var $page_number_field = jQuery('#azlc_page_number');
                var p;
                if ($page_number_field.length) {
                    p = Number($page_number_field.val()) + which_page;
                } else {
                    p = 1;
                }
                var datatosend = {
                    'action': 'azlc_linksTable',
                    'azlc_linksTableOptions': {
                        'num_of_rows': r,
                        'page_num': p,
                        first_sort_column: sort_column,
                        first_sort_order: sort_order,
                        // jQuery.extend function is used to get a copy of the value
                        second_sort_column: JSON.stringify(table_column_sorted_by),
                        second_sort_order: JSON.stringify(table_column_sort_order)
                    }
                };

                //update sort variables
                table_column_sorted_by = sort_column;
                table_column_sort_order = sort_order;
                jQuery('#azlc_loading').show();
                jQuery.post(ajaxurl, datatosend, loadLinksTable);
            }


            function loadLinksTable(data) {
                jQuery('#azlc_loading').hide();
                $links_table.html(data);
                var newTableObject = document.getElementById('az_link_instances');

            }

});


jQuery(document).ready(function () {
    var interval = 0;
    var doajax = false;
    var mytimer;
    function sendpost () {
        var d = {
            'action': 'azlc_ajax_controller'
        };

        if(typeof post_id !== 'undefined') {
            d.azlc_pid = post_id;
        }
        jQuery.post(ajaxurl, d, callbackforall);
    }

    sendpost();

    function callbackforall(data) {

        var x = JSON && JSON.parse(data) || jQuery.parseJSON(data);
        az_check(x);
        if (typeof (x.success) !== 'undefined') {
            if (x.success == 1) {
                doajax = true;

                if (("interval" in x) && jQuery.isNumeric(x.interval)) {

                    if (interval !== x.interval) {
                        // if the interval hasn't changed, don't reset this
                        interval = x.interval;
                        if (typeof mytimer !== undefined) {
                            //todo: FIX this will always be called... it is not undefined!?

                            clearInterval(mytimer);
                        }

                        mytimer = setInterval(sendpost, x.interval * 1000);
                    }
                }
            } else {

                clearInterval(mytimer);
                // try again in 1 minute
                mytimer = setInterval(sendpost, 60 * 1000);
            }

        } else {

        }
    }

    function az_check(x) {
        var $az_check = jQuery("#az_check");
        if ($az_check.length) {

            if ("az" in x) {
                if (x.az == 1) {
                    $az_check.html("<p class='az_success'>&#x2713 Your Amazon API Credentials are working!  Just give this plugin some time to find & check your links.</p>");
                } else {
                    $az_check.html("<p class='az_error'>&#x2717 A problem has been detected.  " +
                        "Please verify that you <a href='options-general.php?page=amazon_link_checker_menu_settings'>Amazon API Credentials are set correctly.</a></p>");
                }
            }
        }
    }

});


jQuery(document).ready(function () {
    var option_page_name = jQuery('input[name=option_page]').val();
    if (typeof(option_page_name) != "undefined") {
        if(option_page_name === 'amazon_link_plugin_options') {
            jQuery('form').submit(function(e) {
                var update_all = jQuery('select[name="azlc_multisite_same[toggle]"]').val();
                if(update_all === '1') {
                    jQuery.post(ajaxurl, 'action=azlc_ms', function(){});
                }

            });
        }
    }

});