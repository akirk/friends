<?php
/**
 * This is the main /friends/ template.
 *
 * @package Friends
 */

$friends = Friends::get_instance();
include __DIR__ . '/header.php'; ?>
<section class="posts">
	<div class="friends-topbar">
		<?php if ( $friends->page->author ) : ?>
			<h1>
			<?php echo esc_html( $friends->page->author->display_name ); ?>
			</h1>
			<p>
			<?php
			echo wp_kses(
				// translators: %1$s is a site name, %2$s is a URL.
				sprintf( __( 'Visit %1$s. Back to <a href=%2$s>your friends page</a>.', 'friends' ), '<a href="' . esc_url( $friends->page->author->user_url ) . '" class="auth-link" data-token="' . esc_attr( get_user_option( 'friends_out_token', $friends->page->author->ID ) ) . '">' . esc_html( $friends->page->author->display_name ) . '</a>', '"' . esc_attr( site_url( '/friends/' ) ) . '"' ), array(
					'a' => array(
						'href'       => array(),
						'class'      => array(),
						'data-token' => array(),
					),
				)
			);
			?>
			</p>
		<?php else : ?>
			<?php dynamic_sidebar( 'friends-topbar' ); ?>
		<?php endif; ?>
	</div>
	<?php while ( have_posts() ) : ?>
		<?php
		the_post();
		$token          = get_user_option( 'friends_out_token', get_the_author_meta( 'ID' ) );
		$avatar         = get_post_meta( get_the_ID(), 'gravatar', true );
		$recommendation = get_post_meta( get_the_ID(), 'recommendation', true );
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<div class="avatar">
					<?php if ( Friends::FRIEND_POST_CACHE === get_post_type() ) : ?>
						<?php if ( $recommendation ) : ?>
							<a href="<?php the_permalink(); ?>">
								<img src="<?php echo esc_url( $avatar ); ?>" width="36" height="36" class="avatar" />
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" target="_blank" rel="noopener noreferrer" class="author-avatar auth-link" data-token="<?php echo esc_attr( $token ); ?>">
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
						<?php if ( Friends::FRIEND_POST_CACHE === get_post_type() ) : ?>
							<?php if ( $recommendation ) : ?>
								<a href="<?php the_permalink(); ?>"><strong><?php echo esc_html( get_post_meta( get_the_ID(), 'author', true ) ); ?></strong></a>
							<?php else : ?>
								<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" target="_blank" rel="noopener noreferrer" class="auth-link" data-token="<?php echo esc_attr( $token ); ?>">
									<strong><?php the_author(); ?></strong>
								</a>
							<?php endif; ?>
						<?php else : ?>
							<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>">
								<strong><?php the_author(); ?></strong>
							</a>
						<?php endif; ?>
					</div>
					<span class="post-date"><?php /* translators: %s is a time span */ printf( _x( '%s ago', '%s = human-readable time difference', 'friends' ), human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) ); ?></span>
					<?php edit_post_link(); ?>
				</div>
			</header>

			<h4 class="entry-title">
				<?php if ( Friends::FRIEND_POST_CACHE === get_post_type() ) : ?>
					<?php if ( $recommendation ) : ?>
						<a href="<?php the_permalink(); ?>" target="_blank" rel="noopener noreferrer" class="auth-link" data-token="<?php echo esc_attr( $token ); ?>">
							<?php
							// translators: %s is a post title.
							echo esc_html( sprintf( __( 'Recommendation: %s', 'friends' ), get_the_title() ) );
							?>
						</a>
					<?php else : ?>
						<a href="<?php the_permalink(); ?>" target="_blank" rel="noopener noreferrer" class="auth-link" data-token="<?php echo esc_attr( $token ); ?>"><?php the_title(); ?></a>
					<?php endif; ?>
				<?php else : ?>
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				<?php endif; ?>
			</h4>

			<div class="entry-content">
				<?php
				if ( Friends::FRIEND_POST_CACHE === get_post_type() && $recommendation ) {
					$friend_name = '<a href="' . esc_url( get_the_author_meta( 'url' ) ) . '" class="auth-link" data-token="' . esc_attr( $token ) . '">' . esc_html( get_the_author() ) . '</a>';
					?>
					<p class="friend-recommendation">
					<?php
					echo wp_kses(
						// translators: %s is the friend's name.
						sprintf( __( 'Your friend %1$s recommended this with the message: %2$s', 'friends' ), $friend_name, '<span>' . esc_html( $recommendation ) . '</span>' ), array(
							'a' => array(
								'class'      => array(),
								'data-token' => array(),
								'href'       => array(),
							),
						)
					);
					?>
					</p>
					<?php
				}
				?>
				<?php the_content(); ?>
			</div>

			<footer class="entry-meta">
				<button href="<?php comments_link(); ?>" target="_blank" rel="noopener noreferrer" class="comments auth-link" data-token="<?php echo esc_attr( $token ); ?>">
					<span class="dashicons dashicons-admin-comments"></span>
					<?php comments_number( '', 1, '%' ); ?>
				</button>
				<?php echo $friends->reactions->post_reactions(); ?>
				<?php if ( Friends::FRIEND_POST_CACHE === get_post_type() ) : ?>
					<?php echo $friends->recommendation->post_recommendation(); ?>
				<?php endif; ?>
			</footer>
		</article>
	<?php endwhile; ?>
</section>
<?php
include __DIR__ . '/footer.php';
