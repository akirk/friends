/**
 * Default theme: collapse/expand behavior for the Friends page.
 *
 * Individual items can be collapsed/expanded by clicking.
 * The compact mode toggle switches all items at once.
 * Double-click with meta/shift/cmd toggles all.
 */
( function( $ ) {
	var $document = $( document );

	$document.on( 'click', 'a.collapse-post, .collapsed.card, .all-collapsed .card:not(.uncollapsed)', function ( e ) {
		if ( e.target.closest( '.friends-dropdown' ) || e.target.closest( 'a:not(.collapse-post)' ) || e.target.closest( 'form' ) || e.target.closest( 'textarea' ) || e.target.closest( 'input' ) || e.target.closest( 'button' ) || e.target.closest( 'label' ) ) {
			return true;
		}

		const card = $( this ).closest( 'article' );
		let collapsed;
		if ( card.closest( 'section.all-collapsed' ).length ) {
			card.toggleClass( 'uncollapsed' );
			collapsed = ! card.is( '.uncollapsed' );
		} else {
			card.toggleClass( 'collapsed' );
			collapsed = card.is( '.collapsed' );
		}
		if ( collapsed ) {
			$( this )
				.find( 'i.dashicons-fullscreen-exit-alt' )
				.removeClass( 'dashicons-fullscreen-exit-alt' )
				.addClass( 'dashicons-fullscreen-alt' );
		} else {
			$( this )
				.find( 'i.dashicons-fullscreen-alt' )
				.removeClass( 'dashicons-fullscreen-alt' )
				.addClass( 'dashicons-fullscreen-exit-alt' );
		}

		return false;
	} );

	$document.on( 'click', 'a.toggle-compact', function () {
		// Collapse-toggle all visible.
		$( 'section.posts' ).toggleClass( 'all-collapsed' );
		if ( $( 'section.posts' ).is( '.all-collapsed' ) ) {
			$( 'a.toggle-compact').text( friends.text_expanded_mode );
		} else {
			$( 'a.toggle-compact').text( friends.text_compact_mode );
		}
		$( window ).trigger( 'scroll' );
		return false;
	} );

	$document.on(
		'dblclick',
		'section.all-collapsed article, article.collapsed, article.uncollapsed',
		function ( e ) {
			if ( e.target.closest( 'form' ) || e.target.closest( 'textarea' ) || e.target.closest( 'input' ) || e.target.closest( 'button' ) || e.target.closest( 'label' ) ) {
				return true;
			}
			$( this ).closest( 'article' ).find( 'a.collapse-post' ).trigger( 'click' );
			return false;
		}
	);
} )( jQuery );
