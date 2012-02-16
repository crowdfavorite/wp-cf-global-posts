jQuery(function($){
	$("#reset_shadow_blog_button").click(function(){
		var confirmation = confirm(CFGPAdminJs.langAreUSure);
		if (confirmation) {
			if(confirm(CFGPAdminJs.langResettingNow)){
			$.post(
				CFGPAdminJs.ajaxEndpoint,
				{
					cf_action: "reset_entire_shadow_blog"
				},
				function(r){
					if (r.success == "true") {
						alert(CFGPAdminJs.langResetSuccess);
						document.location = document.location;
					}
					else {
						alert(CFGPAdminJs.langResetError);
					}
				},
				'json'
			);
			}
			else {
				alert(CFGPAdminJs.langResetCancel);
			}
		}
		else {
			alert(CFGPAdminJs.langResetCancel);
		}
	});
});

jQuery(function($) {
	var ajaxSpinner = '<div class="ajax-spinner"><img src="images/loading.gif" style="margin: 0 6px 0 0; vertical-align: middle" /> <span class="ajax-loading">' + CFGPAdminJs.langProcessing + '</span></div>';
	var originalBGColortr = jQuery("#blogrow-1");
	var originalBGColor = originalBGColortr.children("td:first").css("backgroundColor");
	import_box = $("#doing-import");
	import_box.hide();
	
	import_buttons = $("button[id^='start_import_blog_']");
	import_all_button = $("button[id^='start_import_all_blogs']");
	
	import_buttons.click(function(){
		//$(document).scrollTop(0);
		blogId = $(this).siblings("input[name='blog_id']").val();
		import_buttons.attr('disabled','disabled');
		var start_tr = jQuery("#blogrow-"+blogId);
		start_tr.children("td").css({backgroundColor:"#FAEDC3"});
		jQuery('#status-'+blogId).html(ajaxSpinner);
		do_batch(blogId, 0);
		//import_box.show().removeClass('updated fade').children('h3').text('Import in progress, do not navigate away from this page...').siblings("#import-ticks").text('#');
		return false;
	});

	import_all_button.click(function() {
		import_buttons.attr('disabled','disabled');
		blogIds = $("#all_blog_ids").val().split(',');
		for (var i in blogIds) {
			var blogId = blogIds[i];
			var start_tr = jQuery("#blogrow-"+blogId);
			start_tr.children("td").css({backgroundColor:"#FAEDC3"});
			jQuery('#status-'+blogId).html(ajaxSpinner);
			do_batch(blogId, 0);
		}
		return false;
	});

	function do_batch(blogId, offset_amount) {
		$.post(
			CFGPAdminJs.ajaxEndpoint,
			{
				cf_action:'add_blog_to_shadow_blog',
				blog_id: blogId,
				offset: offset_amount
			},
			function(r){
				if (r.status == 'finished') {
					//import_box.addClass('updated fade').children('h3').text('Finished Importing!').siblings("#import-ticks").text('');
					import_buttons.removeAttr('disabled');
					var finished_tr = jQuery("#blogrow-"+blogId);
					finished_tr.children("td").css({backgroundColor:originalBGColor});				
					jQuery('#status-'+blogId).html(CFGPAdminJs.langComplete);
					return;
				}
				else {
					//import_box.children("#import-ticks").text(import_box.children("#import-ticks").text()+' # ');
					do_batch(blogId, r.next_offset);
				}
			},
			'json'
		);
	}
});
