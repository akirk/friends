<?php
/**
 * This template contains a comment for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

?>
<div class="comment">
	<div class="comment-meta mb-1" style="font-size: 90%">
	<strong><?php echo esc_html( $args['author'] ); ?></strong>
	<a href="<?php echo esc_attr( $args['permalink'] ); ?>" title="<?php echo esc_attr( $args['date'] ); ?>'">
	<?php
	echo esc_html(
		/* translators: %s is a time span */
		sprintf( __( '%s ago' ), human_time_diff( strtotime( $args['date'] ) ) ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
	);
	?>
	</a>
	</div>
	<blockquote class="comment-content">
		<?php echo wp_kses_post( $args['post_content'] ); ?>
	</blockquote>
</div>
