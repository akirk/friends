<?php
/**
 * Friend Stats Widget
 *
 * A widget that displays a stats of your friends.
 *
 * @package Friends
 * @since 2.9.0
 */

namespace Friends;

/**
 * This is the class for the Friend Stats Widget.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Widget_Friend_Stats extends \WP_Widget {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-stats',
			__( 'Friend Stats', 'friends' ),
			array(
				'description' => __( 'Show stats about yourself and friends.', 'friends' ),
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
		$friends = User_Query::all_friends();
		$friends_count = $friends->get_total();

		$subscriptions = User_Query::all_subscriptions();
		$subscriptions_count = $subscriptions->get_total();

		$instance = wp_parse_args( $instance, $this->defaults() );
		$show_followers = false;
		if ( class_existS( '\ActivityPub\Collection\Followers' ) ) {
			$follower_count = \ActivityPub\Collection\Followers::count_followers( get_current_user_id() );
			$show_followers = true;
		}
		echo $args['before_widget'];
		?>
		<details class="accordion" open>
			<summary class="accordion-header">
		<?php
		echo $args['before_title'];
		echo wp_kses(
			$instance['title'],
			array(
				'span' => array( 'class' => array() ),
				'a'    => array(
					'class' => array(),
					'href'  => array(),
				),
			)
		);
		echo $args['after_title'];
		?>
		</summary>
		<ul class="friend-stats menu menu-nav">
			<?php if ( $show_followers ) : ?>
				<li class="friend-stats-followers">
					<?php
					echo wp_kses(
						sprintf(
						/* translators: %s: number of followers */
							_n( '%s Follower', '%s Followers', $follower_count, 'friends' ),
							'<span class="followers">' . $follower_count . '</span>'
						),
						array( 'span' => array( 'class' => array() ) )
					);
					?>
				</li>
				<?php endif; ?>
				<li class="friend-stats-friends">
					<?php
					echo wp_kses(
						sprintf(
						/* translators: %s: number of friends */
							_n( '%s Friend', '%s Friends', $friends_count, 'friends' ),
							'<span class="friends">' . $friends_count . '</span>'
						),
						array( 'span' => array( 'class' => array() ) )
					);
					?>
				</li>
				<li class="friend-stats-subscriptions">
					<?php
					echo wp_kses(
						sprintf(
						/* translators: %s: number of subscriptions */
							_n( '%s Subscription', '%s Subscriptions', $subscriptions_count, 'friends' ),
							'<span class="subscriptions">' . $subscriptions_count . '</span>'
						),
						array( 'span' => array( 'class' => array() ) )
					);
					?>
				</li>

		</ul>
		<?php

		do_action( 'friends_widget_starred_friend_list_after', $this, $args );

		echo $args['after_widget'];
	}

	/**
	 * Update widget configuration.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array Sanitized instance settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $this->defaults();

		return $instance;
	}

	/**
	 * Return an associative array of default values
	 *
	 * These values are used in new widgets.
	 *
	 * @return array Array of default values for the Widget's options
	 */
	public function defaults() {
		return array(
			'title' => __( 'Friend Stats', 'friends' ),
		);
	}

	/**
	 * Register this widget.
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}
}
