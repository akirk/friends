<?php
/**
 * This template contains the header for the main feed on /friends/.
 *
 * @package Friends
 */

$data = $args['friends']->get_main_header_data();
$hidden_post_count = 0;
if ( isset( $data['post_count_by_post_status']->trash ) ) {
	$hidden_post_count = $data['post_count_by_post_status']->trash;
}
?><div id="main-header" class="mb-2">
<h2 id="page-title"><a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
<?php
$_title = __( 'Main Feed', 'friends' );
switch ( $args['friends']->frontend->post_format ) {
	case 'standard':
		$_title = _x( 'Post feed', 'Post format', 'friends' );
		break;
	case 'aside':
		$_title = _x( 'Aside feed', 'Post format', 'friends' );
		break;
	case 'chat':
		$_title = _x( 'Chat feed', 'Post format', 'friends' );
		break;
	case 'gallery':
		$_title = _x( 'Gallery feed', 'Post format', 'friends' );
		break;
	case 'link':
		$_title = _x( 'Link feed', 'Post format', 'friends' );
		break;
	case 'image':
		$_title = _x( 'Image feed', 'Post format', 'friends' );
		break;
	case 'quote':
		$_title = _x( 'Quote feed', 'Post format', 'friends' );
		break;
	case 'status':
		$_title = _x( 'Status feed', 'Post format', 'friends' );
		break;
	case 'video':
		$_title = _x( 'Video feed', 'Post format', 'friends' );
		break;
	case 'audio':
		$_title = _x( 'Audio feed', 'Post format', 'friends' );
		break;
}
if ( isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$_title = sprintf(
		// translators: %s is a search term.
		__( 'Search for "%s"', 'friends' ),
		esc_html( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	);
}

if ( $args['friends']->frontend->reaction ) {
	echo esc_html(
		sprintf(
		// translators: %1$s is an emoji reaction, %2$s is a type of feed, e.g. "Main Feed".
			__( 'My %1$s reactions on %2$s', 'friends' ),
			$args['friends']->frontend->reaction,
			$_title
		)
	);
} else {
	echo esc_html( $_title );
}

?>
</a></h2>

<?php if ( $data['description'] ) : ?>
	<p>
	<?php
	echo wp_kses( $data['description'], array( 'a' => array( 'href' => array() ) ) );
	?>
	</p>
<?php endif; ?>

<?php foreach ( $data['post_count_by_post_format'] as $post_format => $count ) : ?>
	<a class="chip" href="<?php echo esc_url( home_url( '/friends/type/' . $post_format . '/' ) ); ?>"><?php echo esc_html( $args['friends']->get_post_format_plural_string( $post_format, $count ) ); ?></a>
<?php endforeach; ?>

<?php if ( isset( $_GET['show-hidden'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<a class="chip" href="<?php echo esc_attr( remove_query_arg( 'show-hidden' ) ); ?>">
		<?php echo esc_html__( 'Hide hidden items', 'friends' ); ?>
	</a>
<?php elseif ( $hidden_post_count > 0 ) : ?>
	<a class="chip" href="<?php echo esc_attr( add_query_arg( 'show-hidden', 1 ) ); ?>">
		<?php echo esc_html( sprintf( /* translators: %s is the number of hidden posts */_n( '%s hidden items', '%s hidden items', $hidden_post_count, 'friends' ), number_format_i18n( $hidden_post_count ) ) ); ?>
	</a>
<?php endif; ?>


<?php foreach ( Friends\Reactions::get_available_emojis() as $slug => $reaction ) : ?>
	<a class="chip" href="<?php echo esc_url( home_url( '/friends/reaction' . $slug . '/' ) ); ?>">
	<?php
	echo esc_html(
		sprintf(
			// translators: %s is an emoji.
			__( 'Reacted with %s', 'friends' ),
			$reaction->char
		)
	);
	?>
</a>
<?php endforeach; ?>

<a class="chip" href="<?php echo esc_attr( self_admin_url( 'admin.php?page=add-friend' ) ); ?>"><?php esc_html_e( 'Add New Friend', 'friends' ); ?></a>
<a class="chip" href="<?php echo esc_attr( self_admin_url( 'admin.php?page=friends-settings' ) ); ?>"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Settings' ); ?></a>

<?php if ( 'status' === $args['friends']->frontend->post_format ) : ?>
	<a class="chip quick-post-panel-toggle" href="#"><?php esc_html_e( 'Quick Post Panel', 'friends' ); ?></a>
<?php endif; ?>

<?php do_action( 'friends_main_feed_header', $args ); ?>
</div>
