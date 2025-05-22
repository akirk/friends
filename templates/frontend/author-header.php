<?php
/**
 * This template contains the author header on /friends/.
 *
 * @package Friends
 */

$edit_user_link = $args['friends']->admin->admin_edit_user_link( false, $args['friend_user'] );
$_feeds = count( $args['friend_user']->get_feeds() );
$rules = count( $args['friend_user']->get_feed_rules() );
$active_feeds = count( $args['friend_user']->get_active_feeds() );
$hidden_post_count = $args['friend_user']->get_post_in_trash_count();

?><div id="author-header" class="mb-2">
<h2 id="page-title">
	<?php if ( $args['friend_user']->is_starred() ) : ?>
		<a href="" class="dashicons dashicons-star-filled starred" data-id="<?php echo esc_attr( $args['friend_user']->user_login ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'star-' . $args['friend_user']->user_login ) ); ?>"></a>
	<?php else : ?>
		<a href="" class="dashicons dashicons-star-empty not-starred" data-id="<?php echo esc_attr( $args['friend_user']->user_login ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'star-' . $args['friend_user']->user_login ) ); ?>"></a>
	<?php endif; ?>
	<a href="<?php echo esc_attr( $args['friend_user']->get_local_friends_page_url() ); ?>">
<?php
if ( $args['friends']->frontend->reaction ) {
	echo esc_html(
		sprintf(
		// translators: %1$s is an emoji reaction, %2$s is a type of feed, e.g. "Main Feed".
			__( 'My %1$s reactions on %2$s', 'friends' ),
			$args['friends']->frontend->reaction,
			$args['friend_user']->display_name
		)
	);
} else {
	if ( $args['friend_user']->get_avatar_url() ) {
		?>
		<img src="<?php echo esc_attr( $args['friend_user']->get_avatar_url() ); ?>" alt="<?php echo esc_attr( $args['friend_user']->display_name ); ?>" class="avatar" width="36" height="36" style="vertical-align: middle;" />
		<?php
	}
	echo esc_html( $args['friend_user']->display_name );
}
?>
</a>
</h2>

<?php if ( $args['friend_user']->description ) : ?>
	<p>
	<?php
	echo wp_kses(
		make_clickable( str_replace( '</p>', '<br/>', $args['friend_user']->description ) ),
		array(
			'a'    => array( 'href' => array() ),
			'span' => array( 'class' => array() ),
			'br'   => array(),
		)
	);
	?>
	</p>
<?php endif; ?>

<span class="chip"><?php echo esc_html( $args['friend_user']->get_role_name() ); ?></span>

<?php if ( apply_filters( 'friends_debug', false ) ) : ?>
	<span class="chip"><?php echo esc_html( get_class( $args['friend_user'] ) ); ?></span>
<?php endif; ?>

<span class="chip"><?php echo /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html( sprintf( /* translators: %s is a localized date (F j, Y) */__( 'Since %s', 'friends' ), date_i18n( __( 'F j, Y' ), strtotime( $args['friend_user']->user_registered ) ) ) ); ?></span>

<?php
if ( ! empty( $args['friend_user']->user_url ) ) {
	$user_url_parts = wp_parse_url( $args['friend_user']->user_url );
	if ( isset( $user_url_parts['host'] ) ) {
		$user_hostname = $user_url_parts['host'];
		if ( substr( $user_hostname, 0, 4 ) === 'www.' ) {
			$user_hostname = substr( $user_hostname, 4 );
		}
		if ( isset( $user_url_parts['path'] ) && substr( $user_url_parts['path'], 0, 2 ) === '/@' ) {
			$p = strpos( $user_url_parts['path'], '/', 1 );
			if ( false === $p ) {
				$p = strlen( $user_url_parts['path'] );
			}
			$user_hostname .= esc_html( substr( $user_url_parts['path'], 0, $p ) );
		}
		?>
		<a class="chip" href="<?php echo esc_url( $args['friend_user']->user_url ); ?>"><?php echo esc_html( $user_hostname ); ?></a>
		<?php
	}
}
?>

<?php foreach ( $args['friend_user']->get_post_count_by_post_format() as $post_format => $count ) : ?>
	<a class="chip" href="<?php echo esc_attr( $args['friend_user']->get_local_friends_page_post_format_url( $post_format ) ); ?>"><?php echo esc_html( $args['friends']->get_post_format_plural_string( $post_format, $count ) ); ?></a>
<?php endforeach; ?>
<?php if ( isset( $_GET['show-hidden'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<a class="chip" href="<?php echo esc_attr( remove_query_arg( 'show-hidden' ) ); ?>">
		<?php echo esc_html__( 'Hide hidden items', 'friends' ); ?>
	</a>
<?php elseif ( $hidden_post_count > 0 ) : ?>
	<a class="chip" href="<?php echo esc_attr( add_query_arg( 'show-hidden', 1 ) ); ?>">
		<?php echo esc_html( sprintf( /* translators: %s is the number of hidden posts */_n( '%s hidden items', '%s hidden items', $hidden_post_count, 'friends' ), number_format_i18n( $hidden_post_count ) ) ); ?>
	</a>
<?php endif; ?>

<?php foreach ( Friends\Reactions::get_available_emojis() as $slug => $reaction ) : ?>
	<a class="chip" href="<?php echo esc_attr( $args['friend_user']->get_local_friends_page_reaction_url( $slug ) ); ?>">
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

<?php if ( apply_filters( 'friends_show_author_edit', true, $args['friend_user'] ) ) : ?>
<a class="chip" href="<?php echo esc_attr( self_admin_url( 'admin.php?page=edit-friend-feeds&user=' . $args['friend_user']->user_login ) ); ?>">
	<?php echo esc_html( sprintf( /* translators: %s is the number of feeds */_n( '%s feed', '%s feeds', $active_feeds, 'friends' ), number_format_i18n( $active_feeds ) ) ); ?>

	<?php if ( $_feeds - $active_feeds > 1 ) : ?>
	&nbsp;<small><?php echo esc_html( sprintf( /* translators: %s is the number of feeds */_n( '(+%s more)', '(+%s more)', $_feeds - $active_feeds, 'friends' ), number_format_i18n( $_feeds - $active_feeds ) ) ); ?></small>
	<?php endif; ?>
</a>

	<?php if ( $rules > 0 ) : ?>
<a class="chip" href="<?php echo esc_attr( self_admin_url( 'admin.php?page=edit-friend-rules&user=' . $args['friend_user']->user_login . '&_wpnonce=' . wp_create_nonce( 'edit-friend-rules-' . $args['friend_user']->user_login ) ) ); ?>">
		<?php
		// translators: %d is the number of rules.
		echo esc_html( sprintf( _n( '%d rule', '%d rules', $rules, 'friends' ), $rules ) );
		?>
</a>
	<?php endif; ?>

<a class="chip" href="<?php echo esc_attr( $edit_user_link ); ?>"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Edit' ); ?></a>
<?php endif; ?>

<?php if ( $args['friend_user']->can_refresh_feeds() && apply_filters( 'friends_debug', false ) ) : ?>
<a class="chip" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'user', $args['friend_user']->user_login, self_admin_url( 'admin.php?page=friends-refresh' ) ), 'friends-refresh' ) ); ?>"><?php esc_html_e( 'Refresh', 'friends' ); ?></a>
<?php endif; ?>

<a class="chip toggle-compact" href=""><?php echo esc_html( 'collapsed' === $args['frontend_default_view'] ? __( 'Expanded mode', 'friends' ) : __( 'Compact mode', 'friends' ) ); ?></a>

<?php do_action( 'friends_author_header', $args['friend_user'], $args ); ?>
</div>
