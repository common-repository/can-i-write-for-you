// http://codex.wordpress.org/Function_Reference/wp_enqueue_script#jQuery_noConflict_wrappers
jQuery(document).ready(function($) {
	
	function init() {
		var msg_enable_checkbox = $('.ciw_msg_enable input[type=checkbox]');
		var msg_enable_status = msg_enable_checkbox.attr('checked');
		if (msg_enable_status == undefined) {
			msg_enable_checkbox.closest('.row').siblings('.ciw_msg_required').hide();
		}
	}
	
	$('.ciw_msg_enable input[type=checkbox]').change(function() {
		if ($(this).attr('checked')) {
			$(this).closest('.row').siblings('.ciw_msg_required').slideDown();
		} else {
			$(this).closest('.row').siblings('.ciw_msg_required').slideUp();
		}
	});
	
	init();
});