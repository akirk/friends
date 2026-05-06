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
		'input#master-search, input.master-search, .form-autocomplete a',
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
						$( this ).closest( '.form-autocomplete' ).find( 'input.master-search, input#master-search' ).focus();
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

	$document.on( 'keyup', 'input#master-search, input.master-search', function () {
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
		if ( standard_count.text().substr( 0, 3 ) === '...' || standard_count.text().substr( 0, 1 ) === '…' ) {
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


	function loadComments( commentsLink, callback ) {
		const $this = $( commentsLink );
		let content = $this.closest( 'article' ).find( '.comments-content' );
		if ( ! content.length ) {
			content = $this.closest( '.wp-block-friends-post-comments' ).find( '.comments-content' );
		}
		if ( ! content.length ) {
			content = $this.closest( 'li' ).find( '.comments-content' );
		}
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

	$document.on( 'click', 'article a.comments, .wp-block-friends-post-comments a.comments', function ( e ) {
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
			const $this = $( this );
			$this.addClass( 'loading' );
			wp.ajax.send( 'friends-toggle-react', {
				data: {
					_ajax_nonce: $this.data( 'nonce' ),
					post_id: $this.data( 'id' ),
					reaction: $this.data( 'emoji' ),
				},
				success() {
					$this.removeClass( 'loading' );
					var isPressed = $this.hasClass( 'pressed' );
					$this.toggleClass( 'pressed' );
					var textNodes = $this.contents().filter( function () {
						return this.nodeType === 3;
					} );
					var count = parseInt( textNodes.last().text().trim(), 10 ) || 0;
					var newCount = isPressed ? count - 1 : count + 1;
					if ( newCount <= 0 ) {
						$this.remove();
					} else {
						textNodes.last().replaceWith( ' ' + newCount );
					}
				},
				error() {
					$this.removeClass( 'loading' );
				},
			} );
			return false;
		} );

		$( '.friends-reaction-picker' ).on( 'click', 'button', function () {
			const $this = $( this );
			const $picker = $this.closest( '.friends-reaction-picker' );
			$this.addClass( 'loading' );
			wp.ajax.send( 'friends-toggle-react', {
				data: {
					_ajax_nonce: $picker.data( 'nonce' ),
					post_id: $picker.data( 'id' ),
					reaction: $this.data( 'emoji' ),
				},
				success() {
					$this.removeClass( 'loading' );
					var postId = $picker.data( 'id' );
					var emoji = $this.data( 'emoji' );
					var emojiChar = $this.text().trim();
					var $footer = $picker.closest( '.card-footer, .entry-meta, .friends-post-footer, footer' );
					var $existing = $footer.find( 'button.friends-reaction[data-emoji="' + emoji + '"]' );
					if ( $existing.length ) {
						var textNodes = $existing.contents().filter( function () {
							return this.nodeType === 3;
						} );
						var count = parseInt( textNodes.last().text().trim(), 10 ) || 0;
						textNodes.last().replaceWith( ' ' + ( count + 1 ) );
						$existing.addClass( 'pressed' );
					} else {
						var nonce = $picker.data( 'nonce' );
						var $btn = $( '<button class="btn btn-link ml-1 friends-action friends-reaction pressed" data-id="' + postId + '" data-emoji="' + emoji + '" data-nonce="' + nonce + '"><span>' + emojiChar + '</span> 1</button>' );
						$picker.closest( '.friends-dropdown' ).before( $btn );
					}
				},
				error() {
					$this.removeClass( 'loading' );
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
			$this.addClass( 'loading' );
			wp.ajax.send( 'friends-boost', {
				data: {
					_ajax_nonce: $this.data( 'nonce' ),
					post_id: $this.data( 'id' ),
				},
				success( result ) {
					$this.removeClass( 'loading' );
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
					$this.removeClass( 'loading' );
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

	$document.on( 'mouseenter', 'h2#page-title a.dashicons, a.wp-block-friends-author-star.dashicons', function () {
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

	$document.on( 'mouseleave', 'h2#page-title a.dashicons, a.wp-block-friends-author-star.dashicons', function () {
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
		'h2#page-title a.dashicons.starred, h2#page-title a.dashicons.not-starred, a.wp-block-friends-author-star.starred, a.wp-block-friends-author-star.not-starred',
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

	// Folder selector for subscriptions.
	$document.on( 'change', '.friends-move-to-folder', function () {
		const $select = $( this );
		const friendId = $select.data( 'id' );
		const nonce = $select.data( 'nonce' );
		let folderId = $select.val();

		if ( 'new' === folderId ) {
			const name = prompt( friends.text_new_folder || 'Folder name:' );
			if ( ! name ) {
				$select.val( $select.data( 'current' ) || 0 );
				return;
			}
			wp.ajax.send( 'friends-create-folder', {
				data: {
					name: name,
					_ajax_nonce: nonce,
				},
				success( r ) {
					// Add the new option and select it.
					$select.find( 'option[value="new"]' ).before(
						'<option value="' + r.term_id + '">' + r.name + '</option>'
					);
					$select.val( r.term_id );
					$select.data( 'current', r.term_id );
					// Now move the subscription to it.
					wp.ajax.send( 'friends-move-to-folder', {
						data: {
							friend_id: friendId,
							folder_id: r.term_id,
							_ajax_nonce: nonce,
						},
					} );
				},
			} );
			return;
		}

		$select.data( 'current', folderId );
		wp.ajax.send( 'friends-move-to-folder', {
			data: {
				friend_id: friendId,
				folder_id: folderId,
				_ajax_nonce: nonce,
			},
		} );
	} );

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
		let comments = $( this ).closest( '.card' ).find( '.comments' );
		if ( ! comments.length ) {
			comments = $( this ).closest( 'li' ).find( '.comments' );
		}

		if ( comments.length ) {
			$( 'html, body' ).animate( {
				scrollTop: comments.offset().top - 100,
			}, 500 );
		}

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

	$document.on( 'click', '.refresh-feeds', function ( e ) {
		e.preventDefault();
		const $this = $( this );
		const originalText = $this.text();
		$this.text( friends.text_refreshing || 'Refreshing' );
		wp.ajax.send( 'friends-refresh-feeds', {
			data: {
				_ajax_nonce: $this.data( 'nonce' ),
				user: $this.data( 'user' ),
			},
			success() {
				$this.text( friends.text_refreshed || 'Refreshed' );
				setTimeout( function () {
					window.location.reload();
				}, 500 );
			},
			error( result ) {
				$this.text( originalText );
			},
		} );
	} );

	let subscriptionPreviewId = 0;
	let subscriptionPasteReviewTimer = null;

	function friendsText( key, fallback ) {
		return friends && friends[ key ] ? friends[ key ] : fallback;
	}

	function ajaxErrorText( result ) {
		if ( $.isArray( result ) ) {
			return result.join( ', ' );
		}
		if ( result && result.message ) {
			return result.message;
		}
		if ( result ) {
			return String( result );
		}
		return friendsText( 'text_error', 'An error occurred.' );
	}

	function getSubscriptionNonce() {
		return $( '#add-subscription-form input[name=_wpnonce]' ).val();
	}

	function looksLikeOpml( text ) {
		if ( ! text ) {
			return false;
		}
		const trimmed = text.trim();
		if ( trimmed.charAt( 0 ) !== '<' ) {
			return false;
		}
		return /<\s*(opml|outline)\b/i.test( trimmed );
	}

	function parsePastedPeopleList( text ) {
		const seen = {};
		const items = [];
		const lines = text.split( /\r?\n/ );
		for ( let i = 0; i < lines.length; i++ ) {
			const line = lines[ i ]
				.replace( /^\s*[-*]\s+/, '' )
				.replace( /^\s*\d+[.)]\s+/, '' )
				.trim();
			if ( ! line || seen[ line ] ) {
				continue;
			}
			seen[ line ] = true;
			items.push( {
				url: line,
				text: line,
			} );
		}
		return items.length > 1 ? items : [];
	}

	function parseOpmlItems( opmlText ) {
		const parser = new DOMParser();
		const doc = parser.parseFromString( opmlText, 'application/xml' );
		if ( doc.getElementsByTagName( 'parsererror' ).length ) {
			return {
				error: friendsText( 'text_opml_invalid', 'Could not parse the OPML file.' ),
			};
		}

		const outlines = doc.querySelectorAll( 'outline[xmlUrl]' );
		if ( ! outlines.length ) {
			return {
				error: friendsText( 'text_opml_no_feeds', 'No feeds were found in the OPML file.' ),
			};
		}

		const items = [];
		$( outlines ).each( function () {
			const xmlUrl = this.getAttribute( 'xmlUrl' ) || '';
			const htmlUrl = this.getAttribute( 'htmlUrl' ) || '';
			items.push( {
				url: htmlUrl || xmlUrl,
				feedUrl: xmlUrl,
				htmlUrl: htmlUrl,
				text: this.getAttribute( 'text' ) || this.getAttribute( 'title' ) || xmlUrl,
			} );
		} );

		return { items: items };
	}

	function setSubscriptionPreviewError( message ) {
		$( '#preview-subscription' ).empty().append( $( '<p class="error"></p>' ).text( message ) );
	}

	function createSelect( options, selected, className ) {
		const $select = $( '<select></select>' ).addClass( 'form-select ' + className );
		let selectedFound = false;
		$.each( options, function ( value, label ) {
			const $option = $( '<option></option>' ).attr( 'value', value ).text( label );
			if ( selected === value ) {
				$option.prop( 'selected', true );
				selectedFound = true;
			}
			$select.append( $option );
		} );
		if ( selected && ! selectedFound ) {
			$select.append( $( '<option></option>' ).attr( 'value', selected ).prop( 'selected', true ).text( selected ) );
		}
		return $select;
	}

	function generateUserLogin( value ) {
		return String( value || '' )
			.toLowerCase()
			.replace( /[^a-z0-9.]+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	function htmlToPlainText( value ) {
		const doc = new DOMParser().parseFromString( String( value || '' ), 'text/html' );
		if ( ! doc.body ) {
			return String( value || '' );
		}
		$( doc.body ).find( 'script, style, noscript' ).remove();
		return doc.body.textContent.replace( /\s+/g, ' ' ).trim();
	}

	function appendMetadataText( $details, label, value ) {
		if ( ! value ) {
			return;
		}
		if ( $details.contents().length ) {
			$details.append( document.createTextNode( ' | ' ) );
		}
		$details.append( document.createTextNode( label + ': ' + value ) );
	}

	function getDefaultParser() {
		const parsers = friends.registered_parsers || { simplepie: 'SimplePie' };
		const parserKeys = Object.keys( parsers );
		return parserKeys.length ? parserKeys[ 0 ] : 'simplepie';
	}

	function getFeedOptionUrl( $item ) {
		const $input = $item.find( '.subscription-feed-url' );
		if ( $input.length ) {
			return $.trim( $input.val() );
		}
		return $.trim( $item.data( 'feedUrl' ) || '' );
	}

	function resetSubscriptionFeedPreview( $item ) {
		const $preview = $item.find( '.subscription-feed-preview' );
		$preview.removeData( 'loaded' ).empty().addClass( 'hidden' );
		$item.find( '.subscription-feed-preview-toggle' ).text( friendsText( 'text_preview_feed', 'Preview feed' ) );
	}

	function renderSubscriptionFeedOption( panelId, feedIndex, feedUrl, feed, options ) {
		const settings = $.extend( { hidden: false, unsupported: false, custom: false }, options || {} );
		const feedDetails = $.extend( { url: feedUrl }, feed || {} );
		const feedId = panelId + '-feed-' + feedIndex;
		const parser = settings.unsupported || settings.custom ? getDefaultParser() : ( feedDetails.parser || getDefaultParser() );
		const title = feedDetails.title || feedUrl || friendsText( 'text_custom_feed', 'Custom feed' );
		const $item = $( '<li class="subscription-feed-option"></li>' )
			.toggleClass( 'hidden rel-alternate', settings.hidden )
			.toggleClass( 'unsupported-feed-option', settings.unsupported )
			.toggleClass( 'custom-feed-option', settings.custom )
			.data( {
				feedUrl: feedUrl,
				feed: feedDetails,
			} );
		const $checkbox = $( '<input type="checkbox" class="subscription-feed-selected" />' )
			.attr( 'id', feedId )
			.prop( 'checked', settings.custom ? true : ( ! settings.unsupported && !! feedDetails.autoselect ) );
		const $label = $( '<label></label>' ).attr( 'for', feedId ).append( $checkbox ).append( ' ' );
		if ( feedUrl ) {
			$label.append(
				$( '<a class="subscription-feed-title-link" target="_blank" rel="noopener noreferrer"></a>' )
					.attr( 'href', feedUrl )
					.text( title )
			);
		} else {
			$label.append( $( '<span class="subscription-feed-title"></span>' ).text( title ) );
		}
		if ( feedDetails.type ) {
			$label.append( ' ' ).append( $( '<small></small>' ).text( '(' + feedDetails.type + ')' ) );
		}
		$item.append( $label );

		const $controls = $( '<div class="subscription-feed-controls"></div>' );
		if ( settings.unsupported || settings.custom ) {
			$controls.append(
				$( '<label class="subscription-feed-url-label"></label>' )
					.text( friendsText( 'text_feed_url', 'Feed URL' ) )
					.append(
						$( '<input type="url" class="form-input subscription-feed-url" />' )
							.val( feedUrl )
							.attr( 'placeholder', 'https://example.com/feed.xml' )
					)
			);
		}
		$controls.append(
			$( '<label></label>' )
				.text( friendsText( 'text_post_format', 'Post Format' ) )
				.append( createSelect( friends.post_formats || { standard: 'Standard' }, feedDetails[ 'post-format' ] || 'standard', 'subscription-feed-post-format' ) )
		);
		$controls.append(
			$( '<label></label>' )
				.text( friendsText( 'text_feed_parser', 'Parser' ) )
				.append( createSelect( friends.registered_parsers || { simplepie: 'SimplePie' }, parser, 'subscription-feed-parser' ) )
		);
		$controls.append(
			$( '<button type="button" class="btn btn-link subscription-feed-preview-toggle"></button>' )
				.text( friendsText( 'text_preview_feed', 'Preview feed' ) )
		);
		$item.append( $controls );

		const $details = $( '<p class="description details subscription-feed-details hidden"></p>' );
		appendMetadataText( $details, 'Type', feedDetails.type );
		appendMetadataText( $details, 'rel', feedDetails.rel );
		appendMetadataText( $details, 'URL', feedUrl );
		appendMetadataText( $details, 'Parser', feedDetails.parser );
		if ( feedDetails[ 'additional-info' ] ) {
			$details.append( $( '<br />' ) ).append( document.createTextNode( feedDetails[ 'additional-info' ] ) );
		}
		$item.append( $details );
		$item.append( $( '<div class="subscription-feed-preview hidden" aria-live="polite"></div>' ) );
		return $item;
	}

	function renderSubscriptionCard( response, nonce, fallbackDisplayName ) {
		const feeds = response.feeds || {};
		const displayName = response.display_name || fallbackDisplayName || response.user_login || response.url;
		const userLogin = response.user_login || generateUserLogin( displayName || response.url );
		const panelId = 'subscription-preview-' + ( ++subscriptionPreviewId );
		const $panel = $( '<div class="feed-preview subscription-preview"></div>' )
			.attr( 'id', panelId )
			.data( {
				url: response.url,
				nonce: nonce,
			} );

		const $header = $( '<div class="subscription-preview-header"></div>' );
		if ( response.avatar ) {
			$header.append( $( '<img class="avatar" width="48" height="48" alt="" />' ).attr( 'src', response.avatar ) );
		}
		const $title = $( '<div class="subscription-preview-title"></div>' );
		$title.append( $( '<strong></strong>' ).text( displayName ) );
		$title.append( $( '<small></small>' ).text( response.url ) );
		$header.append( $title );
		$panel.append( $header );

		if ( response.description ) {
			$panel.append( $( '<p class="subscription-preview-description"></p>' ).text( htmlToPlainText( response.description ) ) );
		}

		const $settings = $( '<div class="subscription-settings"></div>' );
		const $displayNameInput = $( '<input type="text" class="form-input subscription-display-name" required />' ).val( displayName );
		const $userLoginInput = $( '<input type="text" class="form-input subscription-user-login" required />' )
			.val( userLogin )
			.data( 'original', userLogin );
		$settings.append(
			$( '<label></label>' )
				.text( friendsText( 'text_display_name', 'Display Name' ) )
				.append( $displayNameInput )
		);
		$settings.append(
			$( '<label></label>' )
				.text( friendsText( 'text_username', 'Username' ) )
				.append( $userLoginInput )
		);
		$panel.append( $settings );

		const $feedList = $( '<ul class="feed-list subscription-feed-list"></ul>' );
		const unsupportedFeeds = [];
		let supportedCount = 0;
		let hiddenFeedCount = 0;

		$.each( feeds, function ( feedUrl, feed ) {
			if ( ! feed || 'unsupported' === feed.parser ) {
				unsupportedFeeds.push( {
					url: feedUrl,
					feed: feed || {},
				} );
				return;
			}

			supportedCount++;
			const hidden = supportedCount > 1 && ! feed.autoselect;
			if ( hidden ) {
				hiddenFeedCount++;
			}

			$feedList.append( renderSubscriptionFeedOption( panelId, supportedCount, feedUrl, feed, { hidden: hidden } ) );
		} );

		if ( supportedCount ) {
			$panel.append( $feedList );
		} else {
			$panel.append( $( '<p class="error"></p>' ).text( friendsText( 'text_no_supported_feeds', 'No supported feeds discovered.' ) ) );
		}

		const $feedActions = $( '<div class="subscription-feed-actions"></div>' );
		if ( hiddenFeedCount ) {
			$feedActions.append(
				$( '<button type="button" class="btn btn-link show-alternate-feeds"></button>' )
					.text( friendsText( 'text_show_more_feeds', 'Show more feeds' ) )
			);
		}
		if ( supportedCount || unsupportedFeeds.length ) {
			$feedActions.append(
				$( '<button type="button" class="btn btn-link show-feed-details"></button>' )
					.text( friendsText( 'text_feed_metadata', 'Display feed metadata' ) )
			);
		}
		if ( unsupportedFeeds.length ) {
			$feedActions.append(
				$( '<button type="button" class="btn btn-link show-unsupported-feeds"></button>' )
					.text( friendsText( 'text_show_unsupported_feeds', 'Show unsupported feeds' ) )
			);
			const $unsupported = $( '<ul class="feed-list subscription-feed-list unsupported-feed-list hidden"></ul>' );
			$.each( unsupportedFeeds, function ( index, item ) {
				$unsupported.append( renderSubscriptionFeedOption( panelId, supportedCount + index + 1, item.url, item.feed, { unsupported: true } ) );
			} );
			$panel.append( $unsupported );
		}
		$feedActions.append(
			$( '<button type="button" class="btn btn-link add-custom-feed"></button>' )
				.text( friendsText( 'text_add_feed_url', 'Add feed URL' ) )
		);
		if ( $feedActions.children().length ) {
			$panel.append( $feedActions );
		}

		const $previewActions = $( '<div class="subscription-preview-actions"></div>' )
			.append( $( '<button type="button" class="btn btn-link bulk-skip-entry"></button>' ).text( friendsText( 'text_skip', 'Skip' ) ) )
			.append( $( '<button type="button" class="btn btn-primary subscribe-btn"></button>' ).text( friendsText( 'text_follow', 'Follow' ) ) )
			.append( $( '<span class="subscription-preview-status" aria-live="polite"></span>' ) );
		$panel.append( $previewActions );

		return $panel;
	}

	function collectSubscriptionFeeds( $panel ) {
		const feeds = [];
		$panel.find( '.subscription-feed-option' ).each( function () {
			const $item = $( this );
			const feed = $.extend( {}, $item.data( 'feed' ) || {} );
			feed.url = getFeedOptionUrl( $item );
			if ( ! feed.url ) {
				return;
			}
			feed.selected = $item.find( '.subscription-feed-selected' ).is( ':checked' );
			feed[ 'post-format' ] = $item.find( '.subscription-feed-post-format' ).val();
			feed.parser = $item.find( '.subscription-feed-parser' ).val();
			feeds.push( feed );
		} );
		return feeds;
	}

	function renderFeedPreviewItems( $preview, items ) {
		$preview.empty();
		if ( ! items || ! items.length ) {
			$preview.append( $( '<p class="description"></p>' ).text( friendsText( 'text_no_preview_items', 'No preview items were found.' ) ) );
			return;
		}

		const $list = $( '<ul></ul>' );
		$.each( items, function ( index, item ) {
			const $item = $( '<li></li>' );
			const $summary = $( '<div class="subscription-feed-preview-title"></div>' );
			if ( item.permalink ) {
				$summary.append(
					$( '<a target="_blank" rel="noopener noreferrer"></a>' )
						.attr( 'href', item.permalink )
						.text( item.title || item.permalink )
				);
			} else {
				$summary.text( item.title || '' );
			}
			const meta = [];
			if ( item.date ) {
				meta.push( item.date );
			}
			if ( item.author ) {
				meta.push( item.author );
			}
			if ( item.post_format ) {
				meta.push( item.post_format );
			}
			if ( meta.length ) {
				$summary.append( $( '<small></small>' ).text( meta.join( ' | ' ) ) );
			}
			$item.append( $summary );
			if ( item.excerpt ) {
				$item.append( $( '<p></p>' ).text( item.excerpt ) );
			}
			$list.append( $item );
		} );
		$preview.append( $list );
	}

	function previewSubscriptionFeed( $item, $button ) {
		const $preview = $item.find( '.subscription-feed-preview' );
		const feedUrl = getFeedOptionUrl( $item );
		if ( ! feedUrl ) {
			$preview.removeClass( 'hidden' ).empty().append( $( '<p class="error"></p>' ).text( friendsText( 'text_feed_url_required', 'Please enter a feed URL.' ) ) );
			return;
		}
		if ( $preview.data( 'loaded' ) ) {
			const isHidden = $preview.hasClass( 'hidden' );
			$preview.toggleClass( 'hidden', ! isHidden );
			$button.text( isHidden ? friendsText( 'text_hide_preview', 'Hide preview' ) : friendsText( 'text_preview_feed', 'Preview feed' ) );
			return;
		}

		$button.prop( 'disabled', true );
		$preview.removeClass( 'hidden' ).text( friendsText( 'text_loading', 'Loading...' ) );
		wp.ajax.send( 'friends-preview-subscription-feed', {
			data: {
				_ajax_nonce: $item.closest( '.subscription-preview' ).data( 'nonce' ),
				url: feedUrl,
				parser: $item.find( '.subscription-feed-parser' ).val(),
			},
			success( response ) {
				renderFeedPreviewItems( $preview, response.items );
				$preview.data( 'loaded', true );
				$button.prop( 'disabled', false ).text( friendsText( 'text_hide_preview', 'Hide preview' ) );
			},
			error( result ) {
				$preview.empty().append( $( '<p class="error"></p>' ).text( ajaxErrorText( result ) ) );
				$button.prop( 'disabled', false );
			},
		} );
	}

	function subscribeSubscriptionPanel( $panel, $button, done ) {
		const feeds = collectSubscriptionFeeds( $panel );
		let selectedFeedCount = 0;
		for ( let i = 0; i < feeds.length; i++ ) {
			if ( feeds[ i ].selected ) {
				selectedFeedCount++;
			}
		}

		const $status = $panel.find( '.subscription-preview-status' );
		if ( ! selectedFeedCount ) {
			$status.addClass( 'error' ).text( friendsText( 'text_no_feed_selected', 'Please select at least one feed.' ) );
			if ( done ) {
				done( false );
			}
			return;
		}

		$button.prop( 'disabled', true );
		$status.removeClass( 'error' ).text( friendsText( 'text_loading', 'Loading...' ) );
		wp.ajax.send( 'friends-subscribe-frontend', {
			data: {
				_ajax_nonce: $panel.data( 'nonce' ),
				url: $panel.data( 'url' ),
				display_name: $panel.find( '.subscription-display-name' ).val(),
				user_login: $panel.find( '.subscription-user-login' ).val(),
				feeds: feeds,
			},
			success( response ) {
				const avatar = $panel.find( '.avatar' ).attr( 'src' );
				const $success = $( '<div class="subscribe-success"></div>' );
				if ( avatar ) {
					$success.append( $( '<img class="avatar" width="48" height="48" alt="" />' ).attr( 'src', avatar ) );
				}
				$success.append( $( '<a></a>' ).attr( 'href', response.url ).text( response.message ) );
				$panel.addClass( 'is-subscribed' ).empty().append( $success );
				if ( done ) {
					done( true );
				}
			},
			error( result ) {
				$status.addClass( 'error' ).text( ajaxErrorText( result ) );
				$button.prop( 'disabled', false );
				if ( done ) {
					done( false );
				}
			},
		} );
	}

	function discoverSubscription( url, nonce, fallbackDisplayName, $target, done ) {
		wp.ajax.send( 'friends-preview-subscription', {
			data: {
				_ajax_nonce: nonce,
				url: url,
			},
			success( response ) {
				$target.append( renderSubscriptionCard( response, nonce, fallbackDisplayName ) );
				if ( done ) {
					done( true );
				}
			},
			error( result ) {
				if ( done ) {
					done( false, ajaxErrorText( result ) );
				} else {
					setSubscriptionPreviewError( ajaxErrorText( result ) );
				}
			},
		} );
	}

	function renderSingleSubscriptionPreview( url ) {
		const $preview = $( '#preview-subscription' ).empty();
		const $results = $( '<div class="subscription-preview-results"></div>' );
		$preview.append( $( '<p class="loading"></p>' ).text( friendsText( 'text_loading', 'Loading...' ) ) );
		$preview.append( $results );
		discoverSubscription( url, getSubscriptionNonce(), '', $results, function ( success, message ) {
			$preview.find( '.loading' ).remove();
			if ( ! success ) {
				setSubscriptionPreviewError( message || friendsText( 'text_error', 'An error occurred.' ) );
			}
		} );
	}

	function renderBulkPicker( items ) {
		const $preview = $( '#preview-subscription' ).empty();
		const $controls = $( '<div class="bulk-controls"></div>' );
		$controls.append( $( '<button type="button" class="btn btn-link bulk-toggle-all"></button>' ).text( friendsText( 'text_opml_deselect_all', 'Deselect all' ) ) );
		$controls.append( ' ' );
		$controls.append( $( '<button type="button" class="btn btn-primary bulk-preview-selected"></button>' ).text( friendsText( 'text_review_selected', 'Review selected' ) ) );
		$controls.append( ' ' );
		$controls.append( $( '<button type="button" class="btn btn-primary bulk-follow-selected hidden"></button>' ).text( friendsText( 'text_follow_selected', 'Follow selected' ) ) );

		const $list = $( '<ul class="feed-list bulk-feed-list"></ul>' );
		$.each( items, function ( index, item ) {
			const itemId = 'bulk-feed-' + index;
			const $checkbox = $( '<input type="checkbox" class="bulk-feed-cb" checked />' )
				.attr( 'id', itemId )
				.data( item );
			const $label = $( '<label class="label-for-bulkfeed"></label>' )
				.attr( 'for', itemId )
				.append( $checkbox )
				.append( ' ' )
				.append( document.createTextNode( item.text || item.url ) );
			if ( item.htmlUrl ) {
				$label.append( ' ' ).append(
					$( '<small></small>' ).append(
						$( '<a target="_blank" rel="noopener noreferrer"></a>' )
							.attr( 'href', item.htmlUrl )
							.text( item.htmlUrl )
					)
				);
			}
			$list.append(
				$( '<li class="bulk-feed-row"></li>' )
					.append( $label )
					.append( ' ' )
					.append( $( '<span class="bulk-feed-status"></span>' ) )
					.append( $( '<div class="bulk-feed-review"></div>' ) )
			);
		} );

		$preview.append( $controls ).append( $list );
	}

	function updateBulkFeedRowState( $row ) {
		const isChecked = $row.find( '.bulk-feed-cb' ).is( ':checked' );
		const hasPreview = !! $row.find( '.bulk-feed-review .subscription-preview' ).length;
		const previewIsVisible = ! $row.find( '.bulk-feed-review' ).hasClass( 'hidden' );
		const isReviewing = isChecked && hasPreview && previewIsVisible;
		$row.toggleClass( 'is-reviewing', isReviewing );
		$row.find( '.label-for-bulkfeed, .bulk-feed-status' ).toggleClass( 'hidden', isReviewing );
	}

	function reviewBulkSubscription( $item, nonce, done ) {
		const data = $item.find( '.bulk-feed-cb' ).data();
		const $status = $item.find( '.bulk-feed-status' );
		const $review = $item.find( '.bulk-feed-review' ).empty();
		$status.removeClass( 'error' ).text( friendsText( 'text_discovering', 'Discovering feeds...' ) );
		updateBulkFeedRowState( $item );
		discoverSubscription( data.url, nonce, data.text, $review, function ( success, message ) {
			if ( success ) {
				$status.text( friendsText( 'text_ready_to_follow', 'Ready' ) );
			} else {
				$status.addClass( 'error' ).text( message || friendsText( 'text_error', 'An error occurred.' ) );
			}
			updateBulkFeedRowState( $item );
			done();
		} );
	}

	$document.on( 'submit', '#add-subscription-form', function ( e ) {
		e.preventDefault();
		const value = $( this ).find( '[name="url"]' ).val();
		if ( ! value ) {
			return;
		}

		if ( looksLikeOpml( value ) ) {
			const parsedOpml = parseOpmlItems( value );
			if ( parsedOpml.error ) {
				setSubscriptionPreviewError( parsedOpml.error );
				return;
			}
			renderBulkPicker( parsedOpml.items );
			return;
		}

		const pastedItems = parsePastedPeopleList( value );
		if ( pastedItems.length ) {
			renderBulkPicker( pastedItems );
			return;
		}

		renderSingleSubscriptionPreview( value.trim() );
	} );

	$document.on( 'paste', '#subscription-url', function () {
		const input = this;
		clearTimeout( subscriptionPasteReviewTimer );
		subscriptionPasteReviewTimer = setTimeout( function () {
			if ( $( input ).val().trim() ) {
				$( input ).closest( 'form' ).trigger( 'submit' );
			}
		}, 25 );
	} );

	$document.on( 'change', '#opml-file', function () {
		const file = this.files && this.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const reader = new FileReader();
		reader.onload = function ( e ) {
			const parsedOpml = parseOpmlItems( e.target.result );
			if ( parsedOpml.error ) {
				setSubscriptionPreviewError( parsedOpml.error );
				return;
			}
			renderBulkPicker( parsedOpml.items );
		};
		reader.readAsText( file );
	} );

	$document.on( 'keyup', '.subscription-display-name', function () {
		const $login = $( this ).closest( '.subscription-preview' ).find( '.subscription-user-login' );
		if ( this.value && $login.data( 'original' ) === $login.val() ) {
			const generatedLogin = generateUserLogin( this.value );
			$login.val( generatedLogin );
			$login.data( 'original', generatedLogin );
		}
	} );

	$document.on( 'click', '.show-alternate-feeds', function () {
		$( this ).closest( '.subscription-preview' ).find( '.rel-alternate' ).toggleClass( 'hidden' );
		return false;
	} );

	$document.on( 'click', '.show-feed-details', function () {
		const $this = $( this );
		const $details = $this.closest( '.subscription-preview' ).find( '.subscription-feed-details' );
		const isHidden = $details.first().hasClass( 'hidden' );
		$details.toggleClass( 'hidden', ! isHidden );
		$this.text( isHidden ? friendsText( 'text_hide_feed_metadata', 'Hide feed metadata' ) : friendsText( 'text_feed_metadata', 'Display feed metadata' ) );
		return false;
	} );

	$document.on( 'click', '.show-unsupported-feeds', function () {
		$( this ).closest( '.subscription-preview' ).find( '.unsupported-feed-list' ).toggleClass( 'hidden' );
		return false;
	} );

	$document.on( 'change', '.subscription-feed-parser', function () {
		resetSubscriptionFeedPreview( $( this ).closest( '.subscription-feed-option' ) );
	} );

	$document.on( 'input change', '.subscription-feed-url', function () {
		const $item = $( this ).closest( '.subscription-feed-option' );
		const feedUrl = getFeedOptionUrl( $item );
		const feed = $.extend( {}, $item.data( 'feed' ) || {} );
		feed.url = feedUrl;
		$item.data( 'feedUrl', feedUrl ).data( 'feed', feed );
		$item.find( '.subscription-feed-title-link' ).attr( 'href', feedUrl || '#' );
		$item.find( '.subscription-feed-selected' ).prop( 'checked', !! feedUrl );
		resetSubscriptionFeedPreview( $item );
	} );

	$document.on( 'click', '.add-custom-feed', function () {
		const $panel = $( this ).closest( '.subscription-preview' );
		let $list = $panel.find( '.custom-feed-list' );
		if ( ! $list.length ) {
			$list = $( '<ul class="feed-list subscription-feed-list custom-feed-list"></ul>' );
			$list.insertBefore( $panel.find( '.subscription-feed-actions' ) );
		}
		const feedIndex = $panel.find( '.subscription-feed-option' ).length + 1;
		const panelId = $panel.attr( 'id' ) || 'subscription-preview';
		const $item = renderSubscriptionFeedOption(
			panelId,
			feedIndex,
			'',
			{
				title: friendsText( 'text_custom_feed', 'Custom feed' ),
				parser: getDefaultParser(),
				rel: 'manual',
				type: 'custom',
			},
			{ custom: true }
		);
		$list.append( $item );
		$item.find( '.subscription-feed-url' ).trigger( 'focus' );
		return false;
	} );

	$document.on( 'click', '.subscription-feed-preview-toggle', function () {
		previewSubscriptionFeed( $( this ).closest( '.subscription-feed-option' ), $( this ) );
		return false;
	} );

	$document.on( 'click', '.subscribe-btn', function () {
		subscribeSubscriptionPanel( $( this ).closest( '.subscription-preview' ), $( this ) );
		return false;
	} );

	$document.on( 'click', '.bulk-toggle-all', function () {
		const $this = $( this );
		const $checkboxes = $( '.bulk-feed-cb' );
		const allChecked = $checkboxes.length === $checkboxes.filter( ':checked' ).length;
		$checkboxes.prop( 'checked', ! allChecked ).trigger( 'change' );
		$this.text( allChecked ? friendsText( 'text_opml_select_all', 'Select all' ) : friendsText( 'text_opml_deselect_all', 'Deselect all' ) );
		return false;
	} );

	$document.on( 'change', '.bulk-feed-cb', function () {
		const $row = $( this ).closest( '.bulk-feed-row' );
		$row.find( '.bulk-feed-review' ).toggleClass( 'hidden', ! this.checked );
		if ( ! this.checked ) {
			$row.find( '.bulk-feed-status' ).text( '' ).removeClass( 'error' );
		}
		updateBulkFeedRowState( $row );
	} );

	$document.on( 'click', '.bulk-skip-entry', function () {
		const $panel = $( this ).closest( '.subscription-preview' );
		const $row = $panel.closest( '.bulk-feed-row' );
		if ( $row.length ) {
			$row.find( '.bulk-feed-cb' ).prop( 'checked', false ).trigger( 'change' );
		} else {
			$panel.addClass( 'hidden' );
		}
		return false;
	} );

	$document.on( 'click', '.bulk-preview-selected', function () {
		const $button = $( this ).prop( 'disabled', true );
		const nonce = getSubscriptionNonce();
		const queue = [];
		$( '.bulk-feed-list > .bulk-feed-row' ).each( function () {
			const $item = $( this );
			if ( $item.find( '.bulk-feed-cb' ).is( ':checked' ) ) {
				queue.push( $item );
			}
		} );
		if ( ! queue.length ) {
			alert( friendsText( 'text_opml_no_selected', 'Please select at least one feed.' ) );
			$button.prop( 'disabled', false );
			return false;
		}

		let index = 0;
		function next() {
			if ( index >= queue.length ) {
				if ( $( '.bulk-feed-review .subscription-preview .subscribe-btn' ).length ) {
					$( '.bulk-follow-selected' ).removeClass( 'hidden' );
				}
				$button.prop( 'disabled', false );
				return;
			}
			reviewBulkSubscription( queue[ index++ ], nonce, next );
		}
		next();
		return false;
	} );

	$document.on( 'click', '.bulk-follow-selected', function () {
		const $button = $( this ).prop( 'disabled', true );
		const queue = [];
		$( '.bulk-feed-list > .bulk-feed-row' ).each( function () {
			if ( $( this ).find( '.bulk-feed-cb' ).is( ':checked' ) ) {
				$( this ).find( '.bulk-feed-review .subscription-preview' ).has( '.subscribe-btn' ).not( '.is-subscribed' ).each( function () {
					queue.push( this );
				} );
			}
		} );
		let index = 0;
		function next() {
			if ( index >= queue.length ) {
				$button.prop( 'disabled', false );
				return;
			}
			const $panel = $( queue[ index++ ] );
			subscribeSubscriptionPanel( $panel, $panel.find( '.subscribe-btn' ), next );
		}
		next();
		return false;
	} );

} )( jQuery, window.wp, window.friends );
