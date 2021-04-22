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
			'friends-widget-friend-list',
			__( 'Friend List', 'friends' ),
			array(
				'description' => __( 'Shows a list of your friends.', 'friends' ),
			)
		);
		$this->friends = Friends::get_instance();
	}

	/**
	 * Render the widget.
	 *
	 * @param array $args Sidebar arguments.
	 * @param array $instance Widget instance settings.
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( $instance, $this->defaults() );

		echo $args['before_widget'];

		$all_friends     = Friend_User_Query::all_friends();
		$friend_requests = Friend_User_Query::all_friend_requests();
		$subscriptions   = Friend_User_Query::all_subscriptions();

		$list_not_empty = $all_friends->get_total() + $subscriptions->get_total() > 0;

		// translators: %s is the number of your friends.
		$friends_title = sprintf( _n( '%s Friend', '%s Friends', $all_friends->get_total(), 'friends' ), '<span class="friend-count">' . $all_friends->get_total() . '</span>' );

		if ( $list_not_empty ) {
			?>
			<details class="accordion" open>
				<summary class="accordion-header">
			<?php
		}
		echo $args['before_title'];
		if ( $friend_requests->get_total() > 0 ) {
			echo wp_kses(
				// translators: %1$s is the string "%s Friend", %2$s is a URL, %3$s is the number of open friend requests.
				sprintf( _n( '%1$s <a href=%2$s>(%3$s request)</a>', '%1$s <a href=%2$s>(%3$s requests)</a>', $friend_requests->get_total(), 'friends' ), $friends_title, '"' . esc_attr( self_admin_url( 'users.php?role=friend_request' ) ) . '" class="open-requests"', '<span class="friend-count">' . $friend_requests->get_total() . '</span>' ),
				array(
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
		echo $args['after_title'];

		if ( $list_not_empty ) {
			?>
			</summary>
			<ul class="friend-list menu menu-nav">
			<?php
			$this->get_list_items( $all_friends->get_results() );
			?>
			</ul>
			</details>
			<?php
		}

		if ( 0 !== $subscriptions->get_total() ) {
			?>
			<details class="accordion" open>
				<summary class="accordion-header">
					<?php
					echo $args['before_title'];
					echo wp_kses(
						sprintf(
						// translators: %s is the number of subscriptions.
							_n( '%s Subscription', '%s Subscriptions', $subscriptions->get_total(), 'friends' ),
							'<span class="subscription-count">' . $subscriptions->get_total() . '</span>'
						),
						array( 'span' => array( 'class' => array() ) )
					);

					echo $args['after_title'];
					?>
				</summary>
				<ul class="subscriptions-list menu menu-nav accordion-body">
					<?php
					$this->get_list_items( $subscriptions->get_results() );
					?>
				</ul>
			</details>
			<?php
		}

		do_action( 'friends_widget_friend_list_after', $this, $args );

		echo $args['after_widget'];
	}

	/**
	 * Gets a section of the list.
	 *
	 * @param      array $users  The users.
	 */
	public function get_list_items( $users ) {
		foreach ( $users as $friend_user ) {
			$friend_user = new Friend_User( $friend_user );
			if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
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
				</small></li>
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
		register_widget( __CLASS__ );
	}
}

