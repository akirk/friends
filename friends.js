jQuery( document ).on( 'click', 'a.auth-link, button.comments', function() {
	var $this = jQuery( this ), href = $this.attr( 'href' );

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

	jQuery( document ).on( 'click', 'button.friends-reaction:not(.new-reaction)', function() {
		jQuery.post( friends.ajax_url, {
			action: 'friends_toggle_react',
			post_id: $( this ).data( 'id' ),
			reaction: $( this ).data( 'emoji' )
		}, function(response) {
			location.reload();
		} );
		return false;
	} );

	jQuery( document ).on( 'click', function() {
		$( '#friends-reaction-picker' ).hide();
	} );

	jQuery( '#friends-reaction-picker' ).on( 'click', 'button', function() {
		jQuery.post( friends.ajax_url, {
			action: 'friends_toggle_react',
			post_id: $( '#friends-reaction-picker' ).data( 'id' ),
			reaction: $( this ).data( 'emoji' )
		}, function(response) {
			location.reload();
		} );
		return false;
	} );



} );
