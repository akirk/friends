<?php
/**
 * Friend List Widget
 *
 * A widget that displays a list of your friends.
 *
 * @package Friends
 * @since 0.3
 */

/**
 * This is the class for the Friend List Widget.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Widget_Friend_List extends WP_Widget {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-friend-list', __( 'Friend List', 'friends' ), array(
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

		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		$friend_requests = new WP_User_Query( array( 'role' => 'friend_request' ) );

		?><div class="friend-request-count-message">
		<?php if ( $friend_requests->get_total() > 0 ) : ?>
			<a href="<?php echo esc_attr( self_admin_url( 'users.php?role=friend_request' ) ); ?>">
			<?php
			// translators: %s is the number of friends.
			echo wp_kses( sprintf( _n( 'You have %s friend request.', ' You have %s friend requests.', $friend_requests->get_total(), 'friends' ), '<span class="friend-request-count">' . $friend_requests->get_total() . '</span>' ), array( 'span' => array( 'class' => array() ) ) );
			?>
			</a>
		<?php endif; ?>
		</div>

		<span class="friend-count-message">
		<?php
		$friends = new WP_User_Query( array( 'role' => 'friend' ) );
		if ( 0 === $friends->get_total() ) {
			esc_html_e( "You don't have any friends yet.", 'friends' );
		} else {
			// translators: %s is the number of friends.
			echo wp_kses( sprintf( _n( 'You have %s friend:', ' You have %s friends:', $friends->get_total(), 'friends' ), '<span class="friend-count">' . $friends->get_total() . '</span>' ), array( 'span' => array( 'class' => array() ) ) );
		}
		?>
		</span>

		<ul class="friend-list">
		<?php foreach ( $friends->get_results() as $friend_user ) : ?>
			<li><a href="<?php echo esc_url( site_url( '/friends/' . $friend_user->user_login . '/' ) ); ?>"><?php echo esc_html( $friend_user->display_name ); ?></a>
				<small><a href="<?php echo esc_url( $friend_user->user_url ); ?>" class="auth-link" data-token="<?php echo esc_attr( get_user_option( 'friends_out_token', $friend_user->ID ) ); ?>">visit</a></small></li>
		<?php endforeach; ?>
		</ul>

		<a href="<?php echo esc_attr( self_admin_url( 'users.php' ) ); ?>"><?php esc_html_e( 'Manage Friends', 'friends' ); ?></a>
		<?php
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
		$instance = $this->defaults;

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
			'title' => '',
		);
	}

	/**
	 * Register this widget.
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}
}

