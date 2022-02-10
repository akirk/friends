<?php
/**
 * This template contains the messages from a friend on /friends/.
 *
 * @package Friends
 */

?>
<div class="card mt-2 p-2">
	<strong><?php esc_html_e( 'Messages', 'friends' ); ?></strong>
	<?php
	while ( $args['existing_messages']->have_posts() ) {
		$post = $args['existing_messages']->next_post();
		setup_postdata( $post );
		$class = '';
		if ( get_post_status( $post ) === 'friends_unread' ) {
			$class .= ' unread';
		}
		?>
		<div class="friend-message" id="message-<?php echo esc_attr( get_the_ID() ); ?>" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-mark-read' ) ); ?>">
		<a href="" class="display-message<?php echo esc_attr( $class ); ?>" title="<?php echo esc_attr( get_post_modified_time( 'r' ) ); ?>">
			<?php
			// translators: %s is a time span.
			echo esc_html( sprintf( __( '%s ago' ), human_time_diff( get_post_modified_time( 'U', true ) ) ) );
			echo ': ';
			the_title();
			?>
		</a>
		<div style="display: none" class="conversation">
			<div class="messages">
			<?php

			$content = get_the_content();
			preg_match_all( '/<span class="date">([^<]+)</', $content, $matches );
			if ( $matches ) {
				$replace = array();
				foreach ( $matches[1] as $gmdate ) {

					$replace[ $gmdate ] = esc_html(
						sprintf(
							// translators: %s is a time span.
							__( '%s ago' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
							human_time_diff( strtotime( $gmdate ) )
						)
					);
				}
				$content = str_replace( array_keys( $replace ), array_values( $replace ), $content );
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This was handled upon post insert.
			echo apply_filters( 'the_content', $content );
			?>
			</div>
			<?php
			Friends\Friends::template_loader()->get_template_part(
				'frontend/messages/message-form',
				null,
				array_merge(
					$args,
					array(
						'subject' => get_the_title(),
					)
				)
			);
			?>
		</div>
		</div>
		<?php
	}
	?>
</div>
