<?php
/**
 * Plugin name: Friends
 * Plugin author: Alex Kirk
 * Plugin URI: https://github.com/akirk/friends
 * Version: 0.5
 *
 * Description: Connect WordPresses and communicate privately with your friends.
 *
 * License: GPL2
 * Text Domain: friends
 * Domain Path: /languages/
 */

include __DIR__ . '/class-friends.php';
include __DIR__ . '/class-friends-admin.php';
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

