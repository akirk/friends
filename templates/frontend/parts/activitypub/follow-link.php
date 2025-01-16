<?php
/**
 * This template contains the activitypub follow link.
 *
 * @version 1.0
 * @package Friends
 */

?>
<a href="<?php echo esc_url( add_query_arg( 'url', $args['url'], admin_url( 'admin.php?page=add-friend' ) ) ); ?>" class="has-icon-left" title="<?php echo esc_attr( $args['summary'] ); ?>"><span class="dashicons dashicons-plus"></span><?php echo esc_html( /* translators: %s is a username. */ sprintf( __( 'Follow %s', 'friends' ), $args['name'] ) ); ?></a>
