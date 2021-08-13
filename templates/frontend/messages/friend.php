<?php
/**
 * This template contains the messages from a friend on /friends/.
 *
 * @package Friends
 */

?>
<div class="card mt-2 p-2">
	<strong>Messages</strong>
	<?php
	while ( $args['existing_messages']->have_posts() ) {
		$post = $args['existing_messages']->next_post();
		$class = '';
		if ( get_post_status( $post ) === 'unread' ) {
			$class .= ' unread';
		}
		?>
		<div class="message" id="message-<?php echo esc_attr( get_the_ID() ); ?>">
		<a href="" class="display-message<?php echo esc_attr( $class ); ?>" title="<?php echo esc_attr( get_post_modified_time( 'r' ) ); ?>">
			<?php
			// translators: %s is a time span.
			echo esc_html( sprintf( __( '%s ago' ), human_time_diff( get_post_modified_time( 'U', true ) ) ) );
			echo ': ';
			the_title();
			?>
		</a>
		<div style="display: none" class="conversation">
			<?php
			the_content();

			Friends::template_loader()->get_template_part(
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
