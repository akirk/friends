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

	var already_loading = false;
	var bottom_offset = 2000;

	$( window ).scroll( function() {
		if ( already_loading ) {
			return;
		}

		if ( $( document ).scrollTop() < ( $( document ).height() - bottom_offset ) ) {
			return;
		}

		wp.ajax.send( 'friends-load-next-page', {
			data: {
				query_vars: friends.query_vars,
				page: friends.current_page,
				qv_sign: friends.qv_sign,
				qv_sign_test: friends.qv_sign_test
			},
			beforeSend: function() {
				$('section.posts .posts-navigation').append( '<img src="'+friends.spinner_url+'" class="friends-loading-more"/>' );;
				already_loading = true;
			},
			success: function( new_posts ) {
				$('.friends-loading-more').remove();
				if ( new_posts ) {
					$('section.posts').find( 'article:last-of-type' ).after( new_posts );
					if ( ++friends.current_page <= friends.max_page && /<article/.test( new_posts ) ) {
						already_loading = false;
					} else {
						$( 'section.posts .posts-navigation' ).remove();
					}
				}
			}
		} );
	} );

})( jQuery, window.wp, window.friends );
