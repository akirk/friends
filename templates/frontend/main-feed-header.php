<?php
/**
 * This template contains the header for the main feed on /friends/.
 *
 * @package Friends
 */

$data = $args['friends']->get_main_header_data();
?><div id="main-header">
<h2 id="page-title"><a href="<?php echo esc_url( site_url( '/friends/' ) ); ?>"><?php esc_html_e( 'Main Feed' ); ?></a></h2>

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
