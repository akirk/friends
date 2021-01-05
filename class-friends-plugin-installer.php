<?php
/**
 * Friends Plugin Installer
 *
 * This contains the functions to install plugins for the Friends Plugin.
 *
 * @package Friends
 */

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
class Friends_Plugin_Installer {
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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_friends_plugin_installer', array( __CLASS__, 'ajax_plugin_installer' ) );
		add_action( 'wp_ajax_friends_plugin_activation', array( __CLASS__, 'ajax_plugin_activation' ) );
		add_action( 'wp_ajax_friends_plugin_deactivation', array( __CLASS__, 'ajax_plugin_deactivation' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'override_plugin_info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'override_plugin_push_update' ) );
	}

	/**
	 * Initialize the display of the plugins.
	 *
	 * @since      1.0
	 */
	public static function init() {

		$plugins = self::get_plugins();
		?>
		<div class="friends-plugin-installer">
		<?php
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		foreach ( array_keys( $plugins ) as $plugin_slug ) :

			$button_classes = 'install button';
			$button_text    = __( 'Install Now', 'friends' );

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => sanitize_file_name( $plugin_slug ),
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

			if ( ! is_wp_error( $api ) ) {

				$main_plugin_file = self::get_plugin_file( $plugin_slug );
				if ( self::check_file_extension( $main_plugin_file ) ) {
					if ( is_plugin_active( $main_plugin_file ) ) {
						$button_classes = 'installed button disabled';
						$button_text    = __( 'Active' );
					} else {
						$button_classes = 'activate button button-primary';
						$button_text    = __( 'Activate' );
					}
				}
				self::render_template( $plugin_slug, $api, $button_text, $button_classes );
			}

		endforeach;
		?>
		</div>
		<?php
	}

	/**
	 * Gets the available plugins from wpfriends.at.
	 *
	 * @return    object  The plugin information.
	 */
	public static function get_plugins() {
		$cache_key = 'friends_plugins_info_v1';

		$data = get_transient( $cache_key );
		if ( false === $data || apply_filters( 'friends_deactivate_plugin_cache', false ) ) {
			$remote = wp_remote_get(
				'https://wpfriends.at/plugins.json',
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( ! is_wp_error( $remote ) && 200 === wp_remote_retrieve_response_code( $remote ) && ! empty( $remote['body'] ) ) {
				$data = array();
				foreach ( json_decode( $remote['body'] ) as $slug => $plugin_data ) {
					$data[ $slug ] = $plugin_data;
				}
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
	 * @param      stdClass $res     The resource.
	 * @param      string   $action  The action.
	 * @param      array    $args    The arguments.
	 *
	 * @return     bool|stdClass  ( description_of_the_return_value )
	 */
	public static function override_plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return false;
		}

		$plugins = self::get_plugins();
		if ( ! isset( $plugins[ $args->slug ] ) ) {
			return false;
		}

		return $plugins[ $args->slug ];
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

		foreach ( self::get_plugins() as $plugin_slug => $data ) {
			if ( $data && version_compare( $transient->checked[ $data->slug . '/' . $data->slug . '.php' ], $data->version, '<' ) && version_compare( $data->requires, get_bloginfo( 'version' ), '<' ) ) {
				$res = new stdClass();
				$res->slug = $data->slug;
				$res->plugin = $data->slug . '/' . $data->slug . '.php';
				$res->new_version = $data->version;
				$res->tested = $data->tested;
				$res->package = $data->download_link;
				$transient->response[ $res->plugin ] = $res;
			}
		}

		return $transient;
	}

	/**
	 * Render display template for each plugin.
	 *
	 * @param      array  $plugin          Original data passed to init().
	 * @param      array  $api             Results from plugins_api.
	 * @param      string $button_text     Text for the button.
	 * @param      string $button_classes  Classnames for the button.
	 *
	 * @since      1.0
	 */
	public static function render_template( $plugin, $api, $button_text, $button_classes ) {
		$more_info_url = $api->more_info;
		$install_url = add_query_arg(
			array(
				'tab'      => 'install-plugin',
				'plugin'   => $api->slug,
				'_wpnonce' => wp_create_nonce( 'install-plugin_' . $api->slug ),
			),
			admin_url( 'update.php' )
		);
		$deactivate_button_class = '';
		if ( preg_match( '/\b(install|activate)\b/', $button_classes ) ) {
			$deactivate_button_class = 'hidden';
		}
		?>
		<div class="wp-list-table widefat plugin-install">
			<div id="the-list">
				<div class="plugin-card plugin-card-<?php echo $api->slug; ?>">
					<div class="plugin-card-top">
						<div class="name column-name">
							<h3>
								<a class="thickbox open-plugin-details-modal" href="<?php echo esc_url( $more_info_url ); ?>"><?php echo $api->name; ?> <?php echo $api->version; ?></a>
							</h3>
						</div>

						<div class="desc column-description">
							<p><?php echo $api->short_description; ?></p>
							<p class="authors">
								<cite>
									<?php /* translators: %s is a plugin author */ printf( __( 'By %s' ), $api->author ); ?>
								</cite>
							</p>
						</div>
					</div>

					<div class="plugin-card-bottom">
						<a class="<?php echo $button_classes; ?>" data-slug="<?php echo $api->slug; ?>" data-name="<?php echo $api->name; ?>" href="<?php echo esc_url( $install_url ); ?>" aria-label="<?php echo /* translators: %1$s is a plugin name, %2$s is a plugin version. */ sprintf( esc_html__( 'Install %1$s %2$s now', 'framework' ), $api->name, $api->version ); ?>"><?php echo esc_html( $button_text ); ?></a>

						<a class="button thickbox" href="<?php echo esc_url( $more_info_url ); ?>" aria-label="<?php echo /* translators: %s is a plugin name. */ sprintf( esc_html__( 'More information about %s' ), $api->name ); ?>" data-title="<?php echo $api->name; ?>"><?php _e( 'More Details' ); ?></a>

						<a class="button thickbox deactivate <?php echo $deactivate_button_class; ?>"
							data-slug="<?php echo esc_attr( $api->slug ); ?>"
							data-name="<?php echo esc_attr( $api->name ); ?>"
							href="<?php echo esc_url( $install_url ); ?>">
							<?php echo esc_html_e( 'Deactivate' ); ?>
						</a>

					</div>
				</div>
			</div>
		</div>
		<?php
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

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
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

		deactivate_plugins( $plugin . '/' . $plugin . '.php' );

		$msg = $plugin . ' successfully deactivated.';

		$json = array(
			'status' => 'success',
			'msg'    => $msg,
		);

		wp_send_json( $json );

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

			if ( $slug ) {
				if ( $slug === $plugin_slug ) {
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
		wp_enqueue_script( 'friends-plugin-installer', plugins_url( 'plugin-installer.js', __FILE__ ), array( 'jquery' ), Friends::VERSION, true );
		wp_localize_script(
			'friends-plugin-installer',
			'friends_plugin_installer_localize',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'admin_nonce'    => wp_create_nonce( 'friends_installer_nonce' ),
				'install_now'    => __( 'Are you sure you want to install this plugin?', 'friends' ),
				'install_btn'    => __( 'Install Now', 'friends' ),
				'activate_btn'   => __( 'Activate' ),
				'deactivate_btn' => __( 'Dectivate' ),
				'installed_btn'  => __( 'Active' ),
			)
		);
	}
}
