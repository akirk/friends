<?php
/**
 * This is the Direct Messages view.
 *
 * @version 1.0
 * @package Friends
 */

if ( ! empty( $friends_args ) && is_array( $friends_args ) ) {
	$args = array_merge( $friends_args, $args );
}

$args['title']            = __( 'Direct Messages', 'friends' );
$args['no-bottom-margin'] = true;

$time_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
if ( false === strpos( $time_format, ':s' ) ) {
	$time_format = str_replace( 'H:i', 'H:i:s', $time_format );
	$time_format = str_replace( 'g:i', 'g:i:s', $time_format );
}

$get_message_friend_user = function ( $message ) {
	$terms = wp_get_object_terms( $message->ID, Friends\Messages::TAXONOMY );
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		$term = reset( $terms );
		$user = Friends\User::get_user_by_id( absint( $term->slug ) );
		if ( $user ) {
			return $user;
		}
	}

	return Friends\User::get_post_author( $message );
};

$top_level_messages = get_posts(
	array(
		'post_type'      => Friends\Messages::CPT,
		'post_parent'    => 0,
		'post_status'    => array( 'friends_read', 'friends_unread' ),
		'posts_per_page' => -1,
	)
);

$conversation_rows = array();
foreach ( $top_level_messages as $top_level_message ) {
	$thread_messages = get_posts(
		array(
			'post_parent'    => $top_level_message->ID,
			'post_type'      => Friends\Messages::CPT,
			'post_status'    => array( 'friends_read', 'friends_unread' ),
			'order'          => 'ASC',
			'orderby'        => 'date',
			'posts_per_page' => -1,
		)
	);
	array_unshift( $thread_messages, $top_level_message );

	$latest_message      = $top_level_message;
	$latest_message_time = get_post_modified_time( 'U', true, $top_level_message );
	$unread_count        = 0;
	foreach ( $thread_messages as $thread_message ) {
		if ( 'friends_unread' === get_post_status( $thread_message ) ) {
			++$unread_count;
		}

		$message_time = get_post_modified_time( 'U', true, $thread_message );
		if ( $message_time > $latest_message_time ) {
			$latest_message      = $thread_message;
			$latest_message_time = $message_time;
		}
	}

	$friend_user = $get_message_friend_user( $top_level_message );
	if ( ! $friend_user || is_wp_error( $friend_user ) || get_current_user_id() === intval( $friend_user->ID ) ) {
		foreach ( $thread_messages as $thread_message ) {
			$thread_author = Friends\User::get_post_author( $thread_message );
			if ( $thread_author && ! is_wp_error( $thread_author ) && get_current_user_id() !== intval( $thread_author->ID ) ) {
				$friend_user = $thread_author;
				break;
			}
		}
	}

	$message_preview = get_the_title( $latest_message );
	if ( ! $message_preview ) {
		$message_preview = wp_strip_all_tags( get_the_excerpt( $latest_message ) );
	}

	$conversation_rows[] = array(
		'id'           => $top_level_message->ID,
		'friend_user'  => $friend_user,
		'messages'     => $thread_messages,
		'latest'       => $latest_message,
		'latest_time'  => $latest_message_time,
		'preview'      => $message_preview,
		'unread_count' => $unread_count,
		'root_message' => $top_level_message,
	);
}

usort(
	$conversation_rows,
	function ( $a, $b ) {
		return $b['latest_time'] - $a['latest_time'];
	}
);

$selected_conversation_id = isset( $_GET['conversation'] ) ? absint( $_GET['conversation'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$selected_conversation    = null;
foreach ( $conversation_rows as $conversation_row ) {
	if ( ! $selected_conversation || $conversation_row['id'] === $selected_conversation_id ) {
		$selected_conversation = $conversation_row;
	}
}

Friends\Friends::template_loader()->get_template_part( 'frontend/header', null, $args );
?>
<section class="friends-dm-view" aria-label="<?php esc_attr_e( 'Direct Messages', 'friends' ); ?>">
	<?php if ( empty( $conversation_rows ) ) : ?>
		<div class="friends-dm-empty">
			<h3><?php esc_html_e( 'No direct messages yet.', 'friends' ); ?></h3>
			<p><?php esc_html_e( 'Open a friend profile to start a conversation.', 'friends' ); ?></p>
		</div>
	<?php else : ?>
		<nav class="friends-dm-sidebar" aria-label="<?php esc_attr_e( 'Conversations', 'friends' ); ?>">
			<?php foreach ( $conversation_rows as $conversation_row ) : ?>
				<?php
				$friend_user = $conversation_row['friend_user'];
				$is_selected = $selected_conversation && $conversation_row['id'] === $selected_conversation['id'];
				$item_url    = add_query_arg( 'conversation', $conversation_row['id'], home_url( '/friends/messages/' ) );
				?>
				<a class="friends-dm-conversation<?php echo $is_selected ? ' is-selected' : ''; ?><?php echo $conversation_row['unread_count'] ? ' is-unread' : ''; ?>" href="<?php echo esc_url( $item_url ); ?>">
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
						<?php if ( $conversation_row['unread_count'] ) : ?>
							<span class="friends-dm-unread-count"><?php echo esc_html( number_format_i18n( $conversation_row['unread_count'] ) ); ?></span>
						<?php endif; ?>
					</span>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php
		$selected_friend_user = $selected_conversation['friend_user'];
		$selected_url         = add_query_arg( 'conversation', $selected_conversation['id'], home_url( '/friends/messages/' ) );
		?>
		<article class="friends-dm-thread" data-id="<?php echo esc_attr( $selected_conversation['id'] ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-mark-read' ) ); ?>" data-unread="<?php echo esc_attr( $selected_conversation['unread_count'] ? '1' : '0' ); ?>">
			<header class="friends-dm-thread-header">
				<?php if ( $selected_friend_user && ! is_wp_error( $selected_friend_user ) && $selected_friend_user->get_avatar_url() ) : ?>
					<img class="avatar" src="<?php echo esc_url( $selected_friend_user->get_avatar_url() ); ?>" alt="" width="44" height="44">
				<?php else : ?>
					<?php echo get_avatar( 0, 44, '', '', array( 'class' => 'avatar' ) ); ?>
				<?php endif; ?>
				<div>
					<h3><?php echo esc_html( $selected_friend_user && ! is_wp_error( $selected_friend_user ) ? $selected_friend_user->display_name : __( 'Unknown sender', 'friends' ) ); ?></h3>
					<?php if ( $selected_friend_user && ! is_wp_error( $selected_friend_user ) ) : ?>
						<a href="<?php echo esc_url( $selected_friend_user->get_local_friends_page_url() ); ?>"><?php esc_html_e( 'View profile', 'friends' ); ?></a>
					<?php endif; ?>
				</div>
			</header>

			<div class="friends-dm-messages">
				<?php $previous_message_author_key = null; ?>
				<?php foreach ( $selected_conversation['messages'] as $message ) : ?>
					<?php
					$message_author = Friends\User::get_post_author( $message );
					$is_own_message = $message_author && ! is_wp_error( $message_author ) && get_current_user_id() === intval( $message_author->ID );
					$author_name    = $message_author && ! is_wp_error( $message_author ) ? $message_author->display_name : __( 'Unknown sender', 'friends' );
					$post_time      = get_post_modified_time( 'U', true, $message );
					$author_key     = $message_author && ! is_wp_error( $message_author ) ? 'user-' . $message_author->ID : 'unknown';
					$is_consecutive = $author_key === $previous_message_author_key;
					?>
					<div class="friends-dm-message<?php echo $is_own_message ? ' is-own-message' : ''; ?><?php echo $is_consecutive ? ' is-consecutive-message' : ''; ?>" data-message-id="<?php echo esc_attr( $message->ID ); ?>">
						<div class="friends-dm-message-avatar">
							<?php if ( ! $is_own_message && $message_author && ! is_wp_error( $message_author ) && $message_author->get_avatar_url() ) : ?>
								<img class="avatar" src="<?php echo esc_url( $message_author->get_avatar_url() ); ?>" alt="" width="32" height="32">
							<?php else : ?>
								<?php echo get_avatar( $is_own_message ? get_current_user_id() : 0, 32, '', '', array( 'class' => 'avatar' ) ); ?>
							<?php endif; ?>
						</div>
						<div class="friends-dm-message-body">
							<div class="friends-dm-message-meta">
								<strong><?php echo esc_html( $is_own_message ? __( 'You', 'friends' ) : $author_name ); ?></strong>
								<time data-friends-relative-time="<?php echo esc_attr( $post_time ); ?>" data-friends-relative-time-suffix="<?php echo esc_attr_x( ' ago', 'relative message timestamp suffix', 'friends' ); ?>" title="<?php echo esc_attr( date_i18n( $time_format, $post_time ) ); ?>">
									<?php
									echo esc_html(
										sprintf(
											// translators: %s is a time span.
											__( '%s ago' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
											human_time_diff( $post_time )
										)
									);
									?>
								</time>
							</div>
							<div class="friends-dm-message-content" title="<?php echo esc_attr( date_i18n( $time_format, $post_time ) ); ?>">
								<?php
								$content = make_clickable( get_the_content( null, false, $message ) );
								echo wp_kses_post( apply_filters( 'the_content', $content ) );
								?>
							</div>
						</div>
					</div>
					<?php $previous_message_author_key = $author_key; ?>
				<?php endforeach; ?>
			</div>

			<footer class="friends-dm-reply">
				<?php
				$form_args = array(
					'blocks-everywhere' => false,
					'friend_user'       => $selected_friend_user,
					'redirect_to'       => $selected_url,
					'reply_to'          => $selected_conversation['id'],
					'subject'           => get_the_title( $selected_conversation['root_message'] ),
				);

				$feed_url = get_post_meta( $selected_conversation['root_message']->ID, 'friends_feed_url', true );
				if ( $feed_url ) {
					$form_args['accounts'] = array( $feed_url => $feed_url );
					$accounts              = apply_filters( 'friends_message_form_accounts', array(), $selected_friend_user );
					if ( isset( $accounts[ $feed_url ] ) ) {
						$form_args['accounts'][ $feed_url ] = $accounts[ $feed_url ];
					}
				} else {
					$form_args['accounts'] = apply_filters( 'friends_message_form_accounts', array(), $selected_friend_user );
				}

				if ( $selected_friend_user && ! is_wp_error( $selected_friend_user ) && ! empty( $form_args['accounts'] ) ) {
					Friends\Friends::template_loader()->get_template_part(
						'frontend/messages/message-form',
						null,
						array_merge( $args, $form_args )
					);
				}
				?>
			</footer>
		</article>
	<?php endif; ?>
</section>
<?php
Friends\Friends::template_loader()->get_template_part( 'frontend/footer', null, $args );
