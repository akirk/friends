/* global jQuery, friends */
jQuery( function ( $ ) {
	$( document ).on( 'click', 'input#require_codeword', function () {
		if ( this.checked ) {
			$( '#codeword_options' ).removeClass( 'hidden' );
			$( '#codeword' ).focus();
		} else {
			$( '#codeword_options' ).addClass( 'hidden' );
		}
	} );

	$( document ).on( 'click', 'a#send-friends-advanced', function () {
		$( 'tr.friends-advanced' )
			.removeClass( 'hidden' )
			.first()
			.find( 'input:visible:first' )
			.focus();
		$( this ).remove();
		return false;
	} );

	$( document ).on( 'click', 'a#add-another-feed', function () {
		$( 'tr.another-feed' )
			.removeClass( 'hidden' )
			.find( 'input:visible:first' )
			.focus();
		$( this ).remove();
		return false;
	} );

	$( document ).on( 'click', 'a#show-details', function () {
		$( '.details' ).toggleClass( 'hidden' ).focus();
		return false;
	} );

	$( document ).on( 'click', 'a#show-alternate-feeds', function () {
		$( 'li.rel-alternate' ).toggleClass( 'hidden' );
		return false;
	} );

	$( document ).on( 'click', 'a#show-unsupported-feeds', function () {
		$( '#unsupported-feeds' ).toggleClass( 'hidden' );
		return false;
	} );

	$( document ).on( 'click', '#friends_retention_days', function ( e ) {
		e.stopPropagation();
	} );

	$( document ).on(
		'click',
		'#friends_enable_retention_days, #friends_enable_retention_days_line',
		function () {
			const el = document.getElementById(
				'friends_enable_retention_days'
			);
			if ( this.id !== el.id ) {
				$( '#friends_enable_retention_days' ).attr(
					'checked',
					! el.checked
				);
			}
			$( '#friends_retention_days' ).attr( 'disabled', ! el.checked );
			$( '#friends_enable_retention_days_line' ).attr(
				'class',
				el.checked ? '' : 'disabled'
			);
		}
	);

	$( document ).on( 'click', '#friends_retention_number', function ( e ) {
		e.stopPropagation();
	} );

	$( document ).on(
		'click',
		'#friends_enable_retention_number, #friends_enable_retention_number_line',
		function () {
			const el = document.getElementById(
				'friends_enable_retention_number'
			);
			if ( this.id !== el.id ) {
				$( '#friends_enable_retention_number' ).attr(
					'checked',
					! el.checked
				);
			}
			$( '#friends_retention_number' ).attr( 'disabled', ! el.checked );
			$( '#friends_enable_retention_number_line' ).attr(
				'class',
				el.checked ? '' : 'disabled'
			);
		}
	);

	$( document ).on( 'click', 'a#toggle-raw-rules-data', function () {
		$( '#raw-rules-data' ).toggle();
		return false;
	} );

	const previewRules = function () {
		const $this = $( 'button#refresh-preview-rules' );
		let url = friends.ajax_url + '?user=' + $this.data( 'friend' );
		if ( $this.data( 'post' ) ) {
			url += '&post=' + $this.data( 'post' );
		}
		$.post(
			url,
			$( 'form#edit-rules [name^=rules]' )
				.add( 'form#edit-rules [name=catch_all]' )
				.serialize() +
				'&_wpnonce=' +
				$this.data( 'nonce' ) +
				'&action=friends_preview_rules',
			function ( response ) {
				$( '#preview-rules' ).html( response );
			}
		);
	};

	$( document ).on( 'change', 'select.rule-action', function () {
		const tdReplace = $( this ).closest( 'tr' ).find( 'td.replace-with' );
		if ( 'replace' === $( this ).val() ) {
			tdReplace.show();
		} else {
			tdReplace.hide();
		}
		previewRules();
	} );

	$( document ).on(
		'keyup click',
		'#edit-rules input, #edit-rules select',
		previewRules
	);

	$( document ).on( 'click', 'button#refresh-preview-rules', function () {
		previewRules();
		return false;
	} );

	$( document ).on( 'click', 'a.preview-parser', function () {
		const url = $( this ).closest( 'tbody' ).find( 'input.url' ).val();
		if ( ! url ) {
			return false;
		}
		this.href = this.href.replace(
			/&parser(=[^&$]+)?([&$])/,
			'&parser=' +
				encodeURIComponent(
					$( this ).closest( 'tr' ).find( 'select' ).val()
				) +
				'&'
		);
		this.href = this.href.replace(
			/&preview(=[^&$]+)?([&$])/,
			'&preview=' + encodeURIComponent( url ) + '&'
		);
	} );

	$( document ).on( 'click', 'a.show-inactive-feeds', function () {
		$( 'ul.feeds' ).show().find( 'li.inactive' ).toggleClass( 'hidden' );
		return false;
	} );

	$( document ).on( 'click', 'a.show-log-lines', function () {
		$( 'ul.feeds li.active p.lastlog' ).toggleClass( 'hidden' );
		return false;
	} );

	$( document ).on( 'click', 'a.add-feed', function () {
		$( 'ul.feeds' )
			.removeClass( 'hidden' )
			.find( 'li.template' )
			.removeClass( 'hidden' )
			.find( 'input:first' )
			.focus();
		$( this ).remove();
		return false;
	} );

	$(
		'<a href="' +
			friends.add_friend_url +
			'" class="page-title-action">' +
			friends.add_friend_text +
			'</a>'
	).insertAfter( 'a.page-title-action[href$="user-new.php"]' );

	$( document ).on( 'click', '#admin-add-emoji', function () {
		$( '#friends-reaction-picker' ).toggle();
		return false;
	} );

	$( document ).on( 'click', '.delete-emoji', function () {
		$( this ).closest( 'li' ).remove();
		return false;
	} );

	$( document ).on( 'click', '.delete-feed', function () {
		// eslint-disable-next-line no-alert
		if ( window.confirm( friends.delete_feed_question ) ) {
			$( this ).closest( 'li' ).remove();
		}
		return false;
	} );

	$( '#friends-reaction-picker' ).on( 'click', 'button', function () {
		const id = $( this ).data( 'emoji' );
		if ( $( '#emoji-' + id ).length ) {
			return;
		}
		$( '#available-emojis' ).append(
			$( '#available-emojis-template' )
				.html()
				.replace( /%1\$s/g, id )
				.replace( /%2\$s/g, $( this ).html() )
		);
		return false;
	} );

	$( document ).on( 'click', '#admin-add-keyword', function () {
		$( '#keyword-notifications' ).append(
			'<li>' +
				$( '#keyword-template' )
					.html()
					.replace(
						/\[0\]/g,
						'[' + $( '#keyword-notifications li' ).length + ']'
					)
		);
		$( '#keyword-notifications input:last ' ).focus();
		return false;
	} );

	$( document ).on( 'click', '#friends-bulk-publish', function () {
		$( this )
			.closest( 'div' )
			.find( 'select option[value=publish]' )
			.prop( 'selected', true );
	} );

	$( document ).on(
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

	$( document ).on( 'click', 'a.set-avatar', function () {
		setAvatarUrl(
			$( this ).find( 'img' ).prop( 'src' ),
			$( this ).data( 'id' ),
			$( this ).data( 'nonce' )
		);
		return false;
	} );

	const setAvatarUrl = function ( url, user, nonce ) {
		if ( ! url || ! url.match( /^https?:\/\// ) ) {
			return;
		}
		$.post(
			friends.ajax_url,
			{
				user,
				avatar: url,
				_ajax_nonce: nonce,
				action: 'friends_set_avatar',
			},
			function ( response ) {
				if ( response.success ) {
					window.location.reload();
				} else {
					$( '#friend-avatar-setting p.description' ).text(
						'⚠️ ' + response.data
					);
				}
			}
		);
	};

	$( document ).on( 'click', 'button#set-avatar-url', function () {
		const url = $( '#new-avatar-url' );
		setAvatarUrl( url.val(), url.data( 'id' ), url.data( 'nonce' ) );
		return false;
	} );

	$( document ).on( 'keyup', 'input#new-avatar-url', function ( e ) {
		const url = $( '#new-avatar-url' );
		if ( e.keyCode === 13 ) {
			setAvatarUrl( url.val(), url.data( 'id' ), url.data( 'nonce' ) );
			return false;
		}
	} );

	var updateDashboardWidget = function() {
		if ( 0 === $( '#friends-dashboard-widget' ).find( 'li.friends-post' ).length ) {
			$( '#friends-dashboard-widget' ).append( ' <i class="friends-loading"></i>' );
		}
		$.post(
			friends.ajax_url,
			{
				_ajax_nonce: $( '#friends-dashboard-widget' ).data( 'nonce' ),
				action: 'friends_dashboard',
			},
			function ( response ) {
				if ( response.success ) {
					if ( 0 === $( '#friends-dashboard-widget' ).find( 'li.friends-post' ).length ) {
						$( '#friends-dashboard-widget' ).html( response.data );
					} else {
						$( response.data ).find( 'li.friends-post' ).each( function () {
							if ( ! document.getElementById( $(this).attr('id') ) ) {
								$( '#friends-dashboard-widget ul' ).prepend( this );
							}
						} );
						$( '#friends-dashboard-widget li.friends-post:gt(49)' ).remove();
					}
				} else {
					$( '#friends-dashboard-widget i' )
						.removeClass( 'friends-loading' )
						.addClass( 'dashicons dashicons-warning' )
						.prop( 'title', response.data );
				}
			}
		);
	};
	if ( $( '#friends-dashboard-widget' ).length ) {
		updateDashboardWidget();
		setInterval( updateDashboardWidget, 60000 );
	}

	setTimeout( function () {
		if ( $( '#fetch-feeds' ).length ) {
			$( '#fetch-feeds' ).append( ' <i class="friends-loading"></i>' );
			$.post(
				friends.ajax_url,
				{
					friend: $( '#fetch-feeds' ).data( 'friend' ),
					_ajax_nonce: $( '#fetch-feeds' ).data( 'nonce' ),
					action: 'friends_fetch_feeds',
				},
				function ( response ) {
					if ( response.success ) {
						$( '#fetch-feeds i' )
							.removeClass( 'friends-loading' )
							.addClass( 'dashicons dashicons-saved' );
					} else {
						$( '#fetch-feeds i' )
							.removeClass( 'friends-loading' )
							.addClass( 'dashicons dashicons-warning' )
							.prop( 'title', response.data );
					}
				}
			);
		}
	}, 500 );
} );
