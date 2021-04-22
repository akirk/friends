<?php
/**
 * This template contains the header for the main feed on /friends/.
 *
 * @package Friends
 */

$data = $args['friends']->get_main_header_data();
?><div id="main-header">
<h2 id="page-title"><a href="<?php echo esc_url( site_url( '/friends/' ) ); ?>">
<?php
switch ( $args['friends']->frontend->post_format ) {
	case 'standard':
			echo esc_html( _x( 'Post feed', 'Post format', 'friends' ) );
		break;
	case 'aside':
			echo esc_html( _x( 'Aside feed', 'Post format', 'friends' ) );
		break;
	case 'chat':
			echo esc_html( _x( 'Chat feed', 'Post format', 'friends' ) );
		break;
	case 'gallery':
			echo esc_html( _x( 'Gallery feed', 'Post format', 'friends' ) );
		break;
	case 'link':
			echo esc_html( _x( 'Link feed', 'Post format', 'friends' ) );
		break;
	case 'image':
			echo esc_html( _x( 'Image feed', 'Post format', 'friends' ) );
		break;
	case 'quote':
			echo esc_html( _x( 'Quote feed', 'Post format', 'friends' ) );
		break;
	case 'status':
			echo esc_html( _x( 'Status feed', 'Post format', 'friends' ) );
		break;
	case 'video':
			echo esc_html( _x( 'Video feed', 'Post format', 'friends' ) );
		break;
	case 'audio':
			echo esc_html( _x( 'Audio feed', 'Post format', 'friends' ) );
		break;
	default:
			esc_html_e( 'Main Feed' );
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
	<a class="chip" href="<?php echo esc_url( site_url( '/friends/type/' . $post_format . '/' ) ); ?>"><?php echo esc_html( $args['friends']->get_post_format_plural_string( $post_format, $count ) ); ?></a>
<?php endforeach; ?>

<a class="chip" href="<?php echo esc_attr( self_admin_url( 'admin.php?page=add-friend' ) ); ?>"><?php esc_html_e( 'Add New Friend', 'friends' ); ?></a>
<a class="chip" href="<?php echo esc_attr( self_admin_url( 'admin.php?page=friends-settings' ) ); ?>"><?php esc_html_e( 'Settings' ); ?></a>
<?php do_action( 'friends_main_feed_header', $args['friend_user'] ); ?>
</div>
