<?php
/**
 * Friend Request Widget
 *
 * A widget that allows you to send a friend request.
 *
 * @package Friends
 * @since 0.3
 */

namespace Friends;

/**
 * This is the class for the Friend Request Widget.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Widget_Friend_Request extends \WP_Widget {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-friend-request',
			__( 'Friend request', 'friends' ),
			array(
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
		$instance = wp_parse_args( $instance, $this->defaults() );

		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		?>
		<form action="<?php echo esc_url( self_admin_url( 'admin.php?page=add-friend' ) ); ?>" method="post" class="form-horizontal">
		<?php wp_nonce_field( 'add-friend' ); ?>
		<div class="form-group">
			<input type="text" name="friend_url" aria-label="<?php echo esc_attr_e( "Enter the Friend's URL", 'friends' ); ?>"  placeholder="<?php echo esc_attr_e( "Friend's URL", 'friends' ); ?>" class="form-input input-sm" />
		</div>
		<div class="form-group">
			<button class="btn btn-primary btn-sm"><?php echo esc_attr_e( 'Add Friend', 'friends' ); ?></button>
		</div>
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
