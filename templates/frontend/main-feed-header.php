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
$_search_term = '';
if ( isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$_search_term = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$_title = sprintf(
		// translators: %s is a search term.
		__( 'Search for "%s"', 'friends' ),
		esc_html( $_search_term )
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
} elseif ( $args['friends']->frontend->tag ) {
	echo esc_html(
		sprintf(
		// translators: %1$s is a hash tag, %2$s is a type of feed, e.g. "Main Feed".
			_x( '#%1$s on %2$s', '#tag on feed', 'friends' ),
			$args['friends']->frontend->tag,
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

<?php if ( $_search_term ) : ?>
	<?php
	$_current_order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<a class="chip" href="<?php echo esc_url( remove_query_arg( 's' ) ); ?>">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s is the search term */
				__( 'Clear search for "%s"', 'friends' ),
				$_search_term
			)
		);
		?>
	</a>
	<?php if ( 'DESC' === $_current_order ) : ?>
		<a class="chip" href="<?php echo esc_url( add_query_arg( 'order', 'ASC' ) ); ?>">
			<?php esc_html_e( 'Oldest first', 'friends' ); ?>
		</a>
	<?php else : ?>
		<a class="chip active" href="<?php echo esc_url( add_query_arg( 'order', 'ASC' ) ); ?>">
			<?php esc_html_e( 'Oldest first', 'friends' ); ?>
		</a>
		<a class="chip" href="<?php echo esc_url( remove_query_arg( 'order' ) ); ?>">
			<?php esc_html_e( 'Newest first', 'friends' ); ?>
		</a>
	<?php endif; ?>
	<a class="chip toggle-compact" href=""><?php echo esc_html( 'collapsed' === $args['frontend_default_view'] ? __( 'Expanded mode', 'friends' ) : __( 'Compact mode', 'friends' ) ); ?></a>
<?php else : ?>
	<?php foreach ( $data['post_count_by_post_format'] as $post_format => $count ) : ?>
		<a class="chip post-count-<?php echo esc_attr( $post_format ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends_post_counts' ) ); ?>" href="<?php echo esc_url( home_url( '/friends/type/' . $post_format . '/' ) ); ?>"><?php echo esc_html( $args['friends']->get_post_format_plural_string( $post_format, $count ) ); ?></a>
	<?php endforeach; ?>

	<?php if ( isset( $_GET['show-hidden'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<a class="chip" href="<?php echo esc_attr( remove_query_arg( 'show-hidden' ) ); ?>">
			<?php echo esc_html__( 'Hide hidden items', 'friends' ); ?>
		</a>
	<?php elseif ( $hidden_post_count > 0 ) : ?>
		<a class="chip" href="<?php echo esc_attr( add_query_arg( 'show-hidden', 1 ) ); ?>">
			<?php echo esc_html( sprintf( /* translators: %s is the number of hidden posts */_n( '%s hidden items', '%s hidden items', $hidden_post_count, 'friends' ), number_format_i18n( $hidden_post_count ) ) ); ?>
		</a>
	<?php elseif ( $hidden_post_count ) : ?>
		<a class="chip post-count-trash" href="<?php echo esc_attr( add_query_arg( 'show-hidden', 1 ) ); ?>">
			<?php echo esc_html( sprintf( /* translators: %s is the number of hidden posts */_n( '%s hidden items', '%s hidden items', $hidden_post_count, 'friends' ), $hidden_post_count ) ); ?>
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

	<a class="chip toggle-compact" href=""><?php echo esc_html( 'collapsed' === $args['frontend_default_view'] ? __( 'Expanded mode', 'friends' ) : __( 'Compact mode', 'friends' ) ); ?></a>
<?php endif; ?>

<?php do_action( 'friends_main_feed_header', $args ); ?>
</div>
