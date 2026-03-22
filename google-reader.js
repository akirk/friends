/**
 * Google Reader keyboard shortcuts for the Friends plugin.
 *
 * j/k     - Next/previous item (expands it, collapses previous)
 * o/Enter - Toggle open/close current item
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

	function expandItem( $item ) {
		if ( ! $item.hasClass( 'uncollapsed' ) ) {
			$item.addClass( 'uncollapsed' );
		}
	}

	function collapseItem( $item ) {
		$item.removeClass( 'uncollapsed' );
	}

	function navigateTo( index ) {
		var items = getItems();
		if ( index < 0 || index >= items.length ) {
			return;
		}

		// Collapse previous.
		if ( currentIndex >= 0 && currentIndex < items.length && currentIndex !== index ) {
			collapseItem( items.eq( currentIndex ) );
		}

		currentIndex = index;
		var $item = items.eq( index );

		items.removeClass( 'gr-current' );
		$item.addClass( 'gr-current' );
		scrollToItem( $item );

		// Expand new.
		expandItem( $item );
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
				navigateTo( currentIndex + 1 );
				break;

			case 'k': // Previous item.
				e.preventDefault();
				if ( currentIndex > 0 ) {
					navigateTo( currentIndex - 1 );
				}
				break;

			case 'o': // Toggle current item.
			case 'Enter':
				if ( currentIndex >= 0 ) {
					e.preventDefault();
					items.eq( currentIndex ).click();
				}
				break;

			case 's': // Star current item.
				if ( currentIndex >= 0 ) {
					e.preventDefault();
					var authorLink = items.eq( currentIndex ).find( '.author a' ).attr( 'href' );
					if ( authorLink ) {
						window.location.href = authorLink;
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

	// When a card is clicked, collapse all others first (before the Friends JS handler expands it).
	$( document ).on( 'click', 'section.posts.all-collapsed article.card', function() {
		var items = getItems();
		var index = items.index( this );

		// Collapse all others.
		items.not( this ).removeClass( 'uncollapsed' );

		// Track current.
		currentIndex = index;
		items.removeClass( 'gr-current' );
		$( this ).addClass( 'gr-current' );

		// Don't stop propagation — let the Friends JS handler toggle this item.
	} );
} )( jQuery );
