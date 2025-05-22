<?php
/**
 * This template contains the HTML for the New Friend Post notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

// Documented in templates/frontend/parts/header.php.
$override_author_name = apply_filters( 'friends_override_author_name', '', $args['author']->display_name, $args['post']->ID );

?>
	<?php
	echo wp_kses(
		// translators: %s is a keyword string specified by the user.
		sprintf( __( 'Keyword matched: %s', 'friends' ), '<strong>' . esc_html( $args['keyword'] ) . '</strong>' ),
		array( 'strong' => array() )
	);
	?>
<br/><br/>
<div class="post-meta">
	<a href="<?php echo esc_attr( $args['author']->get_local_friends_page_url() ); ?>" class="author">
		<strong><?php echo esc_html( $args['author']->display_name ); ?></strong>
		<?php if ( $override_author_name && trim( str_replace( $override_author_name, '', $args['author']->display_name ) ) === $args['author']->display_name ) : ?>
			– <?php echo esc_html( $override_author_name ); ?>
		<?php endif; ?>
	</a>
	<?php if ( '' !== trim( $args['post']->post_title ) ) : ?>
		<h2><a href="<?php the_permalink( $args['post'] ); ?>"><?php echo esc_html( $args['post']->post_title ); ?></a></h2>
	<?php endif; ?>
</div>

<div class="post-content">
<?php
	echo wp_kses_post( apply_filters( 'friends_rewrite_mail_html', $args['post']->post_content, $args ) );
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
	<p class="permalink">
	<?php
	echo wp_kses(
		sprintf(
		// translators: %s is a URL.
			__( 'Published at %s', 'friends' ),
			'<a href="' . esc_url( get_permalink( $args['post'] ) ) . '">' . esc_html( get_permalink( $args['post'] ) ) . '</a></strong>'
		),
		array( 'a' => array( 'href' => array() ) )
	);
	?>
	</p>
</div>

<div class="subscription-settings">
	<hr>
	<?php
	echo wp_kses(
		sprintf(
		// translators: %1$s is a URL, %2$s is a URL, %3$s is a username, %4$s is a URL.
			__( 'Manage your <a href=%1$s>global notification settings</a>, <a href=%2$s>change notifications for %3$s</a>, or <a href=%4$s>muffle posts like these</a>.', 'friends' ),
			'"' . esc_url( self_admin_url( 'admin.php?page=friends-settings' ) ) . '"',
			'"' . esc_url( self_admin_url( 'admin.php?page=edit-friend&user=' . $args['author']->ID ) ) . '"',
			'<em>' . esc_html( $args['author']->display_name ) . '</em>',
			'"' . esc_url( self_admin_url( 'admin.php?page=edit-friend-rules&user=' . $args['author']->ID . '&post=' . $args['post']->ID ) ) . '"'
		),
		array(
			'em' => array(),
			'a'  => array( 'href' => true ),
		)
	);

	?>
</div>
