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
 * This is the class for the Friend Request Widget.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Widget_Friend_Request extends WP_Widget {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-friend-request', __( 'Friend request', 'friends' ), array(
				'description' => __( 'Send a friend request.', 'friends' ),
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
		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		?>
		<form action="<?php echo esc_url( self_admin_url( 'admin.php?page=send-friend-request' ) ); ?>" method="post">
		<?php wp_nonce_field( 'send-friend-request' ); ?>
		<input type="text" name="site_url" size="15" placeholder="<?php echo esc_attr_e( "Friend's URL", 'friends' ); ?>"/>
		<button><?php echo esc_attr_e( 'Send Request', 'friends' ); ?></button>
		</form>
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
	 * Rgister this widget.
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}
}
