<?php
/**
 * Friend List Widget
 *
 * A widget that displays a list of your friends.
 *
 * @package Friends
 * @since 0.3
 */

namespace Friends;

/**
 * This is the class for the Friend List Widget.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Widget_Recent_Friends_List extends Widget_Base_Friends_List {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-recent-friend-list',
			__( 'Recent Friend List', 'friends' ),
			array(
				'description' => __( 'Shows a list of your Recent friends and subscriptions.', 'friends' ),
			)
		);
	}

	/**
	 * Render the widget.
	 *
	 * @param array $args Sidebar arguments.
	 * @param array $instance Widget instance settings.
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( $instance, $this->defaults() );

		echo $args['before_widget'];
		$this->list_friends(
			$args,
			'<span class="dashicons dashicons-admin-users"></span> ' . __( 'Recent Friends', 'friends' ),
			User_Query::recent_friends_subscriptions( 5 )
		);

		do_action( 'friends_widget_lastest_friend_list_after', $this, $args );

		echo $args['after_widget'];
	}
}
