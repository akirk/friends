<?php
/**
 * Feed posts and empty states, shared by page and AJAX rendering.
 *
 * @package Friends
 */

$show_welcome = ! empty( $args['show_welcome'] );
if ( $show_welcome || ! have_posts() ) {
	?>
		<div class="card columns col-12 friends-empty-state">
			<div class="card-body">
		<?php
		if ( ! $show_welcome && $args['friends']->frontend->post_format ) {
			$post_formats = get_post_format_strings();

			if ( $args['friend_user'] ) {
				echo esc_html(
					sprintf(
					// translators: %s is the name of an author.
						__( '%1$s hasn\'t posted anything with the post format %2$s yet!', 'friends' ),
						$args['friend_user']->display_name,
						$post_formats[ $args['friends']->frontend->post_format ]
					)
				);
				?>
					<a href="<?php echo esc_url( $args['friend_user']->get_local_friends_page_url() ); ?>"><?php esc_html_e( 'Remove post format filter', 'friends' ); ?></a>
					<?php
			} else {
				echo esc_html(
					sprintf(
					// translators: %s is a post format title.
						__( "Your friends haven't posted anything with the post format %s yet!", 'friends' ),
						$post_formats[ $args['friends']->frontend->post_format ]
					)
				);
				?>
					<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>"><?php esc_html_e( 'Remove post format filter', 'friends' ); ?></a>
					<?php
			}
		} else {
			$any_friends = Friends\User_Query::all_associated_users();
			if ( ! $show_welcome && $any_friends->get_total() > 0 ) {
				Friends\Friends::template_loader()->get_template_part( 'frontend/no-posts', $args['post_format'], $args );
			} else {
				Friends\Friends::template_loader()->get_template_part( 'frontend/no-friends', $args['post_format'], $args );
			}
		}
		?>
			</div>
		</div>
		<?php
} else {
	Friends\Frontend::have_posts();
	if ( empty( $args['async_feed'] ) ) {
		the_posts_navigation();
	}
}
if ( ! empty( $args['async_feed'] ) ) {
	Friends\Friends::template_loader()->get_template_part( 'frontend/feed-navigation', null, $args );
}
