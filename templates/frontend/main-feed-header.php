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
} elseif ( $args['friends']->frontend->tag ) {
	echo esc_html(
		sprintf(
		// translators: %1$s is a hash tag, %2$s is a type of feed, e.g. "Main Feed".
			_x( '#%1$s on %2$s', '#tag on feed', 'friends' ),
			$args['friends']->frontend->tag,
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

<?php if ( isset( $_GET['show-hidden'] ) ) : ?>
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
