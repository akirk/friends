<?php
/**
 * This template contains the HTML for the New Friend Post notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

$override_author_name = apply_filters( 'friends_override_author_name', '', $args['author']->display_name, $args['post']->ID );

?>
<div class="post-meta">
<a href="<?php echo esc_attr( $args['author']->get_local_friends_page_url() ); ?>" class="author">
	<strong><?php echo esc_html( $args['author']->display_name ); ?></strong>
	<?php if ( $override_author_name && trim( str_replace( $override_author_name, '', $args['author']->display_name ) ) === $args['author']->display_name ) : ?>
		– <?php echo esc_html( $override_author_name ); ?>
	<?php endif; ?>
</a>
<h2><a href="<?php the_permalink( $args['post'] ); ?>">
	<?php
	$post_title = $args['post']->post_title;
	if ( '' === trim( $post_title ) ) {
		$post_title = $args['post']->post_date;
	}
	echo esc_html( $post_title );
	?>
</a></h2>
</div>

<div class="post-content">
<?php
	echo wp_kses_post( $args['post']->post_content );
?>
</div>

<div class="post-footer">
	<a href="<?php echo esc_url( $args['author']->get_local_friends_page_url( $args['post']->ID ) ); ?>#respond" class="btn">
		<?php
			esc_html_e( 'Reply', 'friends' );
		?>
	</a>
	<a href="<?php echo esc_url( $args['author']->get_local_friends_page_url( $args['post']->ID ) ); ?>" class="btn noborder">
		<?php
			esc_html_e( 'View on your friends page', 'friends' );
		?>
	</a>
	<p>
	<?php
	printf(
		// translators: %s is a URL.
		__( 'Published at %s', 'friends' ),
		'<strong><a href="' . esc_url( get_permalink( $args['post'] ) ) . '">' . esc_html( get_permalink( $args['post'] ) ) . '</a></strong>'
	);
	?>
	</p>
</div>

<div class="subscription-settings">
	<hr>
	<?php
	printf(
		// translators: %1$s is a URL, %2$s is a URL, %3$s is a username, %4$s is a URL.
		__( 'Manage your <a href=%1$s>global notification settings</a>, <a href=%2$s>change notifications for %3$s</a>, or <a href=%4$s>muffle posts like these</a>.', 'friends' ),
		'"' . esc_url( self_admin_url( 'admin.php?page=friends-settings' ) ) . '"',
		'"' . esc_url( self_admin_url( 'admin.php?page=edit-friend&user=' . $args['author']->ID ) ) . '"',
		'<em>' . esc_html( $args['author']->display_name ) . '</em>',
		'"' . esc_url( self_admin_url( 'admin.php?page=edit-friend-rules&user=' . $args['author']->ID . '&post=' . $args['post']->ID ) ) . '"'
	);
	?>
</div>

