<?php
/**
 * A conversation link in the direct message sidebar.
 *
 * @package Friends
 */

$time_format      = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$conversation_row = $args['conversation'];
$friend_user      = $conversation_row['friend_user'];
$is_selected      = $conversation_row['id'] === $args['selected_id'];
$item_url         = add_query_arg( 'conversation', $conversation_row['id'], home_url( '/friends/messages/' ) ) . '#friends-dm-conversation-' . $conversation_row['id'];
if ( ! empty( $args['friend_id'] ) ) {
	$item_url = add_query_arg( 'friend', $args['friend_id'], $item_url );
}
?>
				<a data-unread-count="<?php echo esc_attr( $conversation_row['unread_count'] ); ?>" class="friends-dm-conversation<?php echo $is_selected ? ' is-selected' : ''; ?><?php echo $conversation_row['unread_count'] ? ' is-unread' : ''; ?>" href="<?php echo esc_url( $item_url ); ?>">
					<?php if ( $friend_user && ! is_wp_error( $friend_user ) && $friend_user->get_avatar_url() ) : ?>
						<img class="avatar" src="<?php echo esc_url( $friend_user->get_avatar_url() ); ?>" alt="" width="40" height="40">
					<?php else : ?>
						<?php echo get_avatar( 0, 40, '', '', array( 'class' => 'avatar' ) ); ?>
					<?php endif; ?>
					<span class="friends-dm-conversation-main">
						<span class="friends-dm-conversation-name">
							<?php echo esc_html( $friend_user && ! is_wp_error( $friend_user ) ? $friend_user->display_name : __( 'Unknown sender', 'friends' ) ); ?>
						</span>
						<span class="friends-dm-conversation-preview"><?php echo esc_html( $conversation_row['preview'] ); ?></span>
					</span>
					<span class="friends-dm-conversation-meta">
						<time data-friends-relative-time="<?php echo esc_attr( $conversation_row['latest_time'] ); ?>" title="<?php echo esc_attr( date_i18n( $time_format, $conversation_row['latest_time'] ) ); ?>">
							<?php echo esc_html( human_time_diff( $conversation_row['latest_time'] ) ); ?>
						</time>
						<?php if ( intval( $conversation_row['latest']->post_author ) === get_current_user_id() ) : ?>
							<span class="friends-dm-delivery-status friends-dm-delivery-icon<?php echo $conversation_row['latest_delivery'] ? ' is-' . esc_attr( $conversation_row['latest_delivery']['status'] ) : ''; ?>" data-delivery-message-id="<?php echo esc_attr( $conversation_row['latest']->ID ); ?>"<?php echo $conversation_row['latest_delivery'] ? ' title="' . esc_attr( $conversation_row['latest_delivery']['title'] ) . '" aria-label="' . esc_attr( $conversation_row['latest_delivery']['label'] ) . '"' : ' hidden'; ?>></span>
						<?php endif; ?>
						<?php if ( $conversation_row['unread_count'] ) : ?>
							<span class="friends-dm-unread-count"><?php echo esc_html( number_format_i18n( $conversation_row['unread_count'] ) ); ?></span>
						<?php endif; ?>
					</span>
				</a>
