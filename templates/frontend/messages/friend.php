<?php
/**
 * This template contains the messages from a friend on /friends/.
 *
 * @package Friends
 */

$time_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
if ( false === strpos( $time_format, ':s' ) ) {
	$time_format = str_replace( 'H:i', 'H:i:s', $time_format );
	$time_format = str_replace( 'g:i', 'g:i:s', $time_format );
}

?>
<div class="card mt-2 p-2 friends-profile-messages">
	<div class="friends-profile-messages-header">
		<strong><?php esc_html_e( 'Direct messages', 'friends' ); ?></strong>
		<a href="<?php echo esc_url( home_url( '/friends/messages/' ) ); ?>"><?php esc_html_e( 'View all', 'friends' ); ?></a>
	</div>
	<?php
	foreach ( $args['existing_messages']->get_posts() as $_post ) {
		$messages = get_posts(
			array(
				'post_parent' => $_post->ID,
				'post_type'   => 'friend_message',
				'post_status' => array( 'friends_read', 'friends_unread' ),
				'order'       => 'ASC',
				'numberposts' => -1,
			)
		);
		array_unshift( $messages, $_post );

		$classes           = array( 'friends-dm-conversation' => true );
		$subject           = false;
		$last_message_time = false;
		$unread_count      = 0;
		foreach ( $messages as $message ) {
			if ( get_post_status( $message ) === 'friends_unread' ) {
				$classes['is-unread'] = true;
				++$unread_count;
			}
			$post_time = get_post_modified_time( 'U', true, $message );
			if ( ! $last_message_time || $post_time > $last_message_time ) {
				$last_message_time = $post_time;

				$subject = get_the_title( $message );
				if ( ! $subject ) {
					$subject = get_the_excerpt( $message );
				}
			}
		}
		$conversation_url = add_query_arg( 'conversation', $_post->ID, home_url( '/friends/messages/' ) );
		?>
		<a class="<?php echo esc_attr( implode( ' ', array_keys( $classes ) ) ); ?>" href="<?php echo esc_url( $conversation_url ); ?>">
			<span class="friends-dm-conversation-main">
				<span class="friends-dm-conversation-name"><?php echo esc_html( $args['friend_user']->display_name ); ?></span>
				<span class="friends-dm-conversation-preview"><?php echo esc_html( $subject ); ?></span>
			</span>
			<span class="friends-dm-conversation-meta">
				<time title="<?php echo esc_attr( date_i18n( $time_format, $last_message_time ) ); ?>">
					<?php echo esc_html( human_time_diff( $last_message_time ) ); ?>
				</time>
				<?php if ( $unread_count ) : ?>
					<span class="friends-dm-unread-count"><?php echo esc_html( number_format_i18n( $unread_count ) ); ?></span>
				<?php endif; ?>
			</span>
		</a>
		<?php
	}
	?>
</div>
