<?php
/**
 * Earlier messages above the reply being viewed.
 *
 * @package Friends
 */

?>
<section class="friends-conversation-context column col-12" aria-label="<?php esc_attr_e( 'Earlier in this conversation', 'friends' ); ?>">
	<h5><?php esc_html_e( 'Earlier in this conversation', 'friends' ); ?></h5>
	<?php foreach ( $args['context'] as $message ) : ?>
		<?php
		$is_comment = $message instanceof WP_Comment;
		if ( $is_comment ) {
			$author = $message->comment_author;
			$content = $message->comment_content;
			$url = get_comment_link( $message );
			$date = $message->comment_date;
			$warning = get_comment_meta( $message->comment_ID, 'content_warning', true );
		} else {
			$author = get_post_meta( $message->ID, 'author', true );
			if ( ! $author ) {
				$author = get_the_author_meta( 'display_name', $message->post_author );
			}
			$content = $message->post_content;
			$url = $message->guid;
			$date = $message->post_date;
			$warning = get_post_meta( $message->ID, 'friends_content_warning', true );
		}
		?>
		<article class="card friends-conversation-message mb-2">
			<header class="card-header">
				<strong><?php echo esc_html( $author ); ?></strong>
				<a href="<?php echo esc_url( $url ); ?>"><time><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) ); ?></time></a>
			</header>
			<div class="card-body">
				<?php if ( $warning ) : ?>
					<details><summary><?php echo esc_html( $warning ); ?></summary>
				<?php endif; ?>
				<?php echo wp_kses_post( wpautop( make_clickable( $content ) ) ); ?>
				<?php if ( $warning ) : ?>
					</details>
				<?php endif; ?>
			</div>
		</article>
	<?php endforeach; ?>
</section>
