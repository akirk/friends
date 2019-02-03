jQuery( document ).on( 'click', 'a.auth-link, button.comments.auth-link', function() {
	var $this = jQuery( this ), href = $this.attr( 'href' );
	if ( ! $this.data( 'token' ) ) {
		return true;
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
		href += 'friend_auth=' + $this.data( 'token' ) + hash;

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
} );

/* Reactions */
jQuery( function( $ ) {
	jQuery( document ).on( 'click', 'button.new-reaction', function() {
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
				    html.push( '<button data-emoji="' + key + '">' + val + '</button>' );
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

	jQuery( document ).on( 'click', 'button.friends-reaction', function() {
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

	jQuery( document ).on( 'click', function( e ) {
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
	jQuery( document ).on( 'click', 'button.friends-recommendation', function() {
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

	jQuery( document ).on( 'submit', '#friends-recommendation-form form', function() {
		var form = $( '#friends-recommendation-form' );

		jQuery.post( friends.ajax_url, form.find( 'form' ).serialize(), function( response ) {
			form.find( 'div.message' ).text( response.data.result ).show();
			form.find( 'form' ).hide();
		} );

		return false;
	} );

	jQuery( document ).on( 'click', function( e ) {
		if ( 0 === $( e.target ).closest( '#friends-recommendation-form' ).length ) {
			$( '#friends-recommendation-form' ).hide();
		}
	} );
});

/* Trash post */
jQuery( function( $ ) {
	jQuery( document ).on( 'click', 'button.friends-trash-post', function() {
		var button = $( this );
		jQuery.post( friends.ajax_url, {
			_ajax_nonce: button.data( 'trash-nonce' ),
			action: 'trash-post',
			id: button.data( 'id' ),
		}, function( response ) {
			if ( response ) {
				button.text( friends.text_undo ).attr( 'class', 'friends-untrash-post' );
			}
		} );

		return false;
	} );
	jQuery( document ).on( 'click', 'button.friends-untrash-post', function() {
		var button = $( this );
		jQuery.post( friends.ajax_url, {
			_ajax_nonce: button.data( 'untrash-nonce' ),
			action: 'untrash-post',
			id: button.data( 'id' ),
		}, function( response ) {
			if ( response ) {
				button.html( '&#x1F5D1;' ).attr( 'class', 'friends-trash-post' );
			}
		} );

		return false;
	} );
});
