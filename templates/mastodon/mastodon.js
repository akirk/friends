( function() {
	// Wrap multiple images in a gallery grid.
	document.querySelectorAll( '.posts .card-body' ).forEach( function( body ) {
		var images = body.querySelectorAll( ':scope > .wp-block-image, :scope > p > img' );
		if ( images.length < 2 ) {
			return;
		}

		var gallery = document.createElement( 'div' );
		gallery.className = 'friends-gallery gallery-' + Math.min( images.length, 4 );

		var imgElements = [];
		images.forEach( function( el ) {
			var img = el.tagName === 'IMG' ? el : el.querySelector( 'img' );
			if ( img ) {
				imgElements.push( { img: img, wrapper: el.tagName === 'IMG' ? el.parentNode : el } );
			}
		} );

		if ( imgElements.length < 2 ) {
			return;
		}

		// Insert gallery before the first image wrapper.
		imgElements[0].wrapper.parentNode.insertBefore( gallery, imgElements[0].wrapper );

		imgElements.forEach( function( item ) {
			gallery.appendChild( item.img.cloneNode( true ) );
			// Remove the original wrapper if it's now empty or just contains the image.
			if ( item.wrapper.parentNode ) {
				item.wrapper.parentNode.removeChild( item.wrapper );
			}
		} );
	} );
} )();
