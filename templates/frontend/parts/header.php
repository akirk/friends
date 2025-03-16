<?php
/**
 * This template contains the content header part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$friend_user = $args['friend_user'];
$avatar = $args['avatar'];
$author_name = $args['friend_user']->display_name;

/**
 * Allows overriding the authorname for a post. The original author will still be displayed.
 *
 * @param string $override_author_name The author name to override with.
 * @param string $author_name The author name.
 * @param int $post_id The post ID.
 *
 * Example:
 * ```php
 * add_filter( 'friends_override_author_name', function( $override_author_name, $author_name, $post_id ) {
 *     if ( ! $override_author_name ) { // Only override if not already overridden.
 *         $override_author_name = get_post_meta( $post_id, 'author', true );
 *     }
 *     return $override_author_name;
 * }, 10, 3 );
 * ```
 */
$override_author_name = apply_filters( 'friends_override_author_name', '', $author_name, get_the_id() );

/**
 * Allows modifying the avatar for a post.
 *
 * @param string $avatar The avatar.
 * @param string $friend_user The friend user if any.
 * @param int $post_id The post ID.
 *
 * Example:
 * ```php
 * add_filter( 'friends_author_avatar_url', function( $avatar, $friend_user, $post_id ) {
 *     return get_avatar_url( 'mystery-man' );
 * }, 10, 3 );
 * ```
 */
+ 45
?><header class="entry-header card-header columns">
	<div class="avatar col-auto mr-2 translator-exclude">
		<?php if ( in_array( get_post_type(), apply_filters( 'friends_frontend_post_types', array() ), true ) ) : ?>
			<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() ); ?>" class="author-avatar">
				<?php echo get_avatar( $args['friend_user']->ID, 36 ); ?>
			</a>
		<?php else : ?>
			<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" class="author-avatar">
				<img src="<?php echo esc_url( $avatar ? $avatar : get_avatar_url( get_the_author_meta( 'ID' ) ) ); ?>" width="36" height="36" class="avatar" />
			</a>
		<?php endif; ?>
	</div>
	<div class="post-meta translator-exclude">
		<div class="author">
			<?php if ( in_array( get_post_type(), apply_filters( 'friends_frontend_post_types', array() ), true ) ) : ?>
				<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() ); ?>">
					<strong><?php echo esc_html( $friend_user->display_name ); ?></strong>
					<?php if ( $override_author_name && trim( str_replace( $override_author_name, '', $author_name ) ) === $author_name ) : ?>
						– <?php echo esc_html( $override_author_name ); ?>
					<?php endif; ?>
				</a>
				<?php do_action( 'friends_post_author_meta', $friend_user ); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
					<strong><?php the_author(); ?></strong>
				</a>
			<?php endif; ?>
		</div>
		<div class="permalink">
		<?php
		echo wp_kses(
			sprintf(
				// translators: %1$s is a date or relative time, %2$s is a site name or domain.
				_x( '%1$s on %2$s', 'at-date-on-post', 'friends' ),
				'<a href="' . esc_attr( $friend_user->get_local_friends_page_url() . get_the_ID() . '/' ) . '" title="' . get_the_time( 'r' ) . '">' .
				/* translators: %s is a time span */ sprintf( __( '%s ago' ), human_time_diff( get_post_time( 'U', true ) ) ) . // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'</a>',
				'<a href="' . esc_url( get_the_permalink() ) . '" rel="noopener noreferrer" target="_blank">' . esc_html( wp_parse_url( get_the_permalink(), PHP_URL_HOST ) ) . '</a>'
			),
			array(
				'a' => array(
					'href'   => array(),
					'rel'    => array(),
					'target' => array(),
					'title'  => array(),
				),
			)
		);

		if ( isset( $args['read_time'] ) ) {
			echo ' <span class="reading-time" title="', esc_html__( 'Estimated reading time', 'friends' ), '">', esc_html(
				sprintf(
					// translators: %s is a timeframe, e.g. < 1 min or 2min.
					__( '%s read', 'friends' ),
					$args['read_time']
				)
			), '</span>';
		}
		?>
		</div>
	</div>
	<div class="overflow col-ml-auto">
		<a class="btn btn-link collapse-post" tabindex="0" title="<?php esc_html_e( 'Double or meta/shift/cmd click to toggle all', 'friends' ); ?>">
			<i class="dashicons dashicons-fullscreen-exit-alt"></i>
		</a>

		<div class="friends-dropdown friends-dropdown-right">
			<a class="btn btn-link friends-dropdown-toggle" tabindex="0">
				<i class="dashicons dashicons-menu-alt2"></i>
			</a>
			<ul class="menu" style="min-width: <?php echo esc_attr( intval( _x( '250', 'dropdown-menu-width', 'friends' ) ) ); ?>px">
				<?php
				Friends\Friends::template_loader()->get_template_part(
					'frontend/parts/header-menu',
					null,
					$args
				);
				?>
			</ul>
		</div>

	</div>
</header>
