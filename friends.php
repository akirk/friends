<?php
/**
 * Plugin name: Friends
 * Plugin author: Alex Kirk
 * Version: 0.2
 *
 * Description: Private blogging with WordPress through friendships and RSS.
 */
include __DIR__ . '/class-friends.php';

add_action( 'plugins_loaded', array( 'Friends', 'init' ) );
add_action( 'widgets_init', array( 'Friends_Widget_Refresh', 'register' ) );
add_action( 'widgets_init', array( 'Friends_Widget_Friend_List', 'register' ) );
add_action( 'widgets_init', array( 'Friends_Widget_Friend_Request', 'register' ) );
register_activation_hook( __FILE__, array( 'Friends', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( 'Friends', 'deactivate_plugin' ) );
