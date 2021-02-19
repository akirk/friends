(function( $, wp, friends ) {
	var $document = $( document );
	var already_searching = '';
	var already_loading = false;
	var bottom_offset_load_next = 2000;
	var search_result_focused = false;

	wp = wp || { ajax: { send: function() {}, post: function() {} } };

	$document.on( 'keydown', 'input#master-search, .form-autocomplete a', function( e ) {
		var code = e.keyCode ? e.keyCode : e.which;
		var results = $( this ).closest( '.form-autocomplete' ).find( '.menu .menu-item a' );
		if ( 40 === code ) {
			if ( false === search_result_focused ) {
				search_result_focused = 0;
			} else if ( search_result_focused + 1 < results.length ) {
				search_result_focused += 1;
			}
		} else if ( 38 === code ) {
			if ( false === search_result_focused ) {
				search_result_focused = -1;
			} else {
				search_result_focused -= 1;
				if ( -1 === search_result_focused ) {
					$( 'input#master-search' ).focus();
					return false;
				}
			}
		} else {
			return;
		}

		var el = results.get( search_result_focused );
		if ( el ) {
			el.focus();
		}
	    return false;
	} );
	$document.on( 'keyup', 'input#master-search', function() {
		var input = $(this);
		var query = input.val().trim();
		var search_indicator = input.closest( '.form-autocomplete-input' ).find( '.form-icon' );
		var menu = input.closest( '.form-autocomplete' ).find( '.menu' );

		if ( already_searching === query ) {
			return;
		}
		if ( ! query ) {
			menu.hide();
			return;
		}
		wp.ajax.send( 'friends-autocomplete', {
			data: {
				q: query
			},
			beforeSend: function() {
				search_indicator.addClass( 'loading' );
				already_searching = query;
			},
			success: function( results ) {
				search_indicator.removeClass( 'loading' );
				search_result_focused = false;
				if ( results ) {
					menu.html( results ).show();
				} else {
					menu.hide();
				}
			}
		} );
	} );
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

	$( window ).scroll( function() {
		if ( already_loading ) {
			return;
		}

		if ( $( document ).scrollTop() < ( $( document ).height() - bottom_offset_load_next ) ) {
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
				$('section.posts .posts-navigation .nav-previous').addClass( 'loading' );
				already_loading = true;
			},
			success: function( new_posts ) {
				$('section.posts .posts-navigation .nav-previous').removeClass( 'loading' );
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
