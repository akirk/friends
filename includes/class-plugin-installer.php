<?php
/**
 * Friends Plugin Installer
 *
 * This contains the functions to install plugins for the Friends Plugin.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class to install plugins for the Friends Plugin. Adapted from the github link below.
 *
 * @since 1.0
 *
 * @package Friends
 * @author Alex Kirk
 * @author Darren Cooney
 * @link  https://github.com/dcooney/wordpress-plugin-installer
 */
class Plugin_Installer {
	/**
	 * Our plugins.
	 *
	 * @var        array
	 */
	private static $plugins = array();

	/**
	 * Register the WordPress hooks
	 */
	public static function register_hooks() {
		$plugins = '_update_plugins';
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_friends_plugin_installer', array( __CLASS__, 'ajax_plugin_installer' ) );
		add_action( 'wp_ajax_friends_plugin_activation', array( __CLASS__, 'ajax_plugin_activation' ) );
		add_action( 'wp_ajax_friends_plugin_deactivation', array( __CLASS__, 'ajax_plugin_deactivation' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'override_plugin_info' ), 20, 3 );
		// TODO: Can be removed after other plugins make it into the plugin directory.
		add_filter( 'site_transient' . $plugins, array( __CLASS__, 'override_plugin_push_update' ) );
		add_filter( 'upgrader_post_install', array( __CLASS__, 'after_install' ), 10, 3 );
	}

	/**
	 * Initialize the display of the plugins.
	 *
	 * @since      1.0
	 */
	public static function init() {
		$additional_plugins = array(
			'activitypub'          => __( 'Adds ActivityPub support to your blog. Be followed on Mastodon, follow people on Mastodon with the Friends plugin.', 'friends' ),
			'enable-mastodon-apps' => true,
		);
		$plugins = array_merge( $additional_plugins, self::get_friends_plugins() );
		ksort( $plugins );

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		wp_enqueue_script( 'plugin-install' );
		add_thickbox();
		wp_enqueue_script( 'updates' );

		foreach ( array_keys( (array) $plugins ) as $plugin_slug ) {

			$button_classes = 'install button button-primary';
			$button_text    = __( 'Install Now', 'friends' );
			$api = self::get_plugin_info( sanitize_file_name( $plugin_slug ) );
			if ( ! is_wp_error( $api ) ) {
				$main_plugin_file = self::get_plugin_file( $plugin_slug );
				if ( $main_plugin_file && self::check_file_extension( $main_plugin_file ) ) {
					if ( is_plugin_active( $main_plugin_file ) ) {
						$button_classes = 'installed button disabled';
						$button_text    = __( 'Active' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					} else {
						$button_classes = 'activate button button-primary';
						$button_text    = __( 'Activate' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					}
				}
				self::render_template( $api, $button_text, $button_classes );
			}
		}
	}

	private static function get_plugin_info( $plugin_slug ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$api = \plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin_slug,
				'fields' => array(
					'short_description' => true,
					'sections'          => false,
					'requires'          => false,
					'downloaded'        => true,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
					'icons'             => true,
					'banners'           => true,
				),
			)
		);
		$api->details = wp_nonce_url( admin_url( 'admin.php?page=friends-plugins&details=' . $plugin_slug ), 'friends-plugin-overview' );
		if ( ! isset( $api->more_info ) ) {
			$api->more_info = 'https://wordpress.org/plugins/' . $plugin_slug;
		}
		return $api;
	}

	/**
	 * Gets the available plugins.
	 *
	 * @return    object  The plugin information.
	 */
	public static function get_friends_plugins() {
		$cache_key = 'friends_plugins_info_v1';
		$offline = apply_filters( 'friends_offline_mode', false );

		$data = get_transient( $cache_key );
		if ( $offline ) {
			// We'll want to use the shipped file.
			$data = false;
		}

		if ( false === $data || apply_filters( 'friends_deactivate_plugin_cache', false ) ) {
			$remote = false;
			if ( ! $offline ) {
				$remote = wp_remote_get(
					'https://raw.githubusercontent.com/akirk/friends/main/plugins.json',
					array(
						'timeout' => 10,
						'headers' => array(
							'Accept' => 'application/json',
						),
					)
				);

				if (
					is_wp_error( $remote )
					|| 200 !== wp_remote_retrieve_response_code( $remote )
					|| is_null( json_decode( wp_remote_retrieve_body( $remote ) ) )
				) {
					$remote = false;
				}
			}

			if ( false === $remote ) {
				$remote = array(
					'status_code' => 200,
					'headers'     => array(
						'content-type' => 'application/json',
					),
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					'body'        => file_get_contents( FRIENDS_PLUGIN_DIR . '/plugins.json' ),
				);
			}

			$data = array();
			foreach ( json_decode( wp_remote_retrieve_body( $remote ) ) as $slug => $plugin_data ) {
				if ( 'activitypub' === $slug ) {
					continue;
				}
				$plugin_data->sections = (array) $plugin_data->sections;
				$data[ $slug ] = $plugin_data;
			}

			if ( ! $offline ) {
				set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
			}
		}

		if ( ! $data ) {
			return array();
		}

		return $data;
	}

	/**
	 * Override the plugin info.
	 *
	 * @param      false|object|array $res     The resource.
	 * @param      string             $action  The action.
	 * @param      object             $args    The arguments.
	 *
	 * @return     bool|stdClass  ( description_of_the_return_value )
	 */
	public static function override_plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		$our_plugins = self::get_friends_plugins();
		if ( ! isset( $our_plugins[ $args->slug ] ) ) {
			return false;
		}

		return $our_plugins[ $args->slug ];
	}

	public static function plugin_details() {
		if ( ! isset( $_GET['details'] ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'friends-plugin-overview' ) ) {
			return;
		}
		$plugin_slug = sanitize_text_field( wp_unslash( $_GET['details'] ) );
		$api = self::get_plugin_info( $plugin_slug );
		if ( is_wp_error( $api ) ) {
			echo esc_html( $api->get_error_message() );
			exit;
		}

		Friends::template_loader()->get_template_part( 'admin/plugin-details', null, (array) $api );
		exit;
	}

	/**
	 * Override the plugin update function for our plugins.
	 *
	 * @param      object|false $transient  The transient.
	 *
	 * @return     object  The modified transient.
	 */
	public static function override_plugin_push_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		foreach ( self::get_friends_plugins() as $data ) {
			if ( $data && isset( $transient->checked[ $data->slug . '/' . $data->slug . '.php' ] ) && version_compare( $transient->checked[ $data->slug . '/' . $data->slug . '.php' ], $data->version, '<' ) && ( ! isset( $data->requires ) || version_compare( $data->requires, get_bloginfo( 'version' ), '<' ) ) ) {
				$res = new \stdClass();
				$res->slug = $data->slug;
				$res->plugin = $data->slug . '/' . $data->slug . '.php';
				$res->new_version = $data->version;
				if ( isset( $data->tested ) ) {
					$res->tested = $data->tested;
				}
				$res->package = $data->download_link;
				$transient->response[ $res->plugin ] = $res;
			}
		}

		return $transient;
	}

	/**
	 * Render display template for each plugin.
	 *
	 * @param      object $api             Results from plugins_api.
	 * @param      string $button_text     Text for the button.
	 * @param      string $button_classes  Classnames for the button.
	 *
	 * @since      1.0
	 */
	public static function render_template( $api, $button_text, $button_classes ) {
		$args = array(
			'api'                     => $api,
			'button_classes'          => $button_classes,
			'button_text'             => $button_text,
			'deactivate_button_class' => '',
		);

		$args['install_url'] = add_query_arg(
			array(
				'tab'      => 'install-plugin',
				'plugin'   => $api->slug,
				'_wpnonce' => wp_create_nonce( 'install-plugin_' . $api->slug ),
			),
			admin_url( 'update.php' )
		);
		if ( preg_match( '/\b(install|activate)\b/', $button_classes ) ) {
			$args['deactivate_button_class'] = 'hidden';
		}

		Friends::template_loader()->get_template_part( 'admin/plugin-info', null, $args );
	}

	/**
	 * An Ajax method for installing plugin.
	 */
	public static function ajax_plugin_installer() {

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html( __( 'Sorry, you are not allowed to install plugins on this site.', 'friends' ) ) );
		}

		$nonce  = $_POST['nonce']; // phpcs:ignore
		$plugin = $_POST['plugin']; // phpcs:ignore

		// Check our nonce, if they don't match then bounce!
		if ( ! wp_verify_nonce( $nonce, 'friends_installer_nonce' ) ) {
			wp_die( esc_html( __( 'Error - unable to verify nonce, please try again.', 'friends' ) ) );
		}

		// Include required libs for installation.
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$upgrader->install( $api->download_link );

		if ( $api->name ) {
			$status = 'success';
			$msg    = $api->name . ' successfully installed.';
		} else {
			$status = 'failed';
			$msg    = 'There was an error installing ' . $api->name . '.';
		}

		$json = array(
			'status' => $status,
			'msg'    => $msg,
		);

		wp_send_json( $json );
	}

	/**
	 * Activate plugin via Ajax.
	 */
	public static function ajax_plugin_activation() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html( __( 'Sorry, you are not allowed to activate plugins on this site.', 'friends' ) ) );
		}

		$nonce  = $_POST['nonce']; // phpcs:ignore
		$plugin = $_POST['plugin']; // phpcs:ignore

		// Check our nonce, if they don't match then bounce!
		if ( ! wp_verify_nonce( $nonce, 'friends_installer_nonce' ) ) {
			die( esc_html( __( 'Error - unable to verify nonce, please try again.', 'friends' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		if ( $api->name ) {
			$main_plugin_file = self::get_plugin_file( $plugin );
			$status           = 'success';
			if ( $main_plugin_file ) {
				activate_plugin( $main_plugin_file );
				$msg = $api->name . ' successfully activated.';
			}
		} else {
			$status = 'failed';
			$msg    = 'There was an error activating ' . $api->name . '.';
		}

		$json = array(
			'status' => $status,
			'msg'    => $msg,
		);

		wp_send_json( $json );
	}

	/**
	 * Deativate plugin via Ajax.
	 */
	public static function ajax_plugin_deactivation() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html( __( 'Sorry, you are not allowed to activate plugins on this site.', 'friends' ) ) );
		}

		$nonce  = $_POST['nonce']; // phpcs:ignore
		$plugin = $_POST['plugin']; // phpcs:ignore

		// Check our nonce, if they don't match then bounce!
		if ( ! wp_verify_nonce( $nonce, 'friends_installer_nonce' ) ) {
			die( esc_html( __( 'Error - unable to verify nonce, please try again.', 'friends' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$main_plugin_file = self::get_plugin_file( $plugin );
		deactivate_plugins( $main_plugin_file );

		$msg = $plugin . ' successfully deactivated.';

		$json = array(
			'status' => 'success',
			'msg'    => $msg,
		);

		wp_send_json( $json );
	}

	/**
	 * Fix wrong slug through Github-added version to ZIP.
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @param array $result     Installation result data.
	 *
	 * @return     bool  $response   Installation response.
	 */
	public static function after_install( $response, $hook_extra, $result ) {
		if ( empty( $result['destination_name'] ) ) {
			return $response;
		}

		// Is it a versioned destination name?
		if ( ! preg_match( '#-[0-9]+(?:\.[0-9]+)+$#', $result['destination_name'], $m ) ) {
			return $response;
		}

		// This contains the leading slash.
		$dashed_version = $m[0];

		// Strip off the version to get the real slug.
		$slug = substr( $result['destination_name'], 0, - strlen( $dashed_version ) );

		$our_plugins = self::get_friends_plugins();
		if ( ! isset( $our_plugins[ $slug ] ) ) {
			return $response;
		}

		// Is this really the plugin ZIP?
		if ( ! in_array( $slug . '.php', $result['source_files'] ) ) {
			return $response;
		}

		$install_directory = str_replace( $result['destination_name'], $slug, $result['destination'] );

		global $wp_filesystem;
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		return $result;
	}
	/**
	 * A method to get the main plugin file.
	 *
	 * @param      string $plugin_slug  The plugin slug.
	 *
	 * @return     $plugin_file
	 *
	 * @since      1.0
	 */
	public static function get_plugin_file( $plugin_slug ) {
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
		$plugins = get_plugins();

		foreach ( $plugins as $plugin_file => $plugin_info ) {
			$slug = dirname( plugin_basename( $plugin_file ) );
			if ( '.' !== $slug ) {
				// Github hosted plugins have a -hex appended.
				if ( $slug === $plugin_slug || preg_match( '/^' . preg_quote( $plugin_slug, '/' ) . '-[0-9a-f]+$/', $slug ) ) {
					return $plugin_file;
				}
			}
		}
		return null;
	}

	/**
	 * A helper to check file extension
	 *
	 * @param      string $filename  The filename.
	 *
	 * @return     boolean
	 *
	 * @since      1.0
	 */
	public static function check_file_extension( $filename ) {
		if ( substr( strrchr( $filename, '.' ), 1 ) === 'php' ) {
			return true;
		} else {
			// ./wp-content/plugins
			return false;
		}
	}

	/**
	 * Enqueue admin scripts and scripts localization
	 *
	 * @since 1.0
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script( 'friends-plugin-installer', plugins_url( 'plugin-installer.js', FRIENDS_PLUGIN_FILE ), array( 'jquery' ), FRIENDS_VERSION, true );
		wp_localize_script(
			'friends-plugin-installer',
			'friends_plugin_installer_localize',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'admin_nonce'    => wp_create_nonce( 'friends_installer_nonce' ),
				'installing'     => __( 'Installing' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'install_btn'    => __( 'Install Now', 'friends' ),
				'activate_btn'   => __( 'Activate' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'deactivate_btn' => __( 'Deactivate' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'installed_btn'  => _x( 'Active', 'plugin', 'friends' ),
			)
		);
	}
}
