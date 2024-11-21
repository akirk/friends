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
class Widget_Starred_Friends_List extends Widget_Base_Friends_List {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-starred-friend-list',
			__( 'Starred Friends', 'friends' ),
			array(
				'description' => __( 'Shows a list of your starred friends and subscriptions.', 'friends' ),
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
		$friends = User_Query::starred_friends_subscriptions();
		if ( ! $friends->get_total() ) {
			return;
		}
		$instance = wp_parse_args( $instance, $this->defaults() );

		echo $args['before_widget'];

		$this->list_friends(
			$args,
			'<span class="dashicons dashicons-star-filled"></span> ' . _x( 'Starred', 'Starred Friends', 'friends' ),
			$friends
		);

		do_action( 'friends_widget_starred_friend_list_after', $this, $args );

		echo $args['after_widget'];
	}
}
