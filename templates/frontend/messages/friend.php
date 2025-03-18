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
<div class="card mt-2 p-2">
	<strong><?php esc_html_e( 'Messages', 'friends' ); ?></strong>
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

		$classes = array( 'display-message' => true );
		$subject = false;
		$last_message_time = false;
		foreach ( $messages as $message ) {
			if ( get_post_status( $message ) === 'friends_unread' ) {
				$classes['unread'] = true;
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
		?>
		<div class="friend-message" id="message-<?php echo esc_attr( $_post->ID ); ?>" data-id="<?php echo esc_attr( $_post->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-mark-read' ) ); ?>">
		<a href="" class="<?php echo esc_attr( implode( ' ', array_keys( $classes ) ) ); ?>" title="<?php echo esc_attr( date_i18n( $time_format, $last_message_time ) ); ?>">
			<?php
			// translators: %s is a time span.
			echo esc_html( sprintf( __( '%s ago' ), human_time_diff( $last_message_time ) ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			echo ': ';
			echo esc_html( $subject );
			?>
		</a>
		<div style="display: none" class="conversation">
			<div class="messages">
			<?php
			foreach ( $messages as $message ) {
				?>
				<div class="message">
				<?php
				$post_time = get_post_modified_time( 'U', true, $message );
				$ago = human_time_diff( $post_time );
				$post_time = date_i18n( $time_format, $post_time );
				$author = Friends\User::get_post_author( $message );
				if ( get_current_user_id() === $author->ID ) {
					echo wp_kses_post(
						sprintf(
							// translators: %2$s is the time span.
							__( '<span class="author">You</span> wrote %s ago:', 'friends' ),
							'<span class="time" title="' . esc_attr( $post_time ) . '">' . esc_html( $ago ) . '</span>'
						)
					);
				} else {
					echo wp_kses_post(
						sprintf(
							// translators: %1$s is the author, %2$s is the time span.
							__( '%1$s wrote %2$s ago:', 'friends' ),
							'<span class="author">' . esc_html( $author->display_name ) . '</span>',
							'<span class="time" title="' . esc_attr( $post_time ) . '">' . esc_html( $ago ) . '</span>'
						)
					);
				}
				?>
				<blockquote>
				<?php
				$content = make_clickable( get_the_content( null, false, $message ) );
				echo wp_kses_post( apply_filters( 'the_content', $content ) );
				?>
				</blockquote>
				</div>
				<?php
			}

			$feed_args = array(
				'subject'  => $title,
				'reply_to' => $_post->ID,
			);
			$feed_url = get_post_meta( $_post->ID, 'friends_feed_url', true );
			if ( $feed_url ) {
				$feed_args['accounts'] = array( $feed_url => $args['accounts'][ $feed_url ] );
			}

			Friends\Friends::template_loader()->get_template_part(
				'frontend/messages/message-form',
				null,
				array_merge( $args, $feed_args )
			);
		?>
		</div>
		</div>
		</div>
		<?php
	}
	?>
</div>
