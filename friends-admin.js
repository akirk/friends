jQuery( function( $ ) {

	jQuery( document ).on( 'click', 'input#require_codeword', function() {
		if ( this.checked ) {
			jQuery( '#codeword_options' ).removeClass( 'hidden' );
			jQuery( '#codeword' ).focus();
		} else {
			jQuery( '#codeword_options' ).addClass( 'hidden' );
		}
	} );

	var welcomePanel = $( '#friends-welcome-panel' ),
		updateWelcomePanel;

	updateWelcomePanel = function( visible ) {
		$.post( ajaxurl, {
			action: 'update-welcome-panel',
			visible: visible,
			welcomepanelnonce: $( '#welcomepanelnonce' ).val()
		});
	};


	// Hide the welcome panel when the dismiss button or close button is clicked.
	$('.welcome-panel-close, .welcome-panel-dismiss a', welcomePanel).click( function(e) {
		e.preventDefault();
		welcomePanel.addClass('hidden');
		updateWelcomePanel( 0 );
	});

	jQuery( document ).on( 'click', 'a#send-friends-advanced', function() {
		jQuery( 'tr.friends-advanced' ).removeClass( 'hidden' ).first().find( 'input:visible:first' ).focus();
		jQuery( this ).remove();
		return false;
	} );

	jQuery( document ).on( 'click', 'a#add-another-feed', function() {
		jQuery( 'tr.another-feed' ).removeClass( 'hidden' ).find( 'input:visible:first' ).focus();
		jQuery( this ).remove();
		return false;
	} );

	jQuery( document ).on( 'click', 'a#show-details', function() {
		jQuery( '.details' ).toggleClass( 'hidden' ).focus();
		return false;
	} );

	jQuery( document ).on( 'click', 'a#show-alternate-feeds', function() {
		jQuery( 'li.rel-alternate' ).toggleClass( 'hidden' );
		return false;
	} );

	jQuery( document ).on( 'click', 'a#show-unsupported-feeds', function() {
		jQuery( '#unsupported-feeds' ).toggleClass( 'hidden' );
		return false;
	} );

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

	jQuery( document ).on( 'click', 'a.preview-parser', function() {
		this.href = this.href.replace( /&parser(=[^&$]+)?([&$])/, '&parser=' + encodeURIComponent( jQuery( this ).closest( 'td' ).find( 'select' ).val() ) + '&' );
		this.href = this.href.replace( /&preview(=[^&$]+)?([&$])/, '&preview=' + encodeURIComponent( jQuery( this ).closest( 'tr' ).find( 'input.url' ).val() ) + '&' );
	} );

	jQuery( document ).on( 'click', 'a.show-inactive-feeds', function() {
		jQuery( 'table.feed-table' ).show().find( 'tr.inactive' ).toggleClass( 'hidden' );
		return false;
	} );

	jQuery( document ).on( 'click', 'a.show-log-lines', function() {
		jQuery( 'table.feed-table' ).find( 'tr:visible + tr.lastlog' ).toggleClass( 'hidden' );
		return false;
	} );

	jQuery( document ).on( 'click', 'a.add-feed', function() {
		jQuery( 'table.feed-table' ).find( 'tr.template' ).removeClass( 'hidden' ).find( 'input:first' ).focus();
		jQuery( this ).remove();
		return false;
	} );

	jQuery( '<a href="' + friends.add_friend_url + '" class="page-title-action">' + friends.add_friend_text + '</a>' ).insertAfter( 'a.page-title-action[href$="user-new.php"]' );

} );
