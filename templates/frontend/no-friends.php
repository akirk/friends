<?php
/**
 * Displayed if user has no friends.
 *
 * @version 1.0
 * @package Friends
 */

esc_html_e( "You haven't added any friends or subscriptions yet.", 'friends' );
?>
<a href="<?php echo self_admin_url( 'admin.php?page=add-friend' ); ?>"><?php esc_html_e( 'Add a friend now', 'friends' ); ?></a>
