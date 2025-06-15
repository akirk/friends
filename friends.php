<?php
/**
 * Plugin name: Friends
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends
 * Version: 3.6.0
 * Requires PHP: 7.2

 * Description: Follow others via RSS and ActivityPub and read their posts on your own WordPress.
 *
 * License: GPL2
 * Text Domain: friends
 * Domain Path: /languages/
 *
 * @package Friends
 */

namespace Friends;

/**
 * This file loads all the dependencies the Friends plugin.
 */

defined( 'ABSPATH' ) || exit;
define( 'FRIENDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FRIENDS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FRIENDS_PLUGIN_FILE', plugin_dir_path( __FILE__ ) . '/' . basename( __FILE__ ) );
define( 'FRIENDS_VERSION', '3.6.0' );

require_once __DIR__ . '/libs/Mf2/Parser.php';

require_once __DIR__ . '/includes/class-user.php';
require_once __DIR__ . '/includes/class-user-feed.php';
require_once __DIR__ . '/includes/class-user-query.php';
require_once __DIR__ . '/includes/class-subscription.php';

// Classes to be implemented or used by parser plugins.
require_once __DIR__ . '/feed-parsers/class-feed-parser.php';
require_once __DIR__ . '/feed-parsers/class-feed-parser-v2.php';
require_once __DIR__ . '/feed-parsers/class-feed-item.php';

require_once __DIR__ . '/includes/class-access-control.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-automatic-status.php';
require_once __DIR__ . '/includes/class-blocks.php';
require_once __DIR__ . '/includes/class-feed.php';
require_once __DIR__ . '/includes/class-frontend.php';
require_once __DIR__ . '/includes/class-import.php';
require_once __DIR__ . '/includes/class-logging.php';
require_once __DIR__ . '/includes/class-messages.php';
require_once __DIR__ . '/includes/class-notifications.php';
require_once __DIR__ . '/includes/class-plugin-installer.php';
require_once __DIR__ . '/includes/class-reactions.php';
require_once __DIR__ . '/includes/class-rest.php';
require_once __DIR__ . '/includes/class-shortcodes.php';
require_once __DIR__ . '/includes/class-template-loader.php';
require_once __DIR__ . '/includes/class-third-parties.php';
require_once __DIR__ . '/includes/class-friends.php';

add_action( 'plugins_loaded', array( __NAMESPACE__ . '\Friends', 'init' ) );
add_action( 'admin_init', array( __NAMESPACE__ . '\Plugin_Installer', 'register_hooks' ) );

if ( is_admin() && version_compare( get_option( 'friends_plugin_version' ), FRIENDS_VERSION, '<' ) ) {
	add_action( 'admin_init', array( __NAMESPACE__ . '\Friends', 'upgrade_plugin' ) );
}

add_action( 'upgrader_process_complete', array( __NAMESPACE__ . '\Friends', 'upgrade_plugin_trigger' ), 10, 2 );
register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Friends', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Friends', 'deactivate_plugin' ) );
register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Friends', 'uninstall_plugin' ) );

add_action( 'activate_blog', array( __NAMESPACE__ . '\Friends', 'activate_plugin' ) );
add_action( 'wp_initialize_site', array( __NAMESPACE__ . '\Friends', 'activate_for_blog' ) );

// Register widgets.
add_filter( 'customize_loaded_components', array( __NAMESPACE__ . '\Frontend', 'ensure_widget_editing' ) );

require_once __DIR__ . '/widgets/class-widget-base-friends-list.php';
require_once __DIR__ . '/widgets/class-widget-refresh.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Refresh', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-friends-list.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Friends_List', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-starred-friends-list.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Starred_Friends_List', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-recent-friends-list.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Recent_Friends_List', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-friend-request.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Friend_Request', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-friend-stats.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Friend_Stats', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-new-private-post.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_New_Private_Post', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-post-formats.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Post_Formats', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-header.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Header', 'register' ) );

// Register bundled parsers.
add_action(
	'friends_load_parsers',
	function ( Feed $friends_feed ) {
		require_once __DIR__ . '/feed-parsers/class-feed-parser-simplepie.php';
		$friends_feed->register_parser( Feed_Parser_SimplePie::SLUG, new Feed_Parser_SimplePie( $friends_feed ) );
	},
	9
);

add_action(
	'friends_load_parsers',
	function ( Feed $friends_feed ) {
		require_once __DIR__ . '/feed-parsers/class-feed-parser-microformats.php';
		$friends_feed->register_parser( Feed_Parser_Microformats::SLUG, new Feed_Parser_Microformats() );
	},
	9
);

add_action(
	'friends_load_parsers',
	function ( Feed $friends_feed ) {
		require_once __DIR__ . '/feed-parsers/class-feed-parser-json-feed.php';
		$friends_feed->register_parser( Feed_Parser_JSON_Feed::SLUG, new Feed_Parser_JSON_Feed() );
	},
	9
);

add_action(
	'friends_load_parsers',
	function ( \Friends\Feed $friends_feed ) {
		if ( ! class_exists( '\Activitypub\Activitypub' ) ) {
			// ActivityPub plugin not active.
			return;
		}

		global $wp_filter;
		if ( isset( $wp_filter['friends_load_parsers']->callbacks[10] ) && class_exists( '\\ReflectionFunction' ) ) {
			// Unhook the parser from ActivityPub 0.14.
			foreach ( $wp_filter['friends_load_parsers']->callbacks[10] as $key => $hook ) {
				$r = new \ReflectionFunction( $hook['function'] );
				if ( \str_ends_with( $r->getFileName(), 'activitypub.php' ) ) {
					remove_filter( 'friends_load_parsers', $hook['function'], 10 );
					break;
				}
			}
		}

		require_once __DIR__ . '/feed-parsers/class-feed-parser-activitypub.php';
		$friends_feed->register_parser( Feed_Parser_ActivityPub::SLUG, new Feed_Parser_ActivityPub( $friends_feed ) );
	},
	9
);

// Global functions, for phpcs.
/**
 * Validate feed catch_all
 *
 * @param  array $catch_all The catch_all value to.
 * @return array            A valid catch_all
 */
function validate_feed_catch_all( $catch_all ) {
	return Feed::validate_feed_catch_all( $catch_all );
}

/**
 * Validate feed item rules
 *
 * @param  array $rules The rules to validate.
 * @return array        The valid rules.
 */
function validate_feed_rules( $rules ) {
	return Feed::validate_feed_rules( $rules );
}

/**
 * Check whether this is a valid URL
 *
 * @param string $url The URL to check.
 * @return false|string URL or false on failure.
 */
function check_url( $url ) {
	return Friends::check_url( $url );
}

// Integrations.

require_once __DIR__ . '/integrations/class-enable-mastodon-apps.php';
Enable_Mastodon_Apps::init();
