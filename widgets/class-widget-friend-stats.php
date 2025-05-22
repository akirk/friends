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

		$follower_count = 0;
		$blog_follower_count = 0;

		$instance = wp_parse_args( $instance, $this->defaults() );
		$show_followers = false;
		$show_blog_followers = false;
		if ( class_exists( '\ActivityPub\Collection\Followers' ) ) {
			$activitypub_actor_mode = \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
			if ( ACTIVITYPUB_ACTOR_MODE === $activitypub_actor_mode || ACTIVITYPUB_ACTOR_AND_BLOG_MODE === $activitypub_actor_mode ) {
				$follower_count = \ActivityPub\Collection\Followers::count_followers( get_current_user_id() );
				$show_followers = true;
			}
			if ( ACTIVITYPUB_BLOG_MODE === $activitypub_actor_mode || ACTIVITYPUB_ACTOR_AND_BLOG_MODE === $activitypub_actor_mode ) {
				$blog_follower_count = \ActivityPub\Collection\Followers::count_followers( \ActivityPub\Collection\Actors::BLOG_USER_ID );
				$show_blog_followers = true;
			}
		}
		echo $args['before_widget'];

		$open = Frontend::get_widget_open_state( $args['widget_id'] );
		?>
		<details class="accordion" <?php echo esc_attr( $open ); ?> data-id="<?php echo esc_attr( $args['widget_id'] ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends_widget_state' ) ); ?>">
			<summary class="accordion-header">
		<?php
		echo $args['before_title'];
		echo '<span class="dashicons dashicons-chart-area"></span> ';
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
				<li class="friend-stats-followers menu-item">
					<a class="followers" href="<?php echo esc_url( home_url( '/friends/followers/' ) ); ?>">
					<?php
					echo esc_html(
						sprintf(
						/* translators: %s: number of followers */
							_n( '%s Follower', '%s Followers', $follower_count, 'friends' ),
							$follower_count
						)
					);
					?>
					</a>
				</li>
				<?php endif; ?>
			<?php if ( $show_blog_followers ) : ?>
				<li class="friend-stats-followers menu-item">
					<a class="followers" href="<?php echo esc_url( home_url( '/friends/blog-followers/' ) ); ?>">
					<?php
					echo esc_html(
						sprintf(
						/* translators: %s: number of followers */
							_n( '%s Blog Follower', '%s Blog Followers', $blog_follower_count, 'friends' ),
							$blog_follower_count
						)
					);
					?>
					</a>
				</li>
				<?php endif; ?>
				<li class="friend-stats-friends menu-item">
					<a href="<?php echo esc_attr( admin_url( 'admin.php?page=friends-list' ) ); ?>">
						<?php
						echo wp_kses(
							sprintf(
							/* translators: %s: number of friends */
								_n( '%s Friend', '%s Friends', $friends_count, 'friends' ),
								'<span class="friends">' . $friends_count . '</span>'
							),
							array( 'span' => array( 'class' => true ) )
						);
						?>
					</a>
				</li>
				<li class="friend-stats-subscriptions menu-item">
					<a href="<?php echo esc_attr( admin_url( 'admin.php?page=friends-list' ) ); ?>">
						<?php
							echo wp_kses(
								sprintf(
								/* translators: %s: number of subscriptions */
									_n( '%s Subscription', '%s Subscriptions', $subscriptions_count, 'friends' ),
									'<a class="subscriptions">' . $subscriptions_count . '</a>'
								),
								array( 'span' => array( 'class' => true ) )
							);
						?>
					</a>
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
