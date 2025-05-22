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
		$author_name = $friend_user->display_name;
		$override_author_name = apply_filters( 'friends_override_author_name', '', $author_name, $_post->ID );
		$avatar = $friend_user->get_avatar_url();
		if ( ! $avatar ) {
			$avatar = get_avatar_url( $friend_user->ID );
		}
		$avatar = apply_filters( 'friends_author_avatar_url', $avatar, $friend_user, $_post->ID );

		?>
	<li id="friends-dashboard-post-<?php echo esc_attr( $_post->ID ); ?>" class="friends-post">
		<?php echo esc_html( get_the_date( get_option( 'time_format', 'H:i' ), $_post ) ); ?>:

		<?php if ( ! isset( $args['friend_user'] ) ) : ?>
			<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() ); ?>" class="author-avatar">
				<img src="<?php echo esc_url( $avatar ); /* phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage */ ?>" width="16" height="16" />
			</a>
			<a href="<?php echo esc_url( $friend_user->get_local_friends_page_url() ); ?>">
			<strong><?php echo esc_html( $friend_user->display_name ); ?></strong><?php if ( $override_author_name && trim( str_replace( $override_author_name, '', $author_name ) ) === $author_name ) : ?>
				– <?php echo esc_html( $override_author_name ); ?>
			<?php endif; ?></a>:
		<?php endif; ?>
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

<?php if ( empty( $args['posts'] ) ) : ?>
	<?php esc_html_e( 'No posts yet.', 'friends' ); ?>
<?php elseif ( isset( $args['friend_user'] ) && isset( $args['format'] ) ) : ?>
	<a href="<?php echo esc_url( $args['friend_user']->get_local_friends_page_post_format_url( $args['format'] ) ); ?>"><?php esc_html_e( 'Go to your friends page for all posts', 'friends' ); ?></a>
<?php elseif ( isset( $args['friend_user'] ) ) : ?>
	<a href="<?php echo esc_url( $args['friend_user']->get_local_friends_page_url() ); ?>"><?php esc_html_e( 'Go to your friends page for all posts', 'friends' ); ?></a>
<?php elseif ( isset( $args['format'] ) ) : ?>
	<a href="<?php echo esc_url( home_url( '/friends/type/' . $args['format'] ) ); ?>"><?php esc_html_e( 'Go to your friends page for all posts', 'friends' ); ?></a>
<?php else : ?>
	<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>"><?php esc_html_e( 'Go to your friends page for all posts', 'friends' ); ?></a>
<?php endif; ?>
