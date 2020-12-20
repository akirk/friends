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
	private static $plugins = array(
		'friends-parser-rss-bridge'   => 'https://wpfriends.at/plugins/friends-parser-rss-bridge/update.json',
		'friends-parser-fraidyscrape' => 'https://wpfriends.at/plugins/friends-parser-fraidyscrape/update.json',
	);

	/**
	 * Register the WordPress hooks
	 */
	public static function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_cnkt_plugin_installer', array( __CLASS__, 'cnkt_plugin_installer' ) );
		add_action( 'wp_ajax_cnkt_plugin_activation', array( __CLASS__, 'cnkt_plugin_activation' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'override_plugin_info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'override_plugin_push_update' ) );
	}

	/**
	 * Initialize the display of the plugins.
	 *
	 * @since      1.0
	 */
	public static function init() {
		?>
		<div class="cnkt-plugin-installer">
		<?php
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		foreach ( array_keys( self::$plugins ) as $plugin_slug ) :

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
						$button_classes = 'button disabled';
						$button_text    = __( 'Activated', 'friends' );
					} else {
						$button_classes = 'activate button button-primary';
						$button_text    = __( 'Activate', 'friends' );
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
	 * Gets the plugin information from the rmeote server.
	 *
	 * @param      string $plugin_slug  The plugin slug.
	 *
	 * @return    object  The plugin information.
	 */
	public static function get_plugin_info( $plugin_slug ) {
		if ( ! isset( self::$plugins[ $plugin_slug ] ) ) {
			return new WP_Error( 'unknown-plugin' );
		}
		$remote = get_transient( 'friends_plugin_info_' . $plugin_slug );
		if ( false === $remote ) {
			$remote = wp_remote_get(
				self::$plugins[ $plugin_slug ],
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( ! is_wp_error( $remote ) && 200 === wp_remote_retrieve_response_code( $remote ) && ! empty( $remote['body'] ) ) {
				set_transient( 'friends_plugin_info_' . $plugin_slug, $remote, 43200 ); // 12 hours cache
			}
		}

		return $remote;
	}

	/**
	 * Override the plugin info.
	 *
	 * Thanks https://rudrastyh.com/wordpress/self-hosted-plugin-update.html
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

		$remote = self::get_plugin_info( $args->slug );
		if ( ! is_wp_error( $remote ) && 200 === wp_remote_retrieve_response_code( $remote ) && ! empty( $remote['body'] ) ) {
			return json_decode( $remote['body'] );
		}

		return false;
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

		foreach ( self::$plugins as $plugin_slug => $url ) {
			$remote = self::get_plugin_info( $plugin_slug );
			if ( ! is_wp_error( $remote ) && 200 === wp_remote_retrieve_response_code( $remote ) && ! empty( $remote['body'] ) ) {

				$remote = json_decode( $remote['body'] );

				if ( $remote && version_compare( '1.0', $remote->version, '<' ) && version_compare( $remote->requires, get_bloginfo( 'version' ), '<' ) ) {
					$res = new stdClass();
					$res->slug = $remote->slug;
					$res->plugin = $remote->slug . '/' . $remote->slug . '.php';
					$res->new_version = $remote->version;
					$res->tested = $remote->tested;
					$res->package = $remote->download_url;
					$transient->response[ $res->plugin ] = $res;
				}
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
		if ( isset( $api->icons['1x'] ) ) {
			$icon = $api->icons['1x'];
		} else {
			$icon = $api->icons['default'];
		}

		$install_url = add_query_arg(
			array(
				'action'   => 'install-plugin',
				'plugin'   => $api->slug,
				'_wpnonce' => wp_create_nonce( 'install-plugin_' . $api->slug ),
			),
			get_admin_url( null, '/update.php' )
		);
		?>
		<div class="plugin">
			<div class="plugin-wrap">
				<img src="<?php echo esc_url( $icon ); ?>" alt="">
				<h2><?php echo esc_html( $api->name ); ?></h2>
				<p><?php echo esc_html( $api->short_description ); ?></p>

				<p class="plugin-author"><?php esc_html_e( 'By', 'friends' ); ?> <?php echo $api->author; // phpcs:ignore ?></p>
			</div>
			<ul class="activation-row">
				<li>
					<a class="<?php echo esc_attr( $button_classes ); ?>"
						data-slug="<?php echo esc_attr( $api->slug ); ?>"
						data-name="<?php echo esc_attr( $api->name ); ?>"
						href="<?php echo esc_url( $install_url ); ?>">
						<?php echo esc_html( $button_text ); ?>
					</a>
				</li>
				<li>
					<a href="https://wordpress.org/plugins/<?php echo esc_attr( $api->slug ); ?>/" target="_blank">
						<?php esc_html_e( 'More Details', 'friends' ); ?>
					</a>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * An Ajax method for installing plugin.
	 */
	public static function cnkt_plugin_installer() {

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html( __( 'Sorry, you are not allowed to install plugins on this site.', 'friends' ) ) );
		}

		$nonce  = $_POST['nonce']; // phpcs:ignore
		$plugin = $_POST['plugin']; // phpcs:ignore

		// Check our nonce, if they don't match then bounce!
		if ( ! wp_verify_nonce( $nonce, 'cnkt_installer_nonce' ) ) {
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
	public static function cnkt_plugin_activation() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html( __( 'Sorry, you are not allowed to activate plugins on this site.', 'friends' ) ) );
		}

		$nonce  = $_POST['nonce']; // phpcs:ignore
		$plugin = $_POST['plugin']; // phpcs:ignore

		// Check our nonce, if they don't match then bounce!
		if ( ! wp_verify_nonce( $nonce, 'cnkt_installer_nonce' ) ) {
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
		wp_enqueue_script( 'plugin-installer', plugins_url( 'plugin-installer.js', __FILE__ ), array( 'jquery' ), Friends::VERSION, true );
		wp_localize_script(
			'plugin-installer',
			'cnkt_installer_localize',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'admin_nonce'   => wp_create_nonce( 'cnkt_installer_nonce' ),
				'install_now'   => __( 'Are you sure you want to install this plugin?', 'friends' ),
				'install_btn'   => __( 'Install Now', 'friends' ),
				'activate_btn'  => __( 'Activate', 'friends' ),
				'installed_btn' => __( 'Activated', 'friends' ),
			)
		);

		wp_enqueue_style( 'plugin-installer', plugins_url( 'plugin-installer.css', __FILE__ ), array(), Friends::VERSION );
	}
}
