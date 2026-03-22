/**
 * Google Reader keyboard shortcuts for the Friends plugin.
 *
 * j/k     - Next/previous item
 * o/Enter - Open/close current item
 * s       - Star/unstar current item
 * v       - Open original link in new tab
 * r       - Refresh feeds
 */
( function( $ ) {
	var currentIndex = -1;

	function getItems() {
		return $( 'section.posts article.card' );
	}

	function scrollToItem( $item ) {
		if ( ! $item.length ) {
			return;
		}
		$( 'html, body' ).animate( {
			scrollTop: $item.offset().top - 80
		}, 150 );
	}

	function highlightItem( index ) {
		var items = getItems();
		items.removeClass( 'gr-current' );
		if ( index >= 0 && index < items.length ) {
			currentIndex = index;
			var $item = items.eq( index );
			$item.addClass( 'gr-current' );
			scrollToItem( $item );
		}
	}

	function toggleCurrent() {
		var items = getItems();
		if ( currentIndex >= 0 && currentIndex < items.length ) {
			items.eq( currentIndex ).click();
		}
	}

	$( document ).on( 'keydown', function( e ) {
		// Don't intercept when typing in inputs.
		if ( $( e.target ).is( 'input, textarea, select, [contenteditable]' ) ) {
			return;
		}

		var items = getItems();
		if ( ! items.length ) {
			return;
		}

		switch ( e.key ) {
			case 'j': // Next item.
				e.preventDefault();
				if ( currentIndex < items.length - 1 ) {
					highlightItem( currentIndex + 1 );
				}
				break;

			case 'k': // Previous item.
				e.preventDefault();
				if ( currentIndex > 0 ) {
					highlightItem( currentIndex - 1 );
				}
				break;

			case 'o': // Open/close current item.
			case 'Enter':
				if ( currentIndex >= 0 ) {
					e.preventDefault();
					toggleCurrent();
				}
				break;

			case 's': // Star current item.
				if ( currentIndex >= 0 ) {
					e.preventDefault();
					var $star = items.eq( currentIndex ).find( '.dashicons-star-filled, .dashicons-star-empty' ).first();
					if ( ! $star.length ) {
						// Navigate to the author page to star.
						var authorLink = items.eq( currentIndex ).find( '.author a' ).attr( 'href' );
						if ( authorLink ) {
							window.location.href = authorLink;
						}
					}
				}
				break;

			case 'v': // Open original in new tab.
				if ( currentIndex >= 0 ) {
					e.preventDefault();
					var $link = items.eq( currentIndex ).find( '.card-title a, h4 a' ).first();
					if ( $link.length ) {
						window.open( $link.attr( 'href' ), '_blank' );
					}
				}
				break;

			case 'r': // Refresh.
				e.preventDefault();
				window.location.href = window.location.pathname + '?refresh';
				break;
		}
	} );

	// Click on an item to make it current.
	$( document ).on( 'click', 'section.posts article.card', function() {
		var items = getItems();
		currentIndex = items.index( this );
		items.removeClass( 'gr-current' );
		$( this ).addClass( 'gr-current' );
	} );
} )( jQuery );
