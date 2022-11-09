<?php
/**
 * This template contains the header for the main feed on /friends/.
 *
 * @package Friends
 */

$data = $args['friends']->get_main_header_data();
?><div id="main-header" class="mb-2">
<h2 id="page-title"><a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
<?php
$title = __( 'Main Feed', 'friends' );
switch ( $args['friends']->frontend->post_format ) {
	case 'standard':
		$title = _x( 'Post feed', 'Post format', 'friends' );
		break;
	case 'aside':
		$title = _x( 'Aside feed', 'Post format', 'friends' );
		break;
	case 'chat':
		$title = _x( 'Chat feed', 'Post format', 'friends' );
		break;
	case 'gallery':
		$title = _x( 'Gallery feed', 'Post format', 'friends' );
		break;
	case 'link':
		$title = _x( 'Link feed', 'Post format', 'friends' );
		break;
	case 'image':
		$title = _x( 'Image feed', 'Post format', 'friends' );
		break;
	case 'quote':
		$title = _x( 'Quote feed', 'Post format', 'friends' );
		break;
	case 'status':
		$title = _x( 'Status feed', 'Post format', 'friends' );
		break;
	case 'video':
		$title = _x( 'Video feed', 'Post format', 'friends' );
		break;
	case 'audio':
		$title = _x( 'Audio feed', 'Post format', 'friends' );
		break;
}

if ( $args['friends']->frontend->reaction ) {
	echo esc_html(
		sprintf(
		// translators: %1$s is an emoji reaction, %2$s is a type of feed, e.g. "Main Feed".
			__( 'My %1$s reactions on %2$s', 'friends' ),
			$args['friends']->frontend->reaction,
			$title
		)
	);
} else {
	echo esc_html( $title );
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
<?php do_action( 'friends_main_feed_header', $args ); ?>
</div>
