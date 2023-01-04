<?php
/**
 * Friends Shortcodes
 *
 * This contains the functions for shortcodes.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Friends Plugin shortcodes.
 *
 * @since 0.8
 *
 * @package Friends
 * @author Alex Kirk
 */
class Shortcodes {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends = null;

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_shortcodes();
	}

	/**
	 * Register the WordPress shortcodes
	 */
	private function register_shortcodes() {
		add_shortcode( 'only-friends', array( $this, 'only_friends_shortcode' ) );
		add_shortcode( 'not-friends', array( $this, 'not_friends_shortcode' ) );
		add_shortcode( 'friends-list', array( $this, 'friends_list_shortcode' ) );
		add_shortcode( 'friends-count', array( $this, 'friends_count_shortcode' ) );
	}

	/**
	 * Display the content of this shortcode just to friends.
	 *
	 * @param  array  $atts    Attributes provided by the user.
	 * @param  string $content Enclosed content provided by the user.
	 * @return string The content to be output.
	 */
	public function only_friends_shortcode( $atts, $content = null ) {
		if ( current_user_can( 'friend' ) ) {
			return do_shortcode( $content );
		}

		if ( friends::has_required_privileges() ) {
			return '<div class="only-friends"><span class="watermark">' . __( 'Only friends', 'friends' ) . '</span>' . do_shortcode( $content ) . '</div>';
		}

		return '';
	}

	/**
	 * Display the content of this shortcode to everyone except friends.
	 *
	 * @param  array  $atts    Attributes provided by the user.
	 * @param  string $content Enclosed content provided by the user.
	 * @return string The content to be output.
	 */
	public function not_friends_shortcode( $atts, $content = null ) {
		if ( current_user_can( 'friend' ) ) {
			return '';
		}

		if ( friends::has_required_privileges() ) {
			return '<div class="not-friends"><span class="watermark">' . __( 'Not friends', 'friends' ) . '</span>' . do_shortcode( $content ) . '</div>';
		}

		return do_shortcode( $content );
	}

	/**
	 * Display a list of your friends.
	 *
	 * @param  array $atts    Attributes provided by the user.
	 * @return string The content to be output.
	 */
	public function friends_list_shortcode( $atts ) {
		$a = shortcode_atts(
			array(
				'include-links' => false,
			),
			$atts
		);

		$friends = User_Query::all_friends();
		$ret     = '<ul class="friend-list">';

		foreach ( $friends->get_results() as $friend_user ) {
			$ret .= '<li>';

			if ( $a['include-links'] ) {
				$ret .= '<a href="' . esc_url( $friend_user->user_url ) . '">';
			}

			$ret .= esc_html( $friend_user->display_name );

			if ( $a['include-links'] ) {
				$ret .= '</a>';
			}

			$ret .= '</li>';

		}

		$ret .= '</ul>';

		return $ret;
	}

	/**
	 * Display the number of your friends.
	 *
	 * @param  array $atts    Attributes provided by the user.
	 * @return string The content to be output.
	 */
	public function friends_count_shortcode( $atts ) {
		exit;
		$friends = User_Query::all_friends();
		return $friends->get_total();
	}

}
