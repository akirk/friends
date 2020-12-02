<?php
/**
 * This template contains the content header part for an article on /friends/.
 *
 * @package Friends
 */

?><header class="entry-header">
	<div class="avatar">
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
	<div class="post-meta">
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
		<?php if ( $friends->post_types->is_cached_post_type( get_post_type() ) ) : ?>
			<?php
			$friends->frontend->link(
				get_the_permalink(),
				// translators: %s is a time span.
				sprintf( __( '%s ago' ), human_time_diff( get_the_time( 'U' ), time() ) ),
				array(
					'class' => 'post-date',
					'title' => get_the_time( 'r' ),
				)
			);
			?>
		<?php else : ?>
			<a href="<?php the_permalink(); ?>" title="<?php echo get_the_time( 'r' ); ?>"><?php /* translators: %s is a time span */ printf( __( '%s ago' ), human_time_diff( get_the_time( 'U' ), time() ) ); ?></a>
		<?php endif; ?>

		<?php edit_post_link(); ?>
	</div>
	<?php if ( false && $friends->post_types->is_cached_post_type( get_post_type() ) ) : ?>
		<button class="friends-trash-post" title="<?php esc_attr_e( 'Trash this post', 'friends' ); ?>" data-trash-nonce="<?php echo esc_attr( wp_create_nonce( 'trash-post_' . get_the_ID() ) ); ?>" data-untrash-nonce="<?php echo esc_attr( wp_create_nonce( 'untrash-post_' . get_the_ID() ) ); ?>" data-id="<?php echo esc_attr( get_the_ID() ); ?>">
			&#x1F5D1;
		</button>
	<?php endif; ?>
</header>
