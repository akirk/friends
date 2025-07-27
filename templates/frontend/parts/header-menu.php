<?php
/**
 * This template contains the content header menu part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$friend_user = $args['friend_user'];

?>
<li class="menu-item">
	<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() . get_the_ID() . '/?share=' . hash( 'crc32b', apply_filters( 'friends_share_salt', wp_salt( 'nonce' ) ) . get_the_ID() ) ); ?>"><?php esc_html_e( 'Share link', 'friends' ); ?></a>
</li>

<?php if ( current_user_can( 'edit_post', get_current_user_id(), get_the_ID() ) ) : ?>
	<li class="menu-item"><?php edit_post_link(); ?></li>
<?php endif; ?>

<?php if ( in_array( get_post_type(), apply_filters( 'friends_frontend_post_types', array() ), true ) ) : ?>
	<?php if ( apply_filters( 'friends_show_author_edit', true, $args['friend_user'] ) ) : ?>
	<li class="menu-item"><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'user', $args['friend_user']->user_login, add_query_arg( 'post', get_the_ID(), self_admin_url( 'admin.php?page=edit-friend-rules' ) ) ), 'edit-friend-rules-' . $args['friend_user']->user_login ) ); ?>" title="<?php esc_attr_e( 'Muffle posts like these', 'friends' ); ?>" class="friends-muffle-post">
		<?php esc_html_e( 'Muffle posts like these', 'friends' ); ?>
	</a></li>
	<?php endif; ?>
	<li class="menu-item">
		<?php if ( 'trash' === get_post_status() ) : ?>
			<a href="#" title="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Restore from Trash' ); ?>" data-trash-nonce="<?php echo esc_attr( wp_create_nonce( 'trash-post_' . get_the_ID() ) ); ?>" data-untrash-nonce="<?php echo esc_attr( wp_create_nonce( 'untrash-post_' . get_the_ID() ) ); ?>" data-id="<?php echo esc_attr( get_the_ID() ); ?>" class="friends-untrash-post">
			<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Restore from Trash' ); ?>
			</a>
		<?php else : ?>
			<a href="#" title="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Move to Trash' ); ?>" data-trash-nonce="<?php echo esc_attr( wp_create_nonce( 'trash-post_' . get_the_ID() ) ); ?>" data-untrash-nonce="<?php echo esc_attr( wp_create_nonce( 'untrash-post_' . get_the_ID() ) ); ?>" data-id="<?php echo esc_attr( get_the_ID() ); ?>" class="friends-trash-post">
			<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Move to Trash' ); ?>
			</a>
		<?php endif; ?>
	</li>
<?php endif; ?>
<?php
do_action( 'friends_entry_dropdown_menu' );
