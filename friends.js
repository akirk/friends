/* global jQuery */
( function ( $, wp, friends ) {
	const $document = $( document );
	let alreadySearching = '';
	let alreadyLoading = false;
	const bottomOffsetLoadNext = 2000;
	let searchResultFocused = false;

	wp = wp || { ajax: { send() {}, post() {} } };

	$document.on(
		'keydown',
		'input#master-search, .form-autocomplete a',
		function ( e ) {
			const code = e.keyCode ? e.keyCode : e.which;
			const results = $( this )
				.closest( '.form-autocomplete' )
				.find( '.menu .menu-item a' );
			if ( 40 === code ) {
				if ( false === searchResultFocused ) {
					searchResultFocused = 0;
				} else if ( searchResultFocused + 1 < results.length ) {
					searchResultFocused += 1;
				}
			} else if ( 38 === code ) {
				if ( false === searchResultFocused ) {
					searchResultFocused = -1;
				} else {
					searchResultFocused -= 1;
					if ( -1 === searchResultFocused ) {
						$( 'input#master-search' ).focus();
						return false;
					}
				}
			} else {
				return;
			}

			const el = results.get( searchResultFocused );
			if ( el ) {
				el.focus();
			}
			return false;
		}
	);

	$document.on( 'keyup', 'input#master-search', function () {
		const input = $( this );
		const query = input.val().trim();

		if ( alreadySearching === query ) {
			return;
		}
		const menu = input.closest( '.form-autocomplete' ).find( '.menu' );

		if ( ! query ) {
			menu.hide();
			return;
		}

		const searchIndicator = input
			.closest( '.form-autocomplete-input' )
			.find( '.form-icon' );

		wp.ajax.send( 'friends-autocomplete', {
			data: {
				q: query,
			},
			beforeSend() {
				searchIndicator.addClass( 'loading' );
				alreadySearching = query;
			},
			success( results ) {
				searchIndicator.removeClass( 'loading' );
				searchResultFocused = false;
				if ( results ) {
					menu.html( results ).show();
				} else {
					menu.hide();
				}
			},
		} );
	} );
	$document.on(
		'click',
		'a.friends-auth-link, button.comments.friends-auth-link',
		function () {
			const $this = jQuery( this );
			const token = $this.data( 'token' );

			if ( ! token ) {
				return;
			}
			let href = $this.attr( 'href' );

			const parts = token.split( /-/ );
			const now = new Date();
			if ( now / 1000 > parts[ 1 ] ) {
				wp.ajax
					.post( 'friends_refresh_link_token', {
						_ajax_nonce: $this.data( 'nonce' ),
						friend: $this.data( 'friend' ),
						url: href,
					} )
					.done( function ( response ) {
						if ( response.data ) {
							$this.data( 'token', response.data.token );
						}
					} );

				// eslint-disable-next-line no-alert
				window.alert( friends.text_link_expired );
				return false;
			}

			if ( href && href.indexOf( 'friend_auth=' ) < 0 ) {
				let hash = href.indexOf( '#' );
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
				href += 'friend_auth=' + token + hash;
				$this.attr( 'href', href );
			}

			if ( $this.is( 'button' ) ) {
				window.location.href = href;
				return false;
			}
		}
	);

	$( function () {
		$( '#only_subscribe' ).on( 'change', function () {
			$( this )
				.closest( 'form' )
				.find( '#submit' )
				.text( this.checked ? '1' : '2' );
		} );
		$( '#post-format-switcher select' ).on( 'change', function () {
			const re = new RegExp( '/type/[^/$]+' ),
				v = $( this ).val();
			let url;
			const urlPart = v ? '/type/' + v + '/' : '/';
			if ( window.location.href.match( re ) ) {
				url = window.location.href.replace( re, urlPart );
			} else {
				url = window.location.href.replace( /\/$/, '' ) + urlPart;
			}
			window.location.href = url;
		} );
	} );

	$document.on( 'click', 'a.friends-trash-post', function () {
		const button = $( this );

		wp.ajax.send( 'trash-post', {
			data: {
				_ajax_nonce: button.data( 'trash-nonce' ),
				id: button.data( 'id' ),
			},
			success() {
				button
					.text( friends.text_undo )
					.attr( 'class', 'friends-untrash-post' );
			},
		} );
		return false;
	} );

	$document.on( 'click', 'a.friends-untrash-post', function () {
		const button = $( this );

		wp.ajax.send( 'untrash-post', {
			data: {
				_ajax_nonce: button.data( 'untrash-nonce' ),
				id: button.data( 'id' ),
			},
			success() {
				button
					.text( friends.text_trash_post )
					.attr( 'class', 'friends-trash-post' );
			},
		} );
		return false;
	} );

	$document.on( 'click', 'a.collapse-post', function () {
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
				.find( 'i' )
				.removeClass( 'dashicons-fullscreen-exit-alt' )
				.addClass( 'dashicons-fullscreen-alt' );
		} else {
			$( this )
				.find( 'i' )
				.removeClass( 'dashicons-fullscreen-exit-alt' )
				.addClass( 'dashicons-fullscreen-alt' );
		}

		return false;
	} );

	$document.on( 'dblclick', 'a.collapse-post', function () {
		// Collapse-toggle all visible.
		$( this ).closest( 'section' ).toggleClass( 'all-collapsed' );
		$( this )[ 0 ].scrollIntoView();
		return false;
	} );

	$document.on( 'click', 'article a.comments', function ( e ) {
		if ( e.metaKey || e.altKey || e.shiftKey ) {
			return;
		}

		const $this = $( this );
		const content = $this.closest( 'article' ).find( '.comments-content' );
		if ( content.data( 'loaded' ) ) {
			content.toggle();
		} else {
			content.show();
			wp.ajax.send( 'friends-load-comments', {
				data: {
					_ajax_nonce: $this.data( 'cnonce' ),
					post_id: $this.data( 'id' ),
				},
				success( comments ) {
					content.html( comments ).data( 'loaded', true );
				},
				error( message ) {
					content.html( message );
				},
			} );
		}
		return false;
	} );

	$document.on( 'change', 'select.friends-change-post-format', function () {
		const select = $( this );

		wp.ajax.send( 'friends-change-post-format', {
			data: {
				_ajax_nonce: select.data( 'change-post-format-nonce' ),
				id: select.data( 'id' ),
				format: select.val(),
			},
			success() {
				// TODO: add some visual indication.
			},
		} );
		return false;
	} );

	$( window ).scroll( function () {
		if ( alreadyLoading ) {
			return;
		}

		if (
			$( document ).scrollTop() <
			$( document ).height() - bottomOffsetLoadNext
		) {
			return;
		}

		if ( friends.current_page >= friends.max_page ) {
			return;
		}

		wp.ajax.send( 'friends-load-next-page', {
			data: {
				query_vars: friends.query_vars,
				page: friends.current_page,
				qv_sign: friends.qv_sign,
			},
			beforeSend() {
				$( 'section.posts .posts-navigation .nav-previous' ).addClass(
					'loading'
				);
				alreadyLoading = true;
			},
			success( newPosts ) {
				$(
					'section.posts .posts-navigation .nav-previous'
				).removeClass( 'loading' );
				if ( newPosts ) {
					$( 'section.posts' )
						.find( 'article:last-of-type' )
						.after( newPosts );
					if (
						++friends.current_page <= friends.max_page &&
						/<article/.test( newPosts )
					) {
						alreadyLoading = false;
					} else {
						$( 'section.posts .posts-navigation' ).remove();
					}
				}
			},
		} );
	} );

	/* Reactions */
	$( function () {
		$document.on( 'click', 'button.friends-reaction', function () {
			wp.ajax.send( 'friends-toggle-react', {
				data: {
					_ajax_nonce: $( this ).data( 'nonce' ),
					post_id: $( this ).data( 'id' ),
					reaction: $( this ).data( 'emoji' ),
				},
				success() {
					window.location.reload();
				},
			} );
			return false;
		} );

		$( '.friends-reaction-picker' ).on( 'click', 'button', function () {
			wp.ajax.send( 'friends-toggle-react', {
				data: {
					_ajax_nonce: $( this )
						.closest( '.friends-reaction-picker' )
						.data( 'nonce' ),
					post_id: $( this )
						.closest( '.friends-reaction-picker' )
						.data( 'id' ),
					reaction: $( this ).data( 'emoji' ),
				},
				success() {
					window.location.reload();
				},
			} );
			return false;
		} );
	} );

	/* ActivityPub */
	$( function () {
		$document.on( 'click', 'a.friends-reblog', function () {
			const $this = $( this );
			wp.ajax.send( 'friends-reblog', {
				data: {
					_ajax_nonce: $this.data( 'nonce' ),
					post_id: $this.data( 'id' ),
				},
				success() {
					$this
						.find( 'i.friends-reblog-status' )
						.addClass( 'dashicons dashicons-saved' );
				},
			} );
			return false;
		} );
	} );

	$document.on( 'click', 'a.send-new-message', function () {
		$( '#friends-send-new-message' )
			.toggle()
			.find(
				'textarea:visible, .block-editor-default-block-appender__content'
			)
			.focus();
		return false;
	} );

	$document.on( 'click', 'button.delete-conversation', function () {
		// eslint-disable-next-line no-alert
		return window.confirm( friends.text_del_convers );
	} );

	$document.on( 'click', 'a.display-message', function () {
		const $this = $( this ).closest( 'div' );
		const conversation = $this.find( 'div.conversation' );
		if ( conversation.is( ':visible' ) ) {
			conversation.hide();
		} else {
			conversation.show();
			const messages = conversation.find( '.messages' ).get( 0 );
			messages.scrollTop = messages.scrollHeight;
			$this.find( 'a.display-message' ).removeClass( 'unread' );
			wp.ajax.send( 'friends-mark-read', {
				data: {
					_ajax_nonce: $this.data( 'nonce' ),
					post_id: $this.data( 'id' ),
				},
				success() {},
			} );
			conversation
				.find(
					'textarea:visible, .block-editor-default-block-appender__content'
				)
				.focus();
		}

		return false;
	} );

	$document.on( 'mouseenter', 'h2#page-title a.dashicons', function () {
		if ( $( this ).hasClass( 'not-starred' ) ) {
			if ( $( this ).hasClass( 'dashicons-star-empty' ) ) {
				$( this )
					.removeClass( 'dashicons-star-empty' )
					.addClass( 'dashicons-star-filled' );
			}
		} else if ( $( this ).hasClass( 'starred' ) ) {
			if ( $( this ).hasClass( 'dashicons-star-filled' ) ) {
				$( this )
					.removeClass( 'dashicons-star-filled' )
					.addClass( 'dashicons-star-empty' );
			}
		}
	} );

	$document.on( 'mouseleave', 'h2#page-title a.dashicons', function () {
		if ( $( this ).hasClass( 'not-starred' ) ) {
			if ( $( this ).hasClass( 'dashicons-star-filled' ) ) {
				$( this )
					.removeClass( 'dashicons-star-filled' )
					.addClass( 'dashicons-star-empty' );
			}
		} else if ( $( this ).hasClass( 'starred' ) ) {
			if ( $( this ).hasClass( 'dashicons-star-empty' ) ) {
				$( this )
					.removeClass( 'dashicons-star-empty' )
					.addClass( 'dashicons-star-filled' );
			}
		}
	} );

	$document.on(
		'click',
		'h2#page-title a.dashicons.starred, h2#page-title a.dashicons.not-starred',
		function () {
			let removeClass = 'dashicons-star-filled starred';
			let addClass = 'dashicons-star-empty not-starred';
			const $this = $( this );
			if ( $this.hasClass( 'not-starred' ) ) {
				const s = removeClass;
				removeClass = addClass;
				addClass = s;
			}
			wp.ajax.send( 'friends-star', {
				data: {
					friend_id: $this.data( 'id' ),
					_ajax_nonce: $this.data( 'nonce' ),
					starred: $this.hasClass( 'starred' ) ? 0 : 1,
				},
				beforeSend() {
					$this
						.removeClass(
							removeClass +
								' dashicons-star-filled dashicons-star-empty'
						)
						.addClass( 'form-icon loading' );
				},
				success() {
					$this
						.removeClass( 'form-icon loading' )
						.addClass( addClass );
				},
			} );
			return false;
		}
	);

	$document.on( 'click', '#in_reply_to_preview a', function () {
		const a = $( this );
		document.execCommand( 'insertText', false, a[ 0 ].outerHTML );
		return false;
	} );
	$document.on( 'keyup', 'input#friends_in_reply_to', function () {
		const input = $( this );
		const url = input.val().trim();

		if ( alreadySearching === url ) {
			return;
		}
		const searchIndicator = input
			.closest( '.form-autocomplete-input' )
			.find( '.form-icon' );

		wp.ajax.send( 'friends-in-reply-to-preview', {
			data: {
				url,
			},
			beforeSend() {
				searchIndicator.addClass( 'loading' );
				alreadySearching = url;
			},
			success( results ) {
				searchIndicator.removeClass( 'loading' );
				if ( results ) {
					$( '#in_reply_to_preview' ).html( results.html ).show();
					const p = wp.blocks.createBlock( 'core/paragraph', {
						content: results.mention,
					} );
					wp.data
						.dispatch( 'core/block-editor' )
						.resetBlocks( [ p ] );
				} else {
					$( '#in_reply_to_preview' ).hide();
				}
			},
		} );
	} );
} )( jQuery, window.wp, window.friends );
