var friends_plugin_installer = friends_plugin_installer || {};

jQuery( document ).ready( function ( $ ) {
	'use strict';

	let is_loading = false;

	/**
	 * Install the plugin
	 *
	 * @since 1.0
	 * @param  el     object Button element
	 * @param  plugin string Plugin slug
	 */
	friends_plugin_installer.install_plugin = function ( el, plugin ) {
		is_loading = true;
		el.addClass( 'installing' );
		el.text( friends_plugin_installer_localize.installing );

		$.ajax( {
			type: 'POST',
			url: friends_plugin_installer_localize.ajax_url,
			data: {
				action: 'friends_plugin_installer',
				plugin,
				nonce: friends_plugin_installer_localize.admin_nonce,
				dataType: 'json',
			},
			success( data ) {
				if ( data ) {
					if ( data.status === 'success' ) {
						el.attr( 'class', 'activate button button-primary' );
						el.text(
							friends_plugin_installer_localize.activate_btn
						);
					} else {
						el.removeClass( 'installing' );
					}
				} else {
					el.removeClass( 'installing' );
				}
				is_loading = false;
			},
			error( xhr, status, error ) {
				el.removeClass( 'installing' );
				is_loading = false;
			},
		} );
	};

	/**
	 * Activate the plugin
	 *
	 * @since 1.0
	 * @param  el     object Button element
	 * @param  plugin string Plugin slug
	 */
	friends_plugin_installer.activate_plugin = function ( el, plugin ) {
		$.ajax( {
			type: 'POST',
			url: friends_plugin_installer_localize.ajax_url,
			data: {
				action: 'friends_plugin_activation',
				plugin,
				nonce: friends_plugin_installer_localize.admin_nonce,
				dataType: 'json',
			},
			success( data ) {
				if ( data ) {
					if ( data.status === 'success' ) {
						el.attr( 'class', 'installed button disabled' );
						el.text(
							friends_plugin_installer_localize.installed_btn
						);
						el.closest( 'div' )
							.find( '.deactivate' )
							.toggleClass( 'hidden' );
					}
				}
				is_loading = false;
			},
			error( xhr, status, error ) {
				is_loading = false;
			},
		} );
	};

	/**
	 * Deactivate the plugin
	 *
	 * @since 1.0
	 * @param  el     object Button element
	 * @param  plugin string Plugin slug
	 */
	friends_plugin_installer.deactivate_plugin = function ( el, plugin ) {
		$.ajax( {
			type: 'POST',
			url: friends_plugin_installer_localize.ajax_url,
			data: {
				action: 'friends_plugin_deactivation',
				plugin,
				nonce: friends_plugin_installer_localize.admin_nonce,
				dataType: 'json',
			},
			success( data ) {
				if ( data ) {
					if ( data.status === 'success' ) {
						el.toggleClass( 'hidden' );
						el.closest( 'div' )
							.find( '.installed' )
							.attr( 'class', 'activate button button-primary' )
							.text(
								friends_plugin_installer_localize.activate_btn
							);
					}
				}
				is_loading = false;
			},
			error( xhr, status, error ) {
				is_loading = false;
			},
		} );
	};

	/**
	 * Install/Activate Button Click
	 *
	 * @since 1.0
	 */
	$( document ).on(
		'click',
		'.friends-plugin-installer a.button:not(.details)',
		function ( e ) {
			const el = $( this ),
				plugin = el.data( 'slug' );

			e.preventDefault();

			if ( ! el.hasClass( 'disabled' ) ) {
				if ( is_loading ) return false;

				// Installation
				if ( el.hasClass( 'install' ) ) {
					friends_plugin_installer.install_plugin( el, plugin );
				}

				// Activation
				if ( el.hasClass( 'activate' ) ) {
					friends_plugin_installer.activate_plugin( el, plugin );
				}

				// Activation
				if ( el.hasClass( 'deactivate' ) ) {
					friends_plugin_installer.deactivate_plugin( el, plugin );
				}
			}
		}
	);
} );
