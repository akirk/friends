/**
 * Twitter theme JS for the Friends plugin.
 *
 * Keyboard shortcuts (compact mode):
 *   j/k           - Select next/previous item (expands it)
 *   n/p           - Select next/previous item without opening
 *   space         - Page down, or next item if at bottom of current
 *   shift-space   - Page up
 *   o/enter       - Toggle open/close current item
 *   s             - Toggle star reaction
 *   v             - Open original in new tab
 *   cmd-k/ctrl-k  - Focus search
 *   ?             - Show keyboard shortcuts help
 */
( function( $ ) {
	var currentIndex = -1;

	function getItems() {
		return $( 'section.posts article.card' );
	}

	function isCompactMode() {
		return $( 'section.posts' ).hasClass( 'all-collapsed' );
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

		items.removeClass( 'mast-current' );
		$item.addClass( 'mast-current' );
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
			if ( item.wrapper.parentNode ) {
				item.wrapper.parentNode.removeChild( item.wrapper );
			}
		} );
	} );

	// Toggle compact/expanded mode.
	$( document ).on( 'click', 'a.toggle-compact', function() {
		$( 'section.posts' ).toggleClass( 'all-collapsed' );
		if ( $( 'section.posts' ).is( '.all-collapsed' ) ) {
			$( 'a.toggle-compact' ).text( friends.text_expanded_mode );
			getItems().removeClass( 'uncollapsed' );
		} else {
			$( 'a.toggle-compact' ).text( friends.text_compact_mode );
		}
		return false;
	} );

	function toggleCard( card ) {
		var items = getItems();
		var index = items.index( card );
		var $card = $( card );

		currentIndex = index;
		items.removeClass( 'mast-current' );
		$card.addClass( 'mast-current' );

		items.not( card ).removeClass( 'uncollapsed' );
		$card.toggleClass( 'uncollapsed' );
	}

	// Block link clicks on collapsed items.
	$( document ).on( 'click', 'section.posts.all-collapsed article.card:not(.uncollapsed) .post-meta a, section.posts.all-collapsed article.card:not(.uncollapsed) .card-title a', function( e ) {
		e.preventDefault();
		e.stopPropagation();
		toggleCard( $( this ).closest( 'article.card' )[0] );
		return false;
	} );

	// Accordion click.
	$( document ).on( 'click', 'section.posts.all-collapsed article.card', function( e ) {
		if ( $( e.target ).closest( 'a, button, input, textarea, form, label, .friends-dropdown' ).length ) {
			return;
		}

		toggleCard( this );

		e.stopPropagation();
		return false;
	} );

	// Keyboard shortcuts.
	$( document ).on( 'keydown', function( e ) {
		if ( ( e.metaKey || e.ctrlKey ) && 75 === e.keyCode ) {
			e.preventDefault();
			$( '.twitter-search-input' ).first().focus();
			return false;
		}

		if ( 27 === e.keyCode && $( e.target ).is( '.twitter-search-input' ) ) {
			$( e.target ).blur();
			return false;
		}

		if ( $( e.target ).is( 'input, textarea, select, [contenteditable]' ) ) {
			return;
		}

		var items = getItems();
		if ( ! items.length && e.key !== '?' ) {
			return;
		}

		if ( ! isCompactMode() && e.key !== '?' ) {
			return;
		}

		switch ( e.key ) {
			case 'j':
				e.preventDefault();
				selectItem( currentIndex + 1, true );
				break;

			case 'k':
				e.preventDefault();
				if ( currentIndex > 0 ) {
					selectItem( currentIndex - 1, true );
				}
				break;

			case 'n':
				e.preventDefault();
				selectItem( currentIndex + 1, false );
				break;

			case 'p':
				e.preventDefault();
				if ( currentIndex > 0 ) {
					selectItem( currentIndex - 1, false );
				}
				break;

			case ' ':
				e.preventDefault();
				if ( e.shiftKey ) {
					window.scrollBy( 0, -$( window ).height() * 0.8 );
				} else if ( currentIndex >= 0 && items.eq( currentIndex ).hasClass( 'uncollapsed' ) && ! isItemBottomVisible( items.eq( currentIndex ) ) ) {
					window.scrollBy( 0, $( window ).height() * 0.8 );
				} else {
					selectItem( currentIndex + 1, true );
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
					var $star = items.eq( currentIndex ).find( 'button.friends-reaction[data-emoji="2b50"], button.friends-reaction-picker[data-emoji="2b50"]' ).first();
					if ( $star.length ) {
						$star.trigger( 'click' );
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

			case '?':
				e.preventDefault();
				var $help = $( '#twitter-shortcuts-help' );
				if ( $help.length ) {
					$help.toggle();
				} else {
					$( 'body' ).append(
						'<div id="twitter-shortcuts-help" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:light-dark(#fff,#16181c);border:none;border-radius:16px;padding:24px;z-index:10000;max-width:500px;font-size:15px;box-shadow:light-dark(rgba(101,119,134,.2),rgba(255,255,255,.2)) 0 0 15px,light-dark(rgba(101,119,134,.15),rgba(255,255,255,.1)) 0 0 3px 1px">' +
						'<h3 style="margin:0 0 16px;font-size:20px;font-weight:800">Keyboard Shortcuts</h3>' +
						'<table style="width:100%;border-collapse:collapse">' +
						'<tr><td style="padding:4px 16px 4px 0"><b>j / k</b></td><td>Next / previous item (expand)</td></tr>' +
						'<tr><td style="padding:4px 16px 4px 0"><b>n / p</b></td><td>Next / previous without opening</td></tr>' +
						'<tr><td style="padding:4px 16px 4px 0"><b>space</b></td><td>Page down or next item</td></tr>' +
						'<tr><td style="padding:4px 16px 4px 0"><b>shift-space</b></td><td>Page up</td></tr>' +
						'<tr><td style="padding:4px 16px 4px 0"><b>o / enter</b></td><td>Open / close item</td></tr>' +
						'<tr><td style="padding:4px 16px 4px 0"><b>s</b></td><td>Toggle \u2b50 reaction</td></tr>' +
						'<tr><td style="padding:4px 16px 4px 0"><b>v</b></td><td>Open original in new tab</td></tr>' +
						'<tr><td style="padding:4px 16px 4px 0"><b>\u2318K / Ctrl-K</b></td><td>Search</td></tr>' +
						'<tr><td style="padding:4px 16px 4px 0"><b>?</b></td><td>Show this help</td></tr>' +
						'</table>' +
						'<p style="margin:16px 0 0;color:light-dark(#536471,#71767b);font-size:13px">Press any key to close</p>' +
						'</div>'
					);
					$( document ).one( 'keydown click', function() {
						$( '#twitter-shortcuts-help' ).remove();
					} );
				}
				return;
		}
	} );
} )( jQuery );

// @ mention autocomplete in the compose box.
( function( $ ) {
	var nonce = ( typeof friendsTwitter !== 'undefined' ) ? friendsTwitter.mentionNonce : '';
	var $dropdown = null;
	var currentTextarea = null;
	var mentionStart = -1;
	var activeIndex = -1;
	var lastFetchedQuery = null;
	var allResults = [];
	var debounceTimer = null;
	var noResultsTimer = null;

	function getDropdown() {
		if ( ! $dropdown ) {
			$dropdown = $( '<ul class="twitter-mention-dropdown"></ul>' ).hide().appendTo( 'body' );
		}
		return $dropdown;
	}

	function closeDropdown() {
		clearTimeout( noResultsTimer );
		if ( $dropdown ) {
			$dropdown.hide();
		}
		mentionStart = -1;
		activeIndex = -1;
		lastFetchedQuery = null;
		allResults = [];
		currentTextarea = null;
		clearTimeout( debounceTimer );
	}

	function getMentionContext( textarea ) {
		var text = textarea.value.substring( 0, textarea.selectionStart );
		var match = text.match( /@(\w*)$/ );
		if ( ! match ) {
			return null;
		}
		return { query: match[ 1 ], start: text.length - match[ 0 ].length };
	}

	function positionDropdown( textarea ) {
		var $form = $( textarea ).closest( '.twitter-compose-form' );
		var offset = $form.offset();
		getDropdown().css( {
			top: offset.top + $form.outerHeight(),
			left: offset.left,
			width: $form.outerWidth()
		} );
	}

	function setActive( index ) {
		var $items = getDropdown().find( 'li:not(.twitter-mention-no-results)' );
		$items.removeClass( 'active' );
		if ( ! $items.length ) {
			activeIndex = -1;
			return;
		}
		activeIndex = Math.max( 0, Math.min( index, $items.length - 1 ) );
		$items.eq( activeIndex ).addClass( 'active' );
	}

	function acceptActive() {
		if ( activeIndex < 0 ) {
			return false;
		}
		var $item = $dropdown.find( 'li:not(.twitter-mention-no-results)' ).eq( activeIndex ).find( 'a' );
		if ( ! $item.length ) {
			return false;
		}
		$item.trigger( 'mousedown' );
		return true;
	}

	function buildItem( item ) {
		var $li = $( '<li></li>' );
		var $a = $( '<a href="#"></a>' );
		if ( item.avatar ) {
			$a.append( $( '<img>' ).attr( { src: item.avatar, width: 40, height: 40 } ) );
		}
		$a.append( $( '<span class="twitter-mention-name"></span>' ).text( item.display_name ) );
		$a.append( $( '<span class="twitter-mention-handle"></span>' ).text( '@' + item.handle ) );
		$a.on( 'mousedown', function( e ) {
			e.preventDefault();
			if ( ! currentTextarea ) {
				return;
			}
			var value = currentTextarea.value;
			var cursor = currentTextarea.selectionStart;
			var mention = '@' + item.handle + ' ';
			currentTextarea.value = value.substring( 0, mentionStart ) + mention + value.substring( cursor );
			var pos = mentionStart + mention.length;
			currentTextarea.setSelectionRange( pos, pos );
			var ta = currentTextarea;
			closeDropdown();
			ta.focus();
		} );
		$li.append( $a );
		return $li;
	}

	function renderResults( results, textarea ) {
		clearTimeout( noResultsTimer );
		var $dd = getDropdown();
		$dd.empty();
		if ( results.length ) {
			results.forEach( function( item ) {
				$dd.append( buildItem( item ) );
			} );
			positionDropdown( textarea );
			$dd.show();
			activeIndex = -1;
		} else {
			$dd.append( '<li class="twitter-mention-no-results"><span>No results</span></li>' );
			positionDropdown( textarea );
			$dd.show();
			activeIndex = -1;
			noResultsTimer = setTimeout( closeDropdown, 500 );
		}
	}

	function filterAndRender( query, textarea ) {
		var q = query.toLowerCase();
		var filtered = allResults.filter( function( item ) {
			return item.display_name.toLowerCase().indexOf( q ) !== -1 ||
				item.handle.toLowerCase().indexOf( q ) !== -1;
		} );
		renderResults( filtered, textarea );
	}

	$( document ).on( 'input', '.twitter-compose-form textarea', function() {
		var textarea = this;
		var ctx = getMentionContext( textarea );

		if ( ! ctx ) {
			closeDropdown();
			return;
		}

		currentTextarea = textarea;
		mentionStart = ctx.start;

		if ( allResults.length ) {
			filterAndRender( ctx.query, textarea );
		}

		clearTimeout( debounceTimer );

		if ( lastFetchedQuery !== null && ctx.query.indexOf( lastFetchedQuery ) === 0 ) {
			return;
		}

		debounceTimer = setTimeout( function() {
			var query = ctx.query;
			wp.ajax.send( 'friends-mention-autocomplete', {
				data: { _ajax_nonce: nonce, q: query },
				success: function( results ) {
					allResults = results || [];
					lastFetchedQuery = query;

					var currentCtx = getMentionContext( textarea );
					if ( currentCtx ) {
						filterAndRender( currentCtx.query, textarea );
					} else {
						closeDropdown();
					}
				},
				error: function() {
					closeDropdown();
				}
			} );
		}, 200 );
	} );

	$( document ).on( 'keydown', '.twitter-compose-form textarea', function( e ) {
		if ( ! $dropdown || ! $dropdown.is( ':visible' ) ) {
			return;
		}

		var count = $dropdown.find( 'li:not(.twitter-mention-no-results)' ).length;

		if ( 40 === e.keyCode ) {
			e.preventDefault();
			setActive( activeIndex < 0 ? 0 : ( activeIndex + 1 ) % count );
		} else if ( 38 === e.keyCode ) {
			e.preventDefault();
			setActive( activeIndex < 0 ? count - 1 : ( activeIndex - 1 + count ) % count );
		} else if ( 9 === e.keyCode ) {
			if ( count ) {
				e.preventDefault();
				if ( activeIndex < 0 ) {
					setActive( 0 );
				}
				acceptActive();
			}
		} else if ( 13 === e.keyCode && activeIndex >= 0 ) {
			e.preventDefault();
			acceptActive();
		} else if ( 27 === e.keyCode ) {
			closeDropdown();
		}
	} );

	$( document ).on( 'blur', '.twitter-compose-form textarea', function() {
		setTimeout( function() {
			if ( $dropdown && $dropdown.is( ':visible' ) && ! $dropdown.find( ':focus' ).length ) {
				closeDropdown();
			}
		}, 150 );
	} );

	$( document ).on( 'click', function( e ) {
		if ( $dropdown && $dropdown.is( ':visible' ) ) {
			if ( ! $( e.target ).closest( '.twitter-compose-form, .twitter-mention-dropdown' ).length ) {
				closeDropdown();
			}
		}
	} );
} )( jQuery );
