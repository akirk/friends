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
