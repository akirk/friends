<?php
/**
 * Link from a friend profile to their direct messages.
 *
 * @package Friends
 */

?>
<div class="card mt-2 p-2 friends-profile-messages">
	<div class="friends-profile-messages-header">
		<a href="<?php echo esc_url( add_query_arg( 'friend', $args['friend_user']->ID, home_url( '/friends/messages/' ) ) ); ?>"><?php esc_html_e( 'View conversations', 'friends' ); ?></a>
	</div>
</div>
