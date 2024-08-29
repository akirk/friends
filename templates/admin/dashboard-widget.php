<?php
/**
 * This template contains the Friends Dashboard Widget.
 *
 * @package Friends
 */

namespace Friends;

?><ul>
	<?php
	foreach ( $args['posts'] as $_post ) :
		$friend_user = User::get_post_author( $_post );
		$author_name = $args['friend_user']->display_name;
		$override_author_name = apply_filters( 'friends_override_author_name', '', $author_name, $_post->ID );
		$avatar = $friend_user->get_avatar_url();
		if ( ! $avatar ) {
			$avatar = get_avatar_url( $friend_user->ID );
		}
		$avatar = apply_filters( 'friends_author_avatar_url', $avatar, $friend_user, $_post->ID );

		?>
	<li>
		<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() ); ?>" class="author-avatar">
			<img src="<?php echo esc_url( $avatar ); ?>" width="16" height="16" />
		</a>
		<a href="<?php echo esc_url( $friend_user->get_local_friends_page_url() ); ?>">
		<strong><?php echo esc_html( $friend_user->display_name ); ?></strong>
		<?php if ( $override_author_name && trim( str_replace( $override_author_name, '', $author_name ) ) === $author_name ) : ?>
			– <?php echo esc_html( $override_author_name ); ?>
		<?php endif; ?>
		</a>:
		<a href="<?php echo esc_url( $friend_user->get_local_friends_page_url( $_post->ID ) ); ?>">
			<?php
			if ( $_post->post_title ) {
				echo esc_html( $_post->post_title );
			} else {
				echo esc_html( wp_trim_words( get_the_excerpt( $_post ), 18 ) );
			}
			?>
		</a>
		<a href="<?php echo esc_url( $_post->guid ); ?>" class="dashicons dashicons-external">
		</a>
	</li>
		<?php endforeach; ?>
</ul>
<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>"><?php esc_html_e( 'Go to your friends page for all posts', 'friends' ); ?></a>
