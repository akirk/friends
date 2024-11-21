<?php
/**
 * Friends Header Widget
 *
 * A widget that allows you to show post formats lins on the friends sidebar.
 *
 * @package Friends
 * @since 1.0
 */

namespace Friends;

/**
 * This is the class for the Friends Post Formats Widget.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Widget_Post_Formats extends \WP_Widget {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'friends-widget-post-formats',
			__( 'Post Formats', 'friends' ),
			array(
				'description' => __( 'Show post formats in the sidebar.', 'friends' ),
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

		echo wp_kses( $args['before_widget'], 'post' );
		$open = Frontend::get_widget_open_state( $args['widget_id'] );
		?>
		<details class="accordion" <?php echo esc_attr( $open ); ?> data-id="<?php echo esc_attr( $args['widget_id'] ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends_widget_state' ) ); ?>">
			<summary class="accordion-header">
				<?php
				if ( ! empty( $instance['title'] ) ) {
					echo wp_kses( $args['before_title'] . '<span class="dashicons dashicons-filter"></span> ' . $instance['title'] . $args['after_title'], 'post' );
				}
				?>
			</summary>
			<ul class="friends-post-formats menu menu-nav accordion-body">
				<li class="menu-item"><a href="<?php echo esc_attr( home_url( '/friends/' ) ); ?>"><?php _ex( 'All', 'all posts', 'friends' ); ?></a></li>
				<?php
				foreach ( get_post_format_strings() as $slug => $title ) :

					if ( ! isset( $instance[ 'show_post_format_' . $slug ] ) || ! $instance[ 'show_post_format_' . $slug ] ) {
						continue;
					}
					if ( ! isset( $instance[ 'post_format_title_' . $slug ] ) ) {
						$instance[ 'post_format_title_' . $slug ] = $title;
					}
					?>
					<li class="menu-item"><a href="<?php echo esc_attr( home_url( '/friends/type/' . $slug . '/' ) ); ?>"> <?php echo esc_attr( $instance[ 'post_format_title_' . $slug ] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</details>
		<?php

		echo wp_kses( $args['after_widget'], 'post' );
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
		<input class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</label>
		</p>
		<p><?php esc_html_e( 'Show Post Format Links:', 'friends' ); ?>
		<ul>
			<?php
			foreach ( get_post_format_strings() as $slug => $title ) :
				if ( ! isset( $instance[ 'post_format_title_' . $slug ] ) ) {
					$instance[ 'post_format_title_' . $slug ] = $title;
				}
				?>
			<li><label><input id="<?php echo esc_attr( $this->get_field_id( 'show_post_format_' . $slug ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_post_format_' . $slug ) ); ?>" type="checkbox" value="1"<?php checked( isset( $instance[ 'show_post_format_' . $slug ] ) && $instance[ 'show_post_format_' . $slug ] ); ?> /> <?php echo esc_html( $title ); ?></label><br/>
				<input id="<?php echo esc_attr( $this->get_field_id( 'post_format_title_' . $slug ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'post_format_title_' . $slug ) ); ?>" type="text" value="<?php echo esc_attr( $instance[ 'post_format_title_' . $slug ] ); ?>" placeholder="<?php echo esc_attr( $title ); ?>" /></li>
		<?php endforeach; ?>
		</ul></p>
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
		$instance['title'] = apply_filters( 'wptexturize', $instance['title'] );
		$instance['title'] = apply_filters( 'convert_chars', $instance['title'] );

		foreach ( get_post_format_strings() as $slug => $title ) {
			$instance[ 'post_format_title_' . $slug ] = ( ! empty( $new_instance[ 'post_format_title_' . $slug ] ) ) ? wp_strip_all_tags( $new_instance[ 'post_format_title_' . $slug ] ) : $title;
			if ( $new_instance[ 'show_post_format_' . $slug ] ) {
				$instance[ 'show_post_format_' . $slug ] = true;
			} else {
				$instance[ 'show_post_format_' . $slug ] = false;
			}
		}
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
		$instance = array(
			'title' => __( 'Filter', 'friends' ),
		);

		foreach ( get_post_format_strings() as $slug => $title ) {
			$t = 'post_format_title_' . $slug;
			$instance[ $t ] = ( ! empty( $new_instance[ $t ] ) ) ? wp_strip_all_tags( $new_instance[ $t ] ) : $title;
			$instance[ 'show_post_format_' . $slug ] = in_array( $slug, array( 'standard', 'status', 'image', 'video' ) );
		}

		return $instance;
	}

	/**
	 * Register this widget.
	 */
	public static function register() {
		register_widget( __CLASS__ );
	}
}
