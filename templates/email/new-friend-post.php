<?php
/**
 * This template contains the HTML for the New Friend Post notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

?>
<blockquote>
	<?php
	echo wp_kses_post( $args['post']->post_content );
	?>
</blockquote>

<p>
	<?php
	$post_title = $args['post']->post_title;
	if ( '' === trim( $post_title ) ) {
		$post_title = $args['post']->post_date;
	}
	printf(
		// translators: %1$s is a username, %2$s is a post title as a link.
		__( 'This post was published by your friend %1$s: %2$s', 'friends' ),
		'<em>' . esc_html( $args['author']->display_name ) . '</em>',
		'<strong><a href="' . get_permalink( $args['post'] ) . '">' . esc_html( $post_title ) . '</a></strong>'
	);
	?>
	<a href="<?php echo esc_url( $args['author']->get_local_friends_page_url( $args['post']->ID ) ); ?>">
		<?php
			esc_html_e( 'View the post on your friends page', 'friends' );
		?>
	</a>
</p>

<p>
	<?php
	printf(
		// translators: %1$s is a URL, %2$s is a URL, %3$s is a username.
		__( 'Manage your <a href=%1$s>subscription settings</a> or <a href=%2$s>stop notifications for %3$s\'s posts</a>', 'friends' ),
		'"' . esc_url( self_admin_url( 'admin.php?page=friends-settings' ) ) . '"',
		'"' . esc_url( self_admin_url( 'admin.php?page=edit-friend&user=' . $args['author']->ID ) ) . '"',
		'<em>' . $args['author']->display_name . '</em>'
	);
	?>
</p>

<p>
	<?php
		// translators: %s is a site name.
		echo wp_kses( sprintf( __( 'This notification was sent by the Friends plugin on %s.', 'friends' ), '<a href="' . esc_attr( site_url() ) . '">' . ( is_multisite() ? get_site_option( 'site_name' ) : get_option( 'blogname' ) ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
	?>
</p>
