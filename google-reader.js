/**
 * Google Reader theme JS for the Friends plugin.
 *
 * Accordion behavior: only one item expanded at a time.
 * Keyboard shortcuts:
 *   j/k     - Next/previous item (expands it, collapses previous)
 *   o/Enter - Toggle open/close current item
 *   s       - Navigate to author page (to star)
 *   v       - Open original link in new tab
 *   r       - Refresh feeds
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

	function collapseAll() {
		getItems().removeClass( 'uncollapsed' );
	}

	function navigateTo( index ) {
		var items = getItems();
		if ( index < 0 || index >= items.length ) {
			return;
		}

		collapseAll();
		currentIndex = index;

		var $item = items.eq( index );
		items.removeClass( 'gr-current' );
		$item.addClass( 'gr-current' );
		scrollToItem( $item );
		expandItem( $item );
	}

	// Keyboard shortcuts.
	$( document ).on( 'keydown', function( e ) {
		if ( $( e.target ).is( 'input, textarea, select, [contenteditable]' ) ) {
			return;
		}

		var items = getItems();
		if ( ! items.length ) {
			return;
		}

		switch ( e.key ) {
			case 'j':
				e.preventDefault();
				navigateTo( currentIndex + 1 );
				break;

			case 'k':
				e.preventDefault();
				if ( currentIndex > 0 ) {
					navigateTo( currentIndex - 1 );
				}
				break;

			case 'o':
			case 'Enter':
				if ( currentIndex >= 0 ) {
					e.preventDefault();
					var $item = items.eq( currentIndex );
					if ( $item.hasClass( 'uncollapsed' ) ) {
						collapseItem( $item );
					} else {
						collapseAll();
						expandItem( $item );
					}
				}
				break;

			case 's':
				if ( currentIndex >= 0 ) {
					e.preventDefault();
					var authorLink = items.eq( currentIndex ).find( '.author a' ).attr( 'href' );
					if ( authorLink ) {
						window.location.href = authorLink;
					}
				}
				break;

			case 'v':
				if ( currentIndex >= 0 ) {
					e.preventDefault();
					var $link = items.eq( currentIndex ).find( '.card-title a, h4 a' ).first();
					if ( $link.length ) {
						window.open( $link.attr( 'href' ), '_blank' );
					}
				}
				break;

		}
	} );

	// Block link clicks on collapsed items' post-meta — just expand instead.
	$( document ).on( 'click', 'section.posts.all-collapsed article.card:not(.uncollapsed) .post-meta a', function( e ) {
		e.preventDefault();
		e.stopPropagation();
		$( this ).closest( 'article.card' ).trigger( 'click' );
		return false;
	} );

	// Accordion click: collapse all others, let the clicked item toggle.
	$( document ).on( 'click', 'section.posts.all-collapsed article.card', function( e ) {
		if ( $( e.target ).closest( 'a, button, input, textarea, form, label, .friends-dropdown' ).length ) {
			return;
		}

		var items = getItems();
		var index = items.index( this );
		var $card = $( this );

		// Track current.
		currentIndex = index;
		items.removeClass( 'gr-current' );
		$card.addClass( 'gr-current' );

		// Collapse all others.
		items.not( this ).removeClass( 'uncollapsed' );

		// Toggle this one.
		$card.toggleClass( 'uncollapsed' );

		e.stopPropagation();
		return false;
	} );
} )( jQuery );
