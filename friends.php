<?php
/**
 * Plugin name: Friends
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends
 * Version: 2.0.2
 *
 * Description: Decentralized Social Networking with WordPress. Connect WordPresses through friend requests and read each other’s (private) posts in a feed reader.
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
define( 'FRIENDS_PLUGIN_FILE', plugin_dir_path( __FILE__ ) . '/' . basename( __FILE__ ) );

require_once __DIR__ . '/libs/Mf2/Parser.php';
require_once __DIR__ . '/libs/gutenberg-everywhere/classes/gutenberg-handler.php';
require_once __DIR__ . '/includes/class-gutenberg-everywhere-friends-message.php';

require_once __DIR__ . '/includes/class-user.php';
require_once __DIR__ . '/includes/class-user-feed.php';
require_once __DIR__ . '/includes/class-user-query.php';

// Classes to be implemented or used by parser plugins.
require_once __DIR__ . '/feed-parsers/class-feed-parser.php';
require_once __DIR__ . '/feed-parsers/class-feed-item.php';

require_once __DIR__ . '/includes/class-access-control.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-automatic-status.php';
require_once __DIR__ . '/includes/class-blocks.php';
require_once __DIR__ . '/includes/class-feed.php';
require_once __DIR__ . '/includes/class-frontend.php';
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
register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Friends', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Friends', 'deactivate_plugin' ) );
register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Friends', 'uninstall_plugin' ) );

// Register widgets.
require_once __DIR__ . '/widgets/class-widget-refresh.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Refresh', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-friend-list.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Friend_List', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-friend-request.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Friend_Request', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-new-private-post.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_New_Private_Post', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-post-formats.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Post_Formats', 'register' ) );

require_once __DIR__ . '/widgets/class-widget-header.php';
add_action( 'widgets_init', array( __NAMESPACE__ . '\Widget_Header', 'register' ) );

// Register bundled parsers.
add_action(
	'friends_load_parsers',
	function( Feed $friends_feed ) {
		require_once __DIR__ . '/feed-parsers/class-feed-parser-simplepie.php';
		$friends_feed->register_parser( 'simplepie', new Feed_Parser_SimplePie );
	}
);

add_action(
	'friends_load_parsers',
	function( Feed $friends_feed ) {
		require_once __DIR__ . '/feed-parsers/class-feed-parser-microformats.php';
		$friends_feed->register_parser( 'microformats', new Feed_Parser_Microformats );
	}
);

add_action(
	'friends_load_parsers',
	function( Feed $friends_feed ) {
		require_once __DIR__ . '/feed-parsers/class-feed-parser-json-feed.php';
		$friends_feed->register_parser( 'jsonfeed', new Feed_Parser_JSON_Feed );
	}
);
