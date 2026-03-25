( function() {
	var toggle = document.querySelector( '.mastodon-chips-toggle' );
	if ( ! toggle ) {
		return;
	}

	var area = document.querySelector( '.mastodon-chips-area' );
	if ( ! area ) {
		return;
	}

	toggle.addEventListener( 'click', function() {
		var expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
		toggle.setAttribute( 'aria-expanded', String( ! expanded ) );
		toggle.classList.toggle( 'is-open', ! expanded );
		if ( expanded ) {
			area.hidden = true;
		} else {
			area.hidden = false;
		}
	} );
} )();
