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
class Widget_Friends_List extends Widget_Base_Friends_List {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-friend-list',
			__( 'Friend List', 'friends' ),
			array(
				'description' => __( 'Shows a list of your friends.', 'friends' ),
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

		$subscriptions = User_Query::all_subscriptions();
		$widget_id = '';
		if ( ! empty( $args['widget_id'] ) ) {
			$widget_id = $args['widget_id'];
		}

		if ( 0 !== $subscriptions->get_total() ) {
			echo $args['before_widget'];
			$this->list_friends(
				array_merge(
					array(
						'widget_id' => $widget_id . '-subscriptions',
					),
					$args
				),
				'<span class="dashicons dashicons-admin-users"></span> ' . sprintf(
					// translators: %s is the number of subscriptions.
					_n( 'Subscription %s', 'Subscriptions %s', $subscriptions->get_total(), 'friends' ),
					'<span class="subscription-count">' . $subscriptions->get_total() . '</span>'
				),
				$subscriptions
			);
			echo $args['after_widget'];
		}

		do_action( 'friends_widget_friend_list_after', $this, $args );
	}
}
