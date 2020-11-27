<?php
/**
 * Plugin name: Friends
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends
 * Version: 1.0
 *
 * Description: Decentralized Social Networking with WordPress. Connect WordPresses through friend requests and read each other’s (private) posts in a feed reader.
 *
 * License: GPL2
 * Text Domain: friends
 * Domain Path: /languages/
 *
 * @package Friends
 */

/**
 * This file loads all the dependencies the Friends plugin.
 */

defined( 'ABSPATH' ) || exit;

include __DIR__ . '/class-friend-user.php';
include __DIR__ . '/class-friend-user-feed.php';
include __DIR__ . '/class-friend-user-query.php';

include __DIR__ . '/class-friends-feed-parser.php';

include __DIR__ . '/class-friends-access-control.php';
include __DIR__ . '/class-friends-admin.php';
include __DIR__ . '/class-friends-api.php';
include __DIR__ . '/class-friends-blocks.php';
include __DIR__ . '/class-friends-feed.php';
include __DIR__ . '/class-friends-frontend.php';
include __DIR__ . '/class-friends-logging.php';
include __DIR__ . '/class-friends-notifications.php';
include __DIR__ . '/class-friends-post-types.php';
include __DIR__ . '/class-friends-reactions.php';
include __DIR__ . '/class-friends-recommendation.php';
include __DIR__ . '/class-friends-rest.php';
include __DIR__ . '/class-friends-shortcodes.php';
include __DIR__ . '/class-friends-3rd-parties.php';
include __DIR__ . '/class-friends.php';

add_action( 'plugins_loaded', array( 'Friends', 'init' ) );
register_activation_hook( __FILE__, array( 'Friends', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( 'Friends', 'deactivate_plugin' ) );
register_uninstall_hook( __FILE__, array( 'Friends', 'uninstall_plugin' ) );

// Register widgets.
include __DIR__ . '/widgets/class-friends-widget-refresh.php';
add_action( 'widgets_init', array( 'Friends_Widget_Refresh', 'register' ) );

include __DIR__ . '/widgets/class-friends-widget-friend-list.php';
add_action( 'widgets_init', array( 'Friends_Widget_Friend_List', 'register' ) );

include __DIR__ . '/widgets/class-friends-widget-friend-request.php';
add_action( 'widgets_init', array( 'Friends_Widget_Friend_Request', 'register' ) );

include __DIR__ . '/widgets/class-friends-widget-new-private-post.php';
add_action( 'widgets_init', array( 'Friends_Widget_New_Private_Post', 'register' ) );

// Register bundled parsers.
add_action(
	'friends_register_parser',
	function( Friends_Feed $friends_feed ) {
		include __DIR__ . '/class-friends-feed-parser-simplepie.php';
		$friends_feed->register_parser( 'simplepie', new Friends_Feed_Parser_SimplePie );
	}
);

add_action(
	'friends_register_parser',
	function( Friends_Feed $friends_feed ) {
		include __DIR__ . '/class-friends-feed-parser-microformats.php';
		$friends_feed->register_parser( 'microformats', new Friends_Feed_Parser_Microformats );
	}
);
