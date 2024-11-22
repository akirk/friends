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
class Widget_Friends_List extends Widget_Base_Friends_List {
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
	}

	/**
	 * Render the widget.
	 *
	 * @param array $args Sidebar arguments.
	 * @param array $instance Widget instance settings.
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( $instance, $this->defaults() );

		$all_friends     = User_Query::all_friends();
		$friend_requests = User_Query::all_friend_requests();
		$subscriptions   = User_Query::all_subscriptions();

		// translators: %s is the number of your friends.
		$friends_title = '<span class="dashicons dashicons-plus-alt"></span> ' . sprintf( _n( 'Friend %s', 'Friends %s', $all_friends->get_total(), 'friends' ), '<span class="friend-count">' . $all_friends->get_total() . '</span>' );

		if ( $all_friends->get_total() > 0 || ( ! $friend_requests->get_total() && ! $subscriptions->get_total() ) ) {
			echo $args['before_widget'];
			$this->list_friends(
				array_merge(
					array(
						'widget_id' => $args['widget_id'] . '-all',
					),
					$args
				),
				$friends_title,
				$all_friends
			);

			if ( ! $all_friends->get_total() ) {
				?>
				<ul class="menu menu-nav accordion-body">
					<li class="menu-item"><a href="<?php echo esc_url( self_admin_url( 'admin.php?page=add-friend' ) ); ?>" class="normal"><?php esc_html_e( 'Add your first friend or subscription now!', 'friends' ); ?></a></li>
				</ul>
				<?php
			}
			echo $args['after_widget'];
		}
		if ( $friend_requests->get_total() > 0 ) {
			echo $args['before_widget'];
			$this->list_friends(
				array_merge(
					array(
						'widget_id' => $args['widget_id'] . '-requests',
					),
					$args
				),
				// translators: %1$s is the string "%s Friend", %2$s is a URL, %3$s is the number of open friend requests.
				sprintf( _n( '%1$s <a href=%2$s>(%3$s request)</a>', '%1$s <a href=%2$s>(%3$s requests)</a>', $friend_requests->get_total(), 'friends' ), $friends_title, '"' . esc_attr( self_admin_url( 'users.php?role=friend_request' ) ) . '" class="open-requests"', $friend_requests->get_total() ),
				$friend_requests
			);
			echo $args['after_widget'];
		}

		if ( 0 !== $subscriptions->get_total() ) {
			echo $args['before_widget'];
			$this->list_friends(
				array_merge(
					array(
						'widget_id' => $args['widget_id'] . '-subscriptions',
					),
					$args
				),
				'<span class="dashicons dashicons-admin-users"></span> ' . sprintf(
					// translators: %s is the number of subscriptions.
					_n( 'Subscription %s', 'Subscriptions %s', $subscriptions->get_total(), 'friends' ),
					'<span class="subscription-count">' . $subscriptions->get_total() . '</span>'
				),
				$subscriptions
			);
			echo $args['after_widget'];
		}

		do_action( 'friends_widget_friend_list_after', $this, $args );
	}
}

