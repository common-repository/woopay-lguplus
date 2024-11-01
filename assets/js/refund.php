<?php
$woopay_plugin_name = $_REQUEST[ 'woopay_plugin_name' ];
?>
jQuery( '.<?php echo $woopay_plugin_name; ?>-cancel' ).click( function() {
	var ask_msg = confirm( jQuery( '#<?php echo $woopay_plugin_name; ?>-ask-refund-msg' ).val() );

	if ( ! ask_msg ) {
		return false;
	}
});

jQuery( '.inicis-confirm-delivery' ).click( function() {

	var href = jQuery(this).attr('href');
	var prompt_msg = prompt( jQuery( '#prompt-confirm-msg' ).val(), "" );
	prompt_msg = prompt_msg.replace(/\D/g, '');

	if ( prompt_msg.length > 0 ) {
		href = href + '&BuyerAuthNum=' + prompt_msg;
		jQuery(this).attr('href', href);
	} else {
		return false;
	}
});

jQuery( '.inicis-escrow-cancel' ).click( function() {
	var ask_msg = confirm( jQuery( '#ask-decline-msg' ).val() );

	if ( ! ask_msg ) {
		return false;
	} else {
		var href = jQuery(this).attr('href');
		var prompt_msg = prompt( jQuery( '#prompt-confirm-msg' ).val(), "" );
		prompt_msg = prompt_msg.replace(/\D/g, '');

		if ( prompt_msg.length > 0 ) {
			href = href + '&BuyerAuthNum=' + prompt_msg;
			jQuery(this).attr('href', href);
		} else {
			return false;
		}
	}
});