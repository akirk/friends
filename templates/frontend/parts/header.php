<?php
/**
 * This template contains the content header part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$friend_user = $args['friend_user'];
$avatar = $args['avatar'];
$author_name = get_the_author_meta( 'display_name' );
$override_author_name = apply_filters( 'friends_override_author_name', '', $author_name, get_the_id() );
?><header class="entry-header card-header columns">
	<div class="avatar col-auto">
		<?php if ( in_array( get_post_type(), Friends\Friends::get_frontend_post_types(), true ) ) : ?>
			<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() ); ?>" class="author-avatar">
				<img src="<?php echo esc_url( get_avatar_url( get_the_author_meta( 'ID' ) ) ); ?>" width="36" height="36" class="avatar" />
			</a>
		<?php else : ?>
			<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" class="author-avatar">
				<img src="<?php echo esc_url( $avatar ? $avatar : get_avatar_url( get_the_author_meta( 'ID' ) ) ); ?>" width="36" height="36" class="avatar" />
			</a>
		<?php endif; ?>
	</div>
	<div class="post-meta">
		<div class="author">
			<?php if ( in_array( get_post_type(), Friends\Friends::get_frontend_post_types(), true ) ) : ?>
				<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() ); ?>">
					<strong><?php the_author(); ?></strong>
					<?php if ( $override_author_name && trim( str_replace( $override_author_name, '', $author_name ) ) === $author_name ) : ?>
						– <?php echo esc_html( $override_author_name ); ?>
					<?php endif; ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
					<strong><?php the_author(); ?></strong>
				</a>
			<?php endif; ?>
		</div>
		<?php
		echo wp_kses(
			sprintf(
				// translators: %1$s is a date or relative time, %2$s is a site name or domain.
				_x( '%1$s on %2$s', 'at-date-on-post', 'friends' ),
				'<a href="' . esc_attr( $friend_user->get_local_friends_page_url() . get_the_ID() . '/' ) . '" title="' . get_the_time( 'r' ) . '">' .
				/* translators: %s is a time span */ sprintf( __( '%s ago' ), human_time_diff( get_post_time( 'U', true ) ) ) . // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'</a>',
				'<a href="' . esc_url( get_the_permalink() ) . '" rel="noopener noreferrer" target="_blank">' . esc_html( parse_url( get_the_permalink(), PHP_URL_HOST ) ) . '</a>'
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
			echo ' | <span title="', esc_html__( 'Estimated reading time', 'friends' ), '">', esc_html(
				sprintf(
					// translators: %s is a timeframe, e.g. < 1 min or 2min.
					__( '%s read', 'friends' ),
					$args['read_time']
				)
			), '</span>';
		}
		?>
	</div>
	<div class="overflow col-ml-auto">
		<a class="btn btn-link collapse-post" tabindex="0">
			<i class="dashicons dashicons-fullscreen-exit-alt"></i>
		</a>

		<div class="friends-dropdown friends-dropdown-right">
			<a class="btn btn-link friends-dropdown-toggle" tabindex="0">
				<i class="dashicons dashicons-menu-alt2"></i>
			</a>
			<ul class="menu" style="min-width: <?php echo esc_attr( intval( _x( '250', 'dropdown-menu-width', 'friends' ) ) ); ?>px">
				<?php
				$edit_user_link = $args['friends']->admin->admin_edit_user_link( false, get_the_author_meta( 'ID' ) );
				if ( $edit_user_link ) :
					?>
					<li class="menu-item"><a href="<?php echo esc_attr( $edit_user_link ); ?>"><?php esc_html_e( 'Edit friend', 'friends' ); ?></a></li>
				<?php endif; ?>
					<li class="menu-item friends-dropdown">
						<select name="post-format" class="friends-change-post-format form-select select-sm" data-change-post-format-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-change-post-format_' . get_the_ID() ) ); ?>" data-id="<?php echo esc_attr( get_the_ID() ); ?>" >
							<option disabled="disabled"><?php esc_html_e( 'Change post format', 'friends' ); ?></option>
							<?php foreach ( get_post_format_strings() as $format => $title ) : ?>
							<option value="<?php echo esc_attr( $format ); ?>"<?php selected( get_post_format(), $format ); ?>><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
						</select>
					</li>
				<?php if ( current_user_can( 'edit_post', get_current_user_id(), get_the_ID() ) ) : ?>
					<li class="menu-item"><?php edit_post_link(); ?></li>
				<?php endif; ?>
				<?php if ( in_array( get_post_type(), Friends\Friends::get_frontend_post_types(), true ) ) : ?>
					<li class="menu-item"><a href="<?php echo esc_url( self_admin_url( 'admin.php?page=edit-friend-rules&user=' . get_the_author_meta( 'ID' ) . '&post=' . get_the_ID() ) ); ?>" title="<?php esc_attr_e( 'Muffle posts like these', 'friends' ); ?>" class="friends-muffle-post">
						<?php esc_html_e( 'Muffle posts like these', 'friends' ); ?>
					</a></li>
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
				<?php do_action( 'friends_entry_dropdown_menu' ); ?>
			</ul>
		</div>

	</div>
</header>
