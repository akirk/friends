jQuery( function( $ ) {

	$( document ).on( 'click', 'input[name=role-preset]', function( el ) {
		if ( 'private' === el.target.value ) {
			$( 'input[name=role\\[friend\\]\\[name\\]]' ).val( friends.role_friend );
			$( 'input[name=role\\[acquaintance\\]\\[name\\]]' ).val( friends.role_acquaintance );
			$( 'input[name=role\\[friend_request\\]\\[name\\]]' ).val( friends.role_friend_request );
			$( 'input[name=role\\[pending_friend_request\\]\\[name\\]]' ).val( friends.role_pending_friend_request );
			$( 'input[name=role\\[subscription\\]\\[name\\]]' ).val( friends.role_subscription );
		} else {
			$( 'input[name=role\\[friend\\]\\[name\\]]' ).val( friends.role_connection );
			$( 'input[name=role\\[acquaintance\\]\\[name\\]]' ).val( friends.role_contact );
			$( 'input[name=role\\[friend_request\\]\\[name\\]]' ).val( friends.role_connection_request );
			$( 'input[name=role\\[pending_friend_request\\]\\[name\\]]' ).val( friends.role_pending_connection_request );
			$( 'input[name=role\\[subscription\\]\\[name\\]]' ).val( friends.role_following );
		}
	} );


	$( document ).on( 'click', 'input#require_codeword', function() {
		if ( this.checked ) {
			$( '#codeword_options' ).removeClass( 'hidden' );
			$( '#codeword' ).focus();
		} else {
			$( '#codeword_options' ).addClass( 'hidden' );
		}
	} );

	$( document ).on( 'click', 'a#send-friends-advanced', function() {
		$( 'tr.friends-advanced' ).removeClass( 'hidden' ).first().find( 'input:visible:first' ).focus();
		$( this ).remove();
		return false;
	} );

	$( document ).on( 'click', 'a#add-another-feed', function() {
		$( 'tr.another-feed' ).removeClass( 'hidden' ).find( 'input:visible:first' ).focus();
		$( this ).remove();
		return false;
	} );

	$( document ).on( 'click', 'a#show-details', function() {
		$( '.details' ).toggleClass( 'hidden' ).focus();
		return false;
	} );

	$( document ).on( 'click', 'a#show-alternate-feeds', function() {
		$( 'li.rel-alternate' ).toggleClass( 'hidden' );
		return false;
	} );

	$( document ).on( 'click', 'a#show-unsupported-feeds', function() {
		$( '#unsupported-feeds' ).toggleClass( 'hidden' );
		return false;
	} );

	$( document ).on( 'click', '#friends_retention_days', function( e ) {
		e.stopPropagation();
	} );

	$( document ).on( 'click', '#friends_enable_retention_days, #friends_enable_retention_days_line', function() {
		var el = document.getElementById( 'friends_enable_retention_days' );
			if ( this.id !== el.id ) {
			$( '#friends_enable_retention_days' ).attr( 'checked', ! el.checked );
		}
		$( '#friends_retention_days' ).attr( 'disabled', ! el.checked );
		$( '#friends_enable_retention_days_line' ).attr( 'class', el.checked ? '' : 'disabled' );
	} );

	$( document ).on( 'click', '#friends_retention_number', function( e ) {
		e.stopPropagation();
	} );

	$( document ).on( 'click', '#friends_enable_retention_number, #friends_enable_retention_number_line', function() {
		var el = document.getElementById( 'friends_enable_retention_number' );
		if ( this.id !== el.id ) {
			$( '#friends_enable_retention_number' ).attr( 'checked', ! el.checked );
		}
		$( '#friends_retention_number' ).attr( 'disabled', ! el.checked );
		$( '#friends_enable_retention_number_line' ).attr( 'class', el.checked ? '' : 'disabled' );
	} );

	$( document ).on( 'click', 'a#toggle-raw-rules-data', function() {
		$( '#raw-rules-data' ).toggle();
		return false;
	} );

	var preview_rules = function() {
		$this = $( 'button#refresh-preview-rules' );
		var url = friends.ajax_url + '?user=' + $this.data( 'id' );
		if ( $this.data( 'post' ) ) {
			url += '&post=' + $this.data( 'post' );
		}
		$.post( url, $( 'form#edit-rules [name^=rules]' ).add( 'form#edit-rules [name=catch_all]' ).serialize() + '&_ajax_nonce=' + $this.data( 'nonce' ) + '&action=friends_preview_rules', function( response ) {
			$( '#preview-rules' ).html( response );
		} );
	};

	$( document ).on( 'change', 'select.rule-action', function() {
		var td_replace = $( this ).closest( 'tr' ).find( 'td.replace-with' );
		if ( 'replace' === $( this ).val() ) {
			td_replace.show();
		} else {
			td_replace.hide();
		}
		preview_rules();
	} );

	$( document ).on( 'keyup click', '#edit-rules input, #edit-rules select', preview_rules );

	$( document ).on( 'click', 'button#refresh-preview-rules', function() {
		preview_rules();
		return false;
	} );

	$( document ).on( 'click', 'a.preview-parser', function() {
		this.href = this.href.replace( /&parser(=[^&$]+)?([&$])/, '&parser=' + encodeURIComponent( $( this ).closest( 'td' ).find( 'select' ).val() ) + '&' );
		this.href = this.href.replace( /&preview(=[^&$]+)?([&$])/, '&preview=' + encodeURIComponent( $( this ).closest( 'tr' ).find( 'input.url' ).val() ) + '&' );
	} );

	$( document ).on( 'click', 'a.show-inactive-feeds', function() {
		$( 'table.feed-table' ).show().find( 'tr.inactive' ).toggleClass( 'hidden' );
		return false;
	} );

	$( document ).on( 'click', 'a.show-log-lines', function() {
		$( 'table.feed-table' ).find( 'tr:visible + tr.lastlog' ).toggleClass( 'hidden' );
		return false;
	} );

	$( document ).on( 'click', 'a.add-feed', function() {
		$( 'table.feed-table' ).find( 'tr.template' ).removeClass( 'hidden' ).find( 'input:first' ).focus();
		$( this ).remove();
		return false;
	} );

	$( '<a href="' + friends.add_friend_url + '" class="page-title-action">' + friends.add_friend_text + '</a>' ).insertAfter( 'a.page-title-action[href$="user-new.php"]' );

	$(document).on( 'click', '#admin-add-emoji', function() {
		$( '#friends-reaction-picker' ).toggle();
		return false;
	} );

	$(document).on( 'click', '.delete-emoji', function() {
		$( this ).closest( 'li' ).remove();
		return false;
	} );

	$( '#friends-reaction-picker' ).on( 'click', 'button', function() {
		var id = $( this ).data( 'emoji' );
		if ( $('#emoji-' + id ).length ) {
			return;
		}
		$( '#available-emojis' ).append( $( '#available-emojis-template' ).html().replace( /%1\$s/g, id ).replace( /%2\$s/g, $( this ).html() ) );
		return false;
	} );

	$(document).on( 'click', '#admin-add-keyword', function() {
		$( '#keyword-notifications' ).append( '<li>' + $( '#keyword-template' ).html().replace( /\[0\]/g, '[' + $( '#keyword-notifications li' ).length + ']' ) );
		$( '#keyword-notifications input:last ' ).focus()
		return false;
	} );

	$( document ).on( 'click', '#friends-bulk-publish', function() {
		$( this ).closest( 'div' ).find( 'select option[value=publish]' ).prop( 'selected', true );
	} );

	$( document ).on( 'click', 'a.friends-auth-link, button.comments.friends-auth-link', function() {
		var $this = jQuery( this ), href = $this.attr( 'href' ), token = $this.data( 'token' );
		if ( ! token ) {
			return;
		}

		var parts = token.split( /-/ );
		var now = new Date;
		if ( now / 1000 > parts[1] ) {
			wp.ajax.post( 'friends_refresh_link_token', {
				_ajax_nonce: $this.data( 'nonce' ),
				friend: $this.data( 'friend' ),
				url: href
			} ).done( function( response ) {
				if ( response.data ) {
					$this.data( 'token', response.data.token );
				}
			} );

			alert( friends.text_link_expired );
			return false;
		}

		if ( href && href.indexOf( 'friend_auth=' ) < 0 ) {
			var hash = href.indexOf( '#' );
			if ( hash >= 0 ) {
				hash = href.substr( hash );
				href = href.substr( 0, href.length - hash.length );
			} else {
				hash = '';
			}

			if ( href.indexOf( '?' ) >= 0 ) {
				href += '&';
			} else {
				href += '?';
			}
			href += 'friend_auth=' + token + hash;
			$this.attr( 'href', href );
		}

		if ( $this.is( 'button' ) ) {
			location.href = href;
			return false;
		}
	} );

} );
