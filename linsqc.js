jQuery( document ).ready(function() {


    var $box = jQuery("#linsoftware_qc"), $yesButton = jQuery('#lsqc_1'), $noButton = jQuery('#lsqc_0'), $maybeButton = jQuery('#lsqc_3'), $sendReportButton = jQuery('#lsqc_r'), $closeLink = jQuery('#lsqc_cl');

    var info = {'action': 'linsoftware_qc', 'lsw_stage': 'initial'};

    jQuery.post(ajaxurl, info, lsqc_callback);


    jQuery(document).on('click', '#lsqc_1', function () {
        jQuery('#lsqc_1').prop('disabled', true);
        info.lsw_stage = 'respond_yes';
        jQuery.post(ajaxurl, info, lsqc_callback);
    });

    jQuery(document).on('click', '#lsqc_0', function () {
        jQuery('#lsqc_0').prop('disabled', true);
        info.lsw_stage = 'respond_no';
        jQuery.post(ajaxurl, info, lsqc_callback);
    });

    jQuery(document).on('click', '#lsqc_3', function() {
        jQuery('#lsqc_3').prop('disabled', true);
        info.lsw_stage = 'respond_maybe';
        jQuery.post(ajaxurl, info, lsqc_callback);
    });

    jQuery(document).on('click', '#lsqc_r', function() {
        jQuery('#lsqc_r').prop('disabled', true);
        console.log("error report submit button clicked");
        info.lsw_stage = 'submit_report';
        if(jQuery('#ls_anon').prop('checked')) {
            info.lsw_anon = 1;
        } else {
            info.lsw_anon = 0;
        }
        jQuery.post(ajaxurl, info, lsqc_callback);
    });

    jQuery(document).on('click', '#linqc_close', function(){
        $box.hide();
    });


    function lsqc_callback(data) {
        var jdata = jQuery.parseJSON(data);
        $box.html(jdata.html);
    }


});

