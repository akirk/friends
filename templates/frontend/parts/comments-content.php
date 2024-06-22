<?php
/**
 * This template contains the comments content in the footer for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

?><footer class="comments-content card-footer <?php echo is_single() ? 'open' : 'closed'; ?>">
<?php
if ( is_single() ) {
	$_post_id = get_the_ID();
	$_comments = apply_filters(
		'friends_get_comments',
		get_comments(
			array(
				'post_id' => $_post_id,
				'status'  => 'approve',
				'order'   => 'ASC',
				'orderby' => 'comment_date_gmt',
			)
		),
		$_post_id
	);

	if ( ! empty( $_comments ) ) {
		$template_loader = Friends\Friends::template_loader();
		?>
	<h5><?php esc_html_e( 'Comments' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></h5>
		<ol class="comment-list">
			<?php
				remove_all_filters( 'comments_template' );
				wp_list_comments(
					array(
						'style'       => 'ol',
						'short_ping'  => true,
						'avatar_size' => 24,
					),
					$_comments
				);
			?>
		</ol><!-- .comment-list -->
		<?php
	}
	do_action( 'friends_comments_form', $_post_id );
}
?>
</footer>
