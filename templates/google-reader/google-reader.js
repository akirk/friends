/**
 * Google Reader theme JS for the Friends plugin.
 *
 * Keyboard shortcuts:
 *   j/k           - Select next/previous item (expands it)
 *   n/p           - Select next/previous item without opening
 *   space         - Page down, or next item if at bottom of current
 *   shift-space   - Page up
 *   o/enter       - Toggle open/close current item
 *   s             - Star (navigate to author page)
 *   v             - Open original in new tab
 *   shift-a       - Mark all as read (trash all visible)
 *   u             - Toggle sidebar
 *   ?             - Show keyboard shortcuts help
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

	function selectItem( index, expand ) {
		var items = getItems();
		if ( index < 0 || index >= items.length ) {
			return;
		}

		if ( expand ) {
			collapseAll();
		}

		currentIndex = index;
		var $item = items.eq( index );

		items.removeClass( 'gr-current' );
		$item.addClass( 'gr-current' );
		scrollToItem( $item );

		if ( expand ) {
			expandItem( $item );
		}
	}

	function isItemBottomVisible( $item ) {
		var itemBottom = $item.offset().top + $item.outerHeight();
		var viewBottom = $( window ).scrollTop() + $( window ).height();
		return itemBottom <= viewBottom;
	}

	// Keyboard shortcuts.
	$( document ).on( 'keydown', function( e ) {
		if ( $( e.target ).is( 'input, textarea, select, [contenteditable]' ) ) {
			return;
		}

		var items = getItems();
		if ( ! items.length && e.key !== '?' && e.key !== 'u' ) {
			return;
		}

		switch ( e.key ) {
			case 'j': // Next item, expand.
				e.preventDefault();
				selectItem( currentIndex + 1, true );
				break;

			case 'k': // Previous item, expand.
				e.preventDefault();
				if ( currentIndex > 0 ) {
					selectItem( currentIndex - 1, true );
				}
				break;

			case 'n': // Next item, don't expand.
				e.preventDefault();
				selectItem( currentIndex + 1, false );
				break;

			case 'p': // Previous item, don't expand.
				e.preventDefault();
				if ( currentIndex > 0 ) {
					selectItem( currentIndex - 1, false );
				}
				break;

			case ' ': // Space: page down or next item.
				e.preventDefault();
				if ( e.shiftKey ) {
					// Page up.
					window.scrollBy( 0, -$( window ).height() * 0.8 );
				} else if ( currentIndex >= 0 && items.eq( currentIndex ).hasClass( 'uncollapsed' ) && ! isItemBottomVisible( items.eq( currentIndex ) ) ) {
					// Page down within current expanded item.
					window.scrollBy( 0, $( window ).height() * 0.8 );
				} else {
					// Move to next item.
					selectItem( currentIndex + 1, true );
				}
				break;

			case 'o': // Toggle current item.
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

			case 's': // Star: toggle ⭐ reaction on current post.
				if ( currentIndex >= 0 ) {
					e.preventDefault();
					var $star = items.eq( currentIndex ).find( 'button.friends-reaction[data-emoji="2b50"], button.friends-reaction-picker[data-emoji="2b50"]' ).first();
					if ( $star.length ) {
						$star.trigger( 'click' );
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

			case 'u': // Toggle sidebar.
				e.preventDefault();
				$( '.off-canvas-sidebar' ).toggle();
				$( '.off-canvas-content' ).toggleClass( 'gr-sidebar-hidden' );
				break;

			case '?': // Show shortcuts help.
				e.preventDefault();
				var $help = $( '#gr-shortcuts-help' );
				if ( $help.length ) {
					$help.toggle();
				} else {
					$( 'body' ).append(
						'<div id="gr-shortcuts-help" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:light-dark(#fff,#292a2d);border:1px solid light-dark(#d4d4d4,#3c4043);border-radius:4px;padding:20px;z-index:10000;max-width:500px;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,.3)">' +
						'<h3 style="margin:0 0 12px;font-size:16px">Keyboard Shortcuts</h3>' +
						'<table style="width:100%;border-collapse:collapse">' +
						'<tr><td style="padding:2px 12px 2px 0"><b>j / k</b></td><td>Next / previous item</td></tr>' +
						'<tr><td style="padding:2px 12px 2px 0"><b>n / p</b></td><td>Next / previous without opening</td></tr>' +
						'<tr><td style="padding:2px 12px 2px 0"><b>space</b></td><td>Page down or next item</td></tr>' +
						'<tr><td style="padding:2px 12px 2px 0"><b>shift-space</b></td><td>Page up</td></tr>' +
						'<tr><td style="padding:2px 12px 2px 0"><b>o / enter</b></td><td>Open / close item</td></tr>' +
						'<tr><td style="padding:2px 12px 2px 0"><b>s</b></td><td>Toggle ⭐ reaction</td></tr>' +
						'<tr><td style="padding:2px 12px 2px 0"><b>v</b></td><td>Open original in new tab</td></tr>' +
						'<tr><td style="padding:2px 12px 2px 0"><b>u</b></td><td>Toggle sidebar</td></tr>' +
						'<tr><td style="padding:2px 12px 2px 0"><b>?</b></td><td>Show this help</td></tr>' +
						'</table>' +
						'<p style="margin:12px 0 0;color:light-dark(#999,#666);font-size:11px">Press any key to close</p>' +
						'</div>'
					);
					// Close on any key or click.
					$( document ).one( 'keydown click', function() {
						$( '#gr-shortcuts-help' ).remove();
					} );
				}
				return; // Don't let the one() handler fire immediately.
		}
	} );

	// Block link clicks on collapsed items — expand instead.
	$( document ).on( 'click', 'section.posts.all-collapsed article.card:not(.uncollapsed) .post-meta a', function( e ) {
		e.preventDefault();
		e.stopPropagation();
		$( this ).closest( 'article.card' ).trigger( 'click' );
		return false;
	} );

	// Accordion click: collapse all others, toggle clicked item.
	$( document ).on( 'click', 'section.posts.all-collapsed article.card', function( e ) {
		if ( $( e.target ).closest( 'a, button, input, textarea, form, label, .friends-dropdown' ).length ) {
			return;
		}

		var items = getItems();
		var index = items.index( this );
		var $card = $( this );

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

	// Toggle compact/expanded mode.
	$( document ).on( 'click', 'a.toggle-compact', function() {
		$( 'section.posts' ).toggleClass( 'all-collapsed' );
		if ( $( 'section.posts' ).is( '.all-collapsed' ) ) {
			$( 'a.toggle-compact' ).text( friends.text_expanded_mode );
			// Collapse all when switching to compact.
			getItems().removeClass( 'uncollapsed' );
		} else {
			$( 'a.toggle-compact' ).text( friends.text_compact_mode );
		}
		return false;
	} );
} )( jQuery );
