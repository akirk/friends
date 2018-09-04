<?php
/**
 * Plugin name: Friends
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends
 * Version: 0.9.1
 *
 * Description: Connect WordPresses and communicate privately with your friends.
 *
 * License: GPL2
 * Text Domain: friends
 * Domain Path: /languages/
 *
 * @package Friends
 */

/**
 * This loads the Friends plugin.
 */

include __DIR__ . '/class-friends-access-control.php';
include __DIR__ . '/class-friends-admin.php';
include __DIR__ . '/class-friends-feed.php';
include __DIR__ . '/class-friends-notifications.php';
include __DIR__ . '/class-friends-page.php';
include __DIR__ . '/class-friends-reactions.php';
include __DIR__ . '/class-friends-recommendation.php';
include __DIR__ . '/class-friends-rest.php';
include __DIR__ . '/class-friends-3rd-parties.php';
include __DIR__ . '/class-friends-shortcodes.php';
include __DIR__ . '/class-friends.php';

add_action( 'plugins_loaded', array( 'Friends', 'init' ) );
register_activation_hook( __FILE__, array( 'Friends', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( 'Friends', 'deactivate_plugin' ) );
register_uninstall_hook( __FILE__, array( 'Friends', 'uninstall_plugin' ) );

include __DIR__ . '/widgets/class-friends-widget-refresh.php';
add_action( 'widgets_init', array( 'Friends_Widget_Refresh', 'register' ) );

include __DIR__ . '/widgets/class-friends-widget-friend-list.php';
add_action( 'widgets_init', array( 'Friends_Widget_Friend_List', 'register' ) );

include __DIR__ . '/widgets/class-friends-widget-friend-request.php';
add_action( 'widgets_init', array( 'Friends_Widget_Friend_Request', 'register' ) );

include __DIR__ . '/widgets/class-friends-widget-new-private-post.php';
add_action( 'widgets_init', array( 'Friends_Widget_New_Private_Post', 'register' ) );
