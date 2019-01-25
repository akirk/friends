jQuery( function( $ ) {

	jQuery( document ).on( 'click', 'a#toggle-raw-rules-data', function() {
		jQuery( '#raw-rules-data' ).toggle();
		return false;
	} );

	jQuery( document ).on( 'change', 'select.rule-action', function() {
		var td_replace = jQuery( this ).closest( 'tr' ).find( 'td.replace-with' );
		if ( 'replace' === jQuery( this ).val() ) {
			td_replace.show();
		} else {
			td_replace.hide();
		}
	} );

	jQuery( document ).on( 'click', 'button#refresh-preview-rules', function() {
		jQuery.post( friends.ajax_url + '?user=' + $( this ).data( 'id' ), $( 'form#edit-rules [name^=rules]' ).add( 'form#edit-rules [name=catch_all]' ).serialize() + '&_ajax_nonce=' + $( this ).data( 'nonce' ) + '&action=friends_preview_rules', function( response ) {
			jQuery( '#preview-rules' ).html( response );
		} );
		return false;
	} );

	jQuery( '<a href="' + friends.add_friend_url + '" class="page-title-action">' + friends.add_friend_text + '</a>' ).insertAfter( 'a.page-title-action[href$="user-new.php"]' );

} );
