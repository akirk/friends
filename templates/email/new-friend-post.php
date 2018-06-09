<?php
/**
 * This template contains the HTML for the New Friend Post notification e-mail.
 *
 * @package Friends
 */

?>
<?php include __DIR__ . '/header.php'; ?>
<p>
	<?php
	// translators: %s is a user display name.
		printf( __( 'Howdy, %s!' ), $user->display_name );
	?>
</p>

<p>
	<?php
	printf(
		// translators: %1$s is a username, %2$s is a post title.
		__( 'Your friend %1$s has published a new post: %2$s', 'friends' ),
		'<em>' . $author->display_name . '</em>',
		'<strong><a href="' . get_permalink( $post ) . '">' . $post->post_title . '</a></strong>'
	);
	?>
	<a href="<?php echo site_url( '/friends/' . $post->ID . '/' ); ?>">
		<?php
			esc_html_e( 'View this post on your site', 'friends' );
		?>
	</a>
</p>

<blockquote>
	<?php
	echo $post->post_content;
	?>
</blockquote>

<p>
	<?php
		esc_html_e( 'Best, the Friends plugin', 'friends' );
	?>
</p>
<?php include __DIR__ . '/footer.php'; ?>
