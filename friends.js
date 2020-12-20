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
			// alert(url);
			location.href = url;
		} );
	} );

	/* Reactions */
	jQuery( function( $ ) {
		$document.on( 'click', 'button.new-reaction', function() {
			var p = $(this).offset();
			var spinner = $( '#friends-reaction-picker .spinner' );
			var picker = $( '#friends-reaction-picker' );

			picker.data( 'id' , $( this ).data( 'id' ) ).css( {
				left: p.left + 'px',
				top: p.top + 'px'
			} );

			if ( spinner.length ) {
				spinner.css( 'background', 'url( ' + friends.spinner_url + ') no-repeat' );
				picker.show();

				jQuery.getJSON( friends.emojis_json, function( json ) {
					var html = [];
					$.each( json, function( key, val ) {
					    html.push( '<button data-emoji="' + key + '" title="' + key + '">' + val + '</button>' );
					  });
					picker.html( html.join( '' ) ).css( {
						left: Math.max( 0, p.left - picker.width() / 2 ) + 'px',
					} );
				} );
			} else {
				picker.css( {
					left: Math.max( 0, p.left - picker.width() / 2 ) + 'px',
				} ).show();
			}

			return false;
		} );

		$document.on( 'click', 'button.friends-reaction', function() {
			jQuery.post( friends.ajax_url, {
				_ajax_nonce: $( this ).data( 'nonce' ),
				action: 'friends_toggle_react',
				post_id: $( this ).data( 'id' ),
				reaction: $( this ).data( 'emoji' )
			}, function( response ) {
				location.reload();
			} );
			return false;
		} );

		$document.on( 'click', function( e ) {
			if ( 0 === $( e.target ).closest( '#friends-reaction-picker' ).length ) {
				$( '#friends-reaction-picker' ).hide();
			}
		} );

		jQuery( '#friends-reaction-picker' ).on( 'click', 'button', function() {
			jQuery.post( friends.ajax_url, {
				_ajax_nonce: $( '#friends-reaction-picker' ).data( 'nonce' ),
				action: 'friends_toggle_react',
				post_id: $( '#friends-reaction-picker' ).data( 'id' ),
				reaction: $( this ).data( 'emoji' )
			}, function( response ) {
				location.reload();
			} );
			return false;
		} );
	} );

	/* Recommendation */
	jQuery( function( $ ) {
		$document.on( 'click', 'button.friends-recommendation', function() {
			var p = $(this).offset();
			var form = $( '#friends-recommendation-form' );

			form.find( 'div.message' ).hide();
			form.find( 'form' ).show();
			form.find( 'input[name=post_id]' ) .val( $( this ).data( 'id' ) );

			form.css( {
				left: p.left + 'px',
				top: p.top + 'px'
			} ).show();

			return false;
		} );

		$document.on( 'submit', '#friends-recommendation-form form', function() {
			var form = $( '#friends-recommendation-form' );

			jQuery.post( friends.ajax_url, form.find( 'form' ).serialize(), function( response ) {
				form.find( 'div.message' ).text( response.data.result ).show();
				form.find( 'form' ).hide();
			} );

			return false;
		} );

		$document.on( 'click', function( e ) {
			if ( 0 === $( e.target ).closest( '#friends-recommendation-form' ).length ) {
				$( '#friends-recommendation-form' ).hide();
			}
		} );
	});

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
