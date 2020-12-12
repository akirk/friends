<?php
/**
 * This template contains the content header part for an article on /friends/.
 *
 * @package Friends
 */

?><header class="entry-header card-header columns">
	<div class="avatar col-auto">
		<?php if ( $friends->post_types->is_cached_post_type( get_post_type() ) ) : ?>
			<?php if ( $recommendation ) : ?>
				<a href="<?php the_permalink(); ?>" rel="noopener noreferrer">
					<img src="<?php echo esc_url( $avatar ); ?>" width="36" height="36" class="avatar" />
				</a>
			<?php else : ?>
				<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() ); ?>" class="author-avatar">
					<img src="<?php echo esc_url( get_avatar_url( get_the_author_meta( 'ID' ) ) ); ?>" width="36" height="36" class="avatar" />
				</a>
			<?php endif; ?>
		<?php else : ?>
			<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" class="author-avatar">
				<img src="<?php echo esc_url( $avatar ? $avatar : get_avatar_url( get_the_author_meta( 'ID' ) ) ); ?>" width="36" height="36" class="avatar" />
			</a>
		<?php endif; ?>
	</div>
	<div class="post-meta col-auto">
		<div class="author">
			<?php if ( $friends->post_types->is_cached_post_type( get_post_type() ) ) : ?>
				<?php if ( $recommendation ) : ?>
					<a href="<?php the_permalink(); ?>" rel="noopener noreferrer" ><strong><?php echo esc_html( get_post_meta( get_the_ID(), 'author', true ) ); ?></strong></a>
				<?php else : ?>
					<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() ); ?>">
						<strong><?php the_author(); ?></strong>
					</a>
				<?php endif; ?>
			<?php else : ?>
				<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
					<strong><?php the_author(); ?></strong>
				</a>
			<?php endif; ?>
		</div>
		<a href="<?php echo esc_attr( $friend_user->get_local_friends_page_url() . get_the_ID() . '/' ); ?>" title="<?php echo get_the_time( 'r' ); ?>"><?php /* translators: %s is a time span */ printf( __( '%s ago' ), human_time_diff( get_the_time( 'U' ), time() ) ); ?></a>
	</div>
	<div class="overflow col-ml-auto">
		<div class="dropdown dropdown-right">
			<a href="#" class="btn btn-link dropdown-toggle" tabindex="0">
				<i class="dashicons dashicons-menu-alt2"></i>
			</a>
			<!-- menu component -->
			<ul class="menu">
				<?php
				$edit_user_link = $friends->admin->admin_edit_user_link( false, get_the_author_meta( 'ID' ) );
				if ( $edit_user_link ) :
					?>
					<li class="menu-item"><a href="<?php echo esc_attr( $edit_user_link ); ?>">Edit friend</a></li>
				<?php endif; ?>
				<?php if ( current_user_can( 'edit_post', $post->ID ) ) : ?>
					<li class="menu-item"><?php edit_post_link(); ?></li>
				<?php endif; ?>
				<?php if ( $friends->post_types->is_cached_post_type( get_post_type() ) ) : ?>
					<li class="menu-item"><a href="#" title="<?php esc_attr_e( 'Trash this post', 'friends' ); ?>" data-trash-nonce="<?php echo esc_attr( wp_create_nonce( 'trash-post_' . get_the_ID() ) ); ?>" data-untrash-nonce="<?php echo esc_attr( wp_create_nonce( 'untrash-post_' . get_the_ID() ) ); ?>" data-id="<?php echo esc_attr( get_the_ID() ); ?>">
						<?php _e( 'Trash this post', 'friends' ); ?>
					</a>
					</li>
				<?php endif; ?>

				</li>
			</ul>
		</div>

	</div>
</header>
