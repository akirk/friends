(function( $, wp, friends ) {
	var $document = $( document );

	wp = wp || {};

	$document.on( 'click', 'a.friends-auth-link, button.comments.friends-auth-link', function() {
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


	jQuery( function( $ ) {
		$( '#friend-request' ).on( 'submit', function() {
			location.href = $( 'input[name=user_url]' ).val() + '/wp-admin/admin.php?page=send-friend-request&url=<?php echo esc_js( site_url() ); ?>';
			return false;
		} );
		$( '#friend-request input[name=user_url]' ).on( 'change', function() {
			if ( ! this.value.match( /^https?:\/\// ) && this.value.match( /[a-z0-9-]+[.][a-z]+/i ) ) {
				this.value = 'http://' + this.value;
			}
		} );
		$( '#only_subscribe' ).on( 'change', function() {
			$( this ).closest( 'form' ).find( '#submit' ).text( this.checked ? '1' : '2' );
		} );
		$( '#post-format-switcher select' ).on( 'change', function() {
			var re = new RegExp( '/type/[^/$]+' ), v = $( this ).val();
			var url, url_part = v ? ( '/type/' + v + '/' ) : '/';
			if ( location.href.match( re ) ) {
				url = location.href.replace( re, url_part );
			} else {
				url = location.href.replace( /\/$/, '' ) + url_part;
			}
			location.href = url;
		} );
	} );

	$document.on( 'click', 'a.friends-trash-post', function() {
		var button = $( this );

		wp.ajax.send( 'trash-post', {
			data: {
				_ajax_nonce: button.data( 'trash-nonce' ),
				id: button.data( 'id' )
			},
			success: function( response ) {
				button.text( friends.text_undo ).attr( 'class', 'friends-untrash-post' );
			}
		} );
		return false;
	} );

	$document.on( 'click', 'a.friends-untrash-post', function() {
		var button = $( this );

		wp.ajax.send( 'untrash-post', {
			data: {
				_ajax_nonce: button.data( 'untrash-nonce' ),
				id: button.data( 'id' )
			},
			success: function( response ) {
				button.text( friends.text_trash_post ).attr( 'class', 'friends-trash-post' );
			}
		} );
		return false;
	} );

	$document.on( 'click', 'a.collapse-post', function() {
		var contents = $( this ).closest( 'article' ).find( 'div.card-body' );
		if ( contents.is(':visible') ) {
			contents.hide();
			$( this ).find( 'i' ).removeClass( 'dashicons-fullscreen-exit-alt' ).addClass( 'dashicons-fullscreen-alt' );
		} else {
			contents.show();
			$( this ).find( 'i' ).removeClass( 'dashicons-fullscreen-exit-alt' ).addClass( 'dashicons-fullscreen-alt' );
		}

		return false;
	} );

	$document.on( 'dblclick', 'a.collapse-post', function() {
		$('a.collapse-post').trigger('click');
		return false;
	} );

	$document.on( 'change', 'select.friends-change-post-format', function() {
		var select = $( this );

		wp.ajax.send( 'friends-change-post-format', {
			data: {
				_ajax_nonce: select.data( 'change-post-format-nonce' ),
				id: select.data( 'id' ),
				format: select.val()
			},
			success: function( response ) {
				// TODO: add some visual indication.
			}
		} );
		return false;
	} );

})( jQuery, window.wp, window.friends );
