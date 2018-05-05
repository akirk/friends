jQuery( document ).on( 'click', 'a.auth-link', function() {
	var $this = jQuery( this ), href = $this.attr( 'href' );
	if ( href.indexOf( 'friend_auth=' ) >= 0 ) return;
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
} );

jQuery( function( $ ) {
	$( '#friend-request' ).on( 'submit', function() {
		location.href = $( 'input[name=user_url]' ).val() + '/wp-admin/admin.php?page=send-friend-request&url=<?php echo esc_js( site_url() ); ?>';
		return false;
	})
	$( 'input[name=user_url]' ).on( 'change', function() {
		if ( ! this.value.match( /^https?:\/\// ) && this.value.match( /[a-z0-9-]+[.][a-z]+/i ) ) {
			this.value = 'http://' + this.value;
		}
	})
});
