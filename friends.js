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

			searchResultFocused = ( results.length + searchResultFocused ) % results.length;

			const el = results.get( searchResultFocused );
			if ( el ) {
				el.focus();
			} else {
				searchResultFocused = results.length;
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
				_ajax_nonce: input.data( 'nonce' ),
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

	$document.on( 'keydown', function ( e ) {
		const searchDialog = $( '.search-dialog' );
		if ( searchDialog.length ) {
			if (
				(e.metaKey && 75 === e.keyCode ) // cmd-k.
				|| ( e.ctrlKey && 75 === e.keyCode ) // ctrl-p.
			) {
				// move all the childnodes of section.navbar-section search into the .search-dialog:
				searchDialog.append( $( '.navbar-section.search' ).children() );

				// create a new dialog
				if ( searchDialog.is( ':visible' ) ) {
					searchDialog.hide();
					$( '.navbar-section.search' ).append( searchDialog.children() );
				} else {
					searchDialog.show();
				}

				$( 'input#master-search' ).focus();
				return false;
			}

			if ( 27 === e.keyCode ) {
				searchDialog.hide();
				$( '.navbar-section.search' ).append( $( '.search-dialog' ).children() );
			}
		}
		if ( 69 === e.keyCode && ! e.metaKey && ! e.ctrlKey && ! $( e.target ).is( 'input, textarea' ) ) {
			const links = $( 'header .post-edit-link' );
			if ( 1 === links.length ) {
				links[ 0 ].click();
			}
		}
	} );

	const refresh_feeds_now = function() {
		let $this = $( this );
		function set_status( t ) {
			if ( ! $this.length ) {
				$this = $( 'a.friends-refresh' );
			}
			if ( ! $this.find( 'span' ).length ) {
				$this.html( '<span></span> <i class="loading"></i> ' );
				$this.find( 'i' ).css( 'margin-left', '1em' );
				$this.find( 'i' ).css( 'margin-right', '1em' );
			}
			$this.find( 'span' ).text( t );
		}
		set_status( friends.text_refreshing );

		$.ajax( {
			url: friends.rest_base + 'get-feeds',
			beforeSend: function(xhr){
				xhr.setRequestHeader( 'X-WP-Nonce', friends.rest_nonce );
			},
		}
		).done( function( feeds ) {
			const feed_count = feeds.length;
			let alreadyLoading = false;
			function update_page() {
				if ( alreadyLoading ) {
					return;
				}
				wp.ajax.send( 'friends-load-next-page', {
					data: {
						query_vars: friends.query_vars,
						page: friends.current_page - 1,
						qv_sign: friends.qv_sign,
					},
					beforeSend() {
						alreadyLoading = true;
					},
					success( newPosts ) {
						alreadyLoading = false;
						if ( newPosts ) {
							$( 'section.posts' )
							.html( newPosts );
						}
					},
				} );
			}
			function fetch_next() {
				if ( ! feeds.length ) {
					$this.html( friends.text_refreshed + '<i class="dashicons dashicons-yes">' );
					alreadyLoading = false;
					update_page();
					return;
				}
				const feed = feeds.shift();
				$.ajax( {
					url: friends.rest_base + 'refresh-feed',
					method: 'POST',
					data: { id: feed.id },
					beforeSend: function(xhr){
						xhr.setRequestHeader( 'X-WP-Nonce', friends.rest_nonce );
					},
				} ).always( function( data ) {
					set_status( ( feed_count - feeds.length ) + ' / ' + feed_count );
					if ( data.new_posts ) {
						update_page();
					}
					setTimeout( fetch_next, 1 );
				} );
			}
			fetch_next();
		} );
		return false;
	};
	$document.on( 'click', 'a.friends-refresh', refresh_feeds_now );

	if ( 'true' === friends.refresh_now ) {
		refresh_feeds_now.apply( $( 'a.friends-refresh' ) );
	}

	$( function () {
		const standard_count = $( '.chip.post-count-standard' );
		if ( standard_count.text().substr( 0, 3 ) === '...' ) {
			wp.ajax.send( 'friends-get-post-counts', {
				data: {
					_ajax_nonce: standard_count.data( 'nonce' )
				},
				success( r ) {
					for ( const i in r ) {
						if ( ! r[ i ] ) {
							$( '.chip.post-count-' + i ).remove();
							continue;
						}
						$( '.chip.post-count-' + i ).text( r[ i ] );
					}
				},
			} );
			return false;

		}
	});

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

	let openMenu = null;
	$document.on( 'click', 'a.friends-dropdown-toggle', function ( e ) {
		const $this = $( this );
		const dropdown = $this.next( '.menu' );
		if ( dropdown.is( ':visible' ) ) {
			dropdown.hide();
			openMenu = null;
		} else {
			$( '.menu:not(.menu-nav)' ).hide();
			dropdown.show();
			openMenu = dropdown;
		}
		e.stopPropagation();
		return false;
	});
	$document.on( 'click', function ( e ) {
		if ( e.target.closest( '.friends-dropdown' ) ) {
			return true;
		}
		if ( openMenu ) {
			openMenu.hide();
		}
	} );

	$document.on( 'click', 'a.collapse-post, .collapsed.card, .all-collapsed .card:not(.uncollapsed)', function ( e ) {
		if ( e.target.closest( '.friends-dropdown' ) || e.target.closest( 'a:not(.collapse-post)' ) ) {
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
		function () {
			$( this ).closest( 'article' ).find( 'a.collapse-post' ).trigger( 'click' );
			return false;
		}
	);

	function loadComments( commentsLink, callback ) {
		const $this = $( commentsLink );
		const content = $this.closest( 'article' ).find( '.comments-content' );
		if ( content.data( 'loaded' ) ) {
			content.toggle();
		} else {
			content.show();
			content.html( '<span></span> <i class="loading"></i>' );
			content.find( 'i' ).css( 'margin-left', '1em' );
			content.find( 'i' ).css( 'margin-right', '1em' );
			content.find( 'span' ).text( friends.text_loading_comments );
			let stillLoading = setTimeout( function () {
				content.find( 'span' ).text( friends.text_still_loading );
			}, 2000 );

			wp.ajax.send( 'friends-load-comments', {
				data: {
					_ajax_nonce: $this.data( 'cnonce' ),
					post_id: $this.data( 'id' ),
				},
				success( comments ) {
					content.html( comments ).data( 'loaded', true );
					clearTimeout( stillLoading );
					callback();
				},
				error( message ) {
					content.html( message ).data( 'loaded', true );
					clearTimeout( stillLoading );
				},
			} );
		}
	}

	$document.on( 'click', 'article a.comments', function ( e ) {
		if ( e.metaKey || e.altKey || e.shiftKey ) {
			return;
		}

		loadComments.call( this );
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
		if (
			$( document ).scrollTop() <
			$( document ).height() - bottomOffsetLoadNext
		) {
			return;
		}
		nextPage();
	} );

	$( document ).on( 'click', '.nav-previous', function () {
		nextPage();
		return false;
	} );

	function nextPage() {
		if ( alreadyLoading ) {
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
					$( 'section.posts > article' ).last().after( newPosts );
					if (
						++friends.current_page < friends.max_page &&
						/<article/.test( newPosts )
					) {
						alreadyLoading = false;
					} else {
						$( 'section.posts .posts-navigation' ).text(
							friends.text_no_more_posts
						);
					}
				}
			},
		} );
	}

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

	$( function () {
		$document.on( 'click', 'a.friends-reblog', function () {
			const $this = $( this );
			wp.ajax.send( 'friends-reblog', {
				data: {
					_ajax_nonce: $this.data( 'nonce' ),
					post_id: $this.data( 'id' ),
				},
				success( result ) {
					if ( result.redirect ) {
						location.href = result.redirect;
					}
					$this
						.find( 'i.friends-reblog-status' )
						.addClass( 'dashicons dashicons-saved' );
				},
			} );
			return false;
		} );
	} );

	$( function () {
		$document.on( 'click', 'a.friends-boost', function () {
			const $this = $( this );
			wp.ajax.send( 'friends-boost', {
				data: {
					_ajax_nonce: $this.data( 'nonce' ),
					post_id: $this.data( 'id' ),
				},
				success( result ) {
					if ( 'boosted' === result ) {
						$this
							.find( 'i.friends-boost-status' )
							.removeClass( 'dashicons-warning' )
							.addClass( 'dashicons dashicons-saved' );
					} else if ( 'unboosted' === result ) {
						$this
							.find( 'i.friends-boost-status' )
							.removeClass( 'dashicons dashicons-warning dashicons-saved' )
					} else {
						$this
							.find( 'i.friends-boost-status' )
							.addClass( 'dashicons dashicons-warning' )
							.removeClass( 'dashicons-saved' )
							.attr( 'title', result );
					}
				},
				fail( result ) {
					$this
						.find( 'i.friends-boost-status' )
						.addClass( 'dashicons dashicons-warning' )
						.removeClass( 'dashicons-saved' )
						.attr( 'title', result );
					}
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
			// scroll smoothly to the bottom of the overflow div:
			$( messages ).animate( { scrollTop: messages.scrollHeight }, 1000 );

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

	function getAcct( href ) {
		const url = new URL( href );
		const username = url.pathname.replace( /\/$/, '' ).split( '/' ).pop();
		return '@' + username.replace( /^@/, '' ) + '@' + url.hostname;
	}

	function insertTextInGutenberg( text ) {
		let element, val, regex;
		if ( jQuery( '.is-root-container p' ).length ) {
			element = jQuery( '.is-root-container p' )[ 0 ];
			element.focus();
			window.getSelection().collapse( element.firstChild, element.textContent.length );
			document.execCommand( 'insertText', false, text + ' ' );
		} else {
			val = text + ' ' + jQuery( '.friends-status-content' ).val();
			jQuery( '.friends-status-content' ).val( val.replace( /^(@[^@]+@[^ ]+ )+/g, text + ' ' ) );
		}
	}

	$document.on( 'click', '.quick-reply,a.comments', function () {
		const card = $( this ).closest( '.card' );
		card.click();
		$( this ).closest( '.friends-dropdown' ).hide();
		openMenu = null;
		const comments = $( this ).closest( '.card' ).find( '.comments' );

		$( 'html, body' ).animate( {
			scrollTop: comments.offset().top - 100,
		}, 500 );

		loadComments( comments, function() {
			// focus #comment textarea but put the cursor at the end
			const comment = card.find( '#comment' );
			comment.focus();
			const val = comment.val();
			comment.val( '' ).val( val );
		} );
		return false;
	} );

	$document.on( 'click', '.quick-post-panel-toggle', function () {
		$( '#quick-post-panel' ).toggleClass( 'open' );
		return false;
	} );

	// when the ".followers details" html element is expanded
	$document.on( 'click', '.followers details', function () {
		const $this = $( this );
		if ( $this.find( '.loading-posts' ).length ) {
			wp.ajax.send( 'friends-preview-activitypub', {
				data: {
					_ajax_nonce: $this.data( 'nonce' ),
					url: $this.data( 'id' ),
					followers: $this.data( 'followers' ),
					following: $this.data( 'following' ),
				},
				success( result ) {
					$this.find( '.loading-posts' ).hide().after( result.posts );
					$this.find( '.their-followers' ).text( result.followers );
					$this.find( '.their-following' ).text( result.following );
				},
				error( result ) {
					$this.find( '.loading-posts' ).text( result.map(function( error ) { return error.message || error.code; } ).join( ',' ) );
				}
			} );
		}
	} );

	$document.on( 'click', '.follower-delete', function () {
		const $this = $( this );
		if ( ! confirm( friends.text_confirm_delete_follower.replace( /%s/, $this.data( 'handle' )) ) ) {
			return false;
		}
		wp.ajax.send( 'friends-delete-follower', {
			data: {
				_ajax_nonce: $this.data( 'nonce' ),
				id: $this.data( 'id' ),
			},
			success() {
				$this.closest( 'details' ).remove();
			},
		} );
		return false;
	} );

	$document.on( 'click', '.friends-widget summary', function () {
		const $this = $( this ).closest( 'details' );
		const previously_open = $this.prop( 'open' );
		wp.ajax.send( 'friends-set-widget-open-state', {
			data: {
				_ajax_nonce: $this.data( 'nonce' ),
				widget: $this.data( 'id' ),
				state: previously_open ? 'closed' : 'open',
			},
			success() {},
		} );
	} );

} )( jQuery, window.wp, window.friends );
