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

		echo $args['before_widget'];

		$all_friends     = User_Query::all_friends();
		$friend_requests = User_Query::all_friend_requests();
		$subscriptions   = User_Query::all_subscriptions();

		// translators: %s is the number of your friends.
		$friends_title = sprintf( _n( '%s Friend', '%s Friends', $all_friends->get_total(), 'friends' ), '<span class="friend-count">' . $all_friends->get_total() . '</span>' );

		if ( $all_friends->get_total() > 0 || ( ! $friend_requests->get_total() && ! $subscriptions->get_total() ) ) {
			$this->list_friends(
				$args,
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
		}
		if ( $friend_requests->get_total() > 0 ) {
			$this->list_friends(
				$args,
				// translators: %1$s is the string "%s Friend", %2$s is a URL, %3$s is the number of open friend requests.
				sprintf( _n( '%1$s <a href=%2$s>(%3$s request)</a>', '%1$s <a href=%2$s>(%3$s requests)</a>', $friend_requests->get_total(), 'friends' ), $friends_title, '"' . esc_attr( self_admin_url( 'users.php?role=friend_request' ) ) . '" class="open-requests"', '<span class="friend-count">' . $friend_requests->get_total() . '</span>' ),
				$friend_requests
			);
		}

		if ( 0 !== $subscriptions->get_total() ) {
			$this->list_friends(
				$args,
				sprintf(
					// translators: %s is the number of subscriptions.
					_n( '%s Subscription', '%s Subscriptions', $subscriptions->get_total(), 'friends' ),
					'<span class="subscription-count">' . $subscriptions->get_total() . '</span>'
				),
				$subscriptions
			);
		}

		do_action( 'friends_widget_friend_list_after', $this, $args );

		echo $args['after_widget'];
	}
}

