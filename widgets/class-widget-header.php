<?php
/**
 * Friends Header Widget
 *
 * A widget that allows you to define the header for your own friends page.
 *
 * @package Friends
 * @since 0.8
 */

namespace Friends;

/**
 * This is the class for the Friends Header Widget.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Widget_Header extends \WP_Widget {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-header',
			__( 'Friends Header', 'friends' ),
			array(
				'description' => __( 'The header for your friends page.', 'friends' ),
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
		$friends = Friends::get_instance();

		$title = apply_filters( 'friends_header_widget_title', $instance['title'] );
		$title = apply_filters( 'wptexturize', $title );
		$title = apply_filters( 'convert_chars', $title );

		echo $args['before_widget'];
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . '<span class="dashicons dashicons-feedback"></span> ' . $title . $args['after_title'];
		}

		if ( $friends->frontend->author ) {
			?>
				<p>
				<?php
				echo wp_kses(
					sprintf(
					// translators: %1$s is a site name, %2$s is a URL.
						__( 'Visit %1$s. Back to <a href=%2$s>your friends page</a>.', 'friends' ),
						// translators: 1: a friend's display name, 2: a URL.
						'<a href="' . esc_url( $friends->frontend->author->user_url ) . '" class="auth-link" data-token="' . esc_attr( get_user_option( 'friends_out_token', $friends->frontend->author->ID ) ) . '">' . esc_html( sprintf( __( '%1$s\'s external site at %2$s', 'friends' ), $friends->frontend->author->display_name, preg_replace( '#https?://#', '', trim( $friends->frontend->author->user_url, '/' ) ) ) ) . '</a>',
						'"' . esc_attr( home_url( '/friends/' ) ) . '"'
					),
					array(
						'a' => array(
							'href'       => array(),
							'class'      => array(),
							'data-token' => array(),
						),
					)
				);
				?>
				</p>
			<?php
		}

		echo $args['after_widget'];
	}

	/**
	 * Widget configuration form.
	 *
	 * @param array $instance The current settings.
	 */
	public function form( $instance ) {
		$instance = array_merge( $this->defaults(), $instance );
		?>
		<p>
		<label><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Title' ); ?><br/>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</label>
		</p>
		<?php
	}

	/**
	 * Update widget configuration.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array Sanitized instance settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['show_post_formats'] = isset( $new_instance['show_post_formats'] ) && $new_instance['show_post_formats'];
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
			'title'             => __( 'Friends', 'friends' ),
			'show_post_formats' => true,
		);
	}

	/**
	 * Register this widget.
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}
}
