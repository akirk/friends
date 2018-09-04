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
		echo $args['before_title'];

		$friends         = Friends::all_friends();
		$friend_requests = Friends::all_friend_requests();

		// translators: %s is the number of your friends.
		$friends_title = sprintf( _n( '%s Friend', '%s Friends', $friends->get_total(), 'friends' ), '<span class="friend-count">' . $friends->get_total() . '</span>' );

		?><a href="<?php echo esc_attr( self_admin_url( 'users.php' ) ); ?>">
		<?php
		if ( $friend_requests->get_total() > 0 ) {
			echo wp_kses(
				// translators: %1$s is the string "%s Friend", %2$s is a URL, %3$s is the number of open friend requests.
				sprintf( _n( '%1$s <a href=%2$s>(%3$s request)</a>', '%1$s <a href=%2$s>(%3$s requests)</a>', $friend_requests->get_total(), 'friends' ), $friends_title, '"' . esc_attr( self_admin_url( 'users.php?role=friend_request' ) ) . '" class="open-requests"', '<span class="friend-count">' . $friend_requests->get_total() . '</span>' ), array(
					'span' => array( 'class' => array() ),
					'a'    => array(
						'class' => array(),
						'href'  => array(),
					),
				)
			);
		} else {
			echo wp_kses( $friends_title, array( 'span' => array( 'class' => array() ) ) );

		}
		?>
		</a>
		<?php
		echo $args['after_title'];

		if ( 0 === $friends->get_total() ) {
			?>
			<span class="friend-count-message">
			<?php
				esc_html_e( "You don't have any friends yet.", 'friends' );
			?>
			</span>
			<?php
		} else {
			?>
			<ul class="friend-list">
			<?php foreach ( $friends->get_results() as $friend_user ) : ?>
				<li><a href="<?php echo esc_url( site_url( '/friends/' . $friend_user->user_login . '/' ) ); ?>"><?php echo esc_html( $friend_user->display_name ); ?></a>
					<small><a href="<?php echo esc_url( $friend_user->user_url ); ?>" class="auth-link" data-token="<?php echo esc_attr( get_user_option( 'friends_out_token', $friend_user->ID ) ); ?>">visit</a></small></li>
			<?php endforeach; ?>
			</ul>
			<?php
		}

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

