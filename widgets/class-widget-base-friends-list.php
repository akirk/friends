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
abstract class Widget_Base_Friends_List extends \WP_Widget {
	/**
	 * PHP5 constructor.
	 *
	 * @since 2.8.0
	 *
	 * @param string $id_base         Optional. Base ID for the widget, lowercase and unique. If left empty,
	 *                                a portion of the widget's PHP class name will be used. Has to be unique.
	 * @param string $name            Name for the widget displayed on the configuration page.
	 * @param array  $widget_options  Optional. Widget options. See wp_register_sidebar_widget() for
	 *                                information on accepted arguments. Default empty array.
	 * @param array  $control_options Optional. Widget control options. See wp_register_widget_control() for
	 *                                information on accepted arguments. Default empty array.
	 */
	public function __construct( $id_base, $name, $widget_options = array(), $control_options = array() ) {
		parent::__construct( $id_base, $name, $widget_options, $control_options );
		$this->friends = Friends::get_instance();
	}

	/**
	 * Render a friend list.
	 *
	 * @param array          $args             The widget arguments.
	 * @param string         $title            The list title.
	 * @param \WP_User_Query $friends The friends to list.
	 */
	public function list_friends( $args, $title, \WP_User_Query $friends ) {
		?>
		<details class="accordion" open>
			<summary class="accordion-header">
		<?php
		echo $args['before_title'];
		echo wp_kses(
			$title,
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
		<ul class="friend-list menu menu-nav">
		<?php
		$this->get_list_items( $friends->get_results() );
		?>
		</ul>
		</details>
		<?php
	}

	/**
	 * Gets a section of the list.
	 *
	 * @param      array $users  The users.
	 */
	public function get_list_items( $users ) {
		foreach ( $users as $friend_user ) {
			$friend_user = new User( $friend_user );
			if ( Friends::has_required_privileges() ) {
				if ( $this->friends->frontend->post_format ) {
					$url = $friend_user->get_local_friends_page_post_format_url( $this->friends->frontend->post_format );
				} else {
					$url = $friend_user->get_local_friends_page_url();
				}
			} else {
				$url = $friend_user->user_url;
			}
			?>
			<li class="menu-item"><a href="<?php echo esc_url( $url ); ?>" style="display: inline-block"><?php echo esc_html( $friend_user->display_name ); ?></a>
				<?php
				if ( $friend_user->user_url ) {
					?>
					<small class="label label-secondary">
					<?php
					$this->friends->frontend->link(
						$friend_user->user_url,
						'',
						array(
							'class' => 'dashicons dashicons-external',
							'style' => 'display: inline',
						),
						$friend_user
					);
					?>
					</small>
					<?php
				}
				?>
				</li>
			<?php
		}
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
		register_widget( get_called_class() );
	}
}

