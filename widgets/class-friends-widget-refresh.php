<?php
/**
 * Friend Posts Refresh
 *
 * A widget that allows you to refresh your friend posts.
 *
 * @package Friends
 * @since 0.3
 */

/**
 * This is the class for the Friend Posts Refresh Widget.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Widget_Refresh extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'friends-widget-refresh', __( 'Friend Posts Refresh', 'friends' ), array(
				'description' => __( "Shows a refresh link to refetch your friends' posts.", 'friends' ),
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

		echo '<a href="/friends/?refresh">' . __( 'Refresh', 'friends' ) . '</a>';

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

