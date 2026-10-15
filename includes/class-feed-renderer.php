<?php
/**
 * Feed markup rendered with a temporary WordPress loop.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Adapts an independent query to existing theme templates without leaking globals.
 */
class Feed_Renderer {
	/**
	 * Render posts or the full feed content, restoring the caller's loop afterward.
	 *
	 * @param \WP_Query $query Query containing the visible posts.
	 * @param bool      $initial Whether to render empty states and navigation too.
	 * @param bool      $has_more Whether a subsequent page exists.
	 * @return string Rendered feed HTML.
	 */
	public function render( $query, $initial = false, $has_more = false ) {
		$loop_globals = array( 'wp_query', 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );
		$previous = array();
		foreach ( $loop_globals as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$previous[ $name ] = $GLOBALS[ $name ];
			}
		}
		$buffer_level = ob_get_level();
		ob_start();
		try {
			$GLOBALS['wp_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $initial ) {
				$friends = Friends::get_instance();
				Friends::template_loader()->get_template_part(
					'frontend/feed-content',
					null,
					array(
						'friends'     => $friends,
						'friend_user' => $friends->frontend->author,
						'post_format' => $friends->frontend->post_format,
						'async_feed'  => true,
						'has_more'    => $has_more,
					)
				);
			} else {
				$this->render_posts();
			}
			return ob_get_contents();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			foreach ( $loop_globals as $name ) {
				if ( array_key_exists( $name, $previous ) ) {
					$GLOBALS[ $name ] = $previous[ $name ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				} else {
					unset( $GLOBALS[ $name ] );
				}
			}
		}
	}

	/**
	 * Handles the post loop on the Friends page.
	 */
	public function render_posts() {
		$friends = Friends::get_instance();
		$known_author = $friends->frontend->author;
		$known_avatar = null;
		if ( $known_author instanceof User ) {
			$known_avatar = $known_author->get_avatar_url();
		}
		while ( have_posts() ) {
			global $post;
			the_post();
			$args = array(
				'friends' => $friends,
			);
			$args['item_friend_user'] = User::get_post_author( $post );

			if ( $known_author ) {
				$args['friend_user'] = $known_author;
				$args['avatar'] = $known_avatar;
			} else {
				$args['friend_user'] = $args['item_friend_user'];
				$args['avatar'] = $args['friend_user']->get_avatar_url();
			}

			$read_time = Frontend::calculate_read_time( get_the_content() );
			if ( $read_time >= 60 ) {
				$mins = ceil( $read_time / MINUTE_IN_SECONDS );
				/* translators: Time difference between two dates, in minutes (min=minute). %s: Number of minutes. */
				$args['read_time'] = sprintf( _n( '%s min', '%s mins', $mins ), $mins ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			} elseif ( $read_time > 20 ) {
				/* translators: Time difference between two dates, in minutes (min=minute). %s: Number of minutes. */
				$args['read_time'] = _x( '< 1 min', 'reading time', 'friends' );
			}

			Friends::template_loader()->get_template_part(
				'frontend/parts/content',
				get_post_format(),
				$args
			);
		}
	}
}
