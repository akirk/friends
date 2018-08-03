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
				// translators: %1$s is a URL, %2$s is a site name, %3$s is a URL.
				sprintf( __( 'Visit <a href=%1$s>%2$s</a>. Back to <a href=%3$s>your friends page</a>.', 'friends' ), '"' . esc_url( $friends->page->author->user_url ) . '" class="auth-link" data-token="' . esc_attr( get_user_option( 'friends_out_token', $friends->page->author->ID ) ) . '"', esc_html( $friends->page->author->user_login ), '"' . esc_attr( site_url( '/friends/' ) ) . '"' ), array(
					'a' => array(
						'href'       => array(),
						'class'      => array(),
						'data-token' => array(),
					),
				)
			);
			?>
			</p>
		<?php elseif ( ! dynamic_sidebar( 'Friends Topbar' ) ) : ?>
			<div class="friends-main-widget">
				<?php
				the_widget(
					'Friends_Widget_New_Private_Post', 'title=Friends', array(
						'before_widget' => '<div class="friends-widget">',
						'after_widget'  => '</div>',
						'before_title'  => '<h1>',
						'after_title'   => '</h1>',
					)
				);
				?>
			</div>
		<?php endif; ?>
	</div>
	<?php while ( have_posts() ) : ?>
		<?php
		the_post();
		$token  = get_user_option( 'friends_out_token', get_the_author_meta( 'ID' ) );
		$avatar = get_post_meta( get_the_ID(), 'gravatar', true );
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<div class="avatar">
					<?php if ( Friends::FRIEND_POST_CACHE === get_post_type() ) : ?>
						<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" target="_blank" rel="noopener noreferrer" class="author-avatar auth-link" data-token="<?php echo esc_attr( $token ); ?>">
							<img src="<?php echo esc_url( get_avatar_url( get_the_author_meta( 'ID' ) ) ); ?>" width="36" height="36" class="avatar"/>
						</a>
					<?php else : ?>
						<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" class="author-avatar">
							<img src="<?php echo esc_url( $avatar ? $avatar : get_avatar_url( get_the_author_meta( 'ID' ) ) ); ?>" width="36" height="36" class="avatar" />
						</a>
					<?php endif; ?>
				</div>
				<div class="post-meta">
					<div class="author">
						<?php if ( Friends::FRIEND_POST_CACHE === get_post_type() ) : ?>
							<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" target="_blank" rel="noopener noreferrer" class="auth-link" data-token="<?php echo esc_attr( $token ); ?>">
								<strong><?php echo esc_html( get_post_meta( get_the_ID(), 'author', true ) ); ?> @ <?php the_author(); ?></strong>
							</a>
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
					<a href="<?php the_permalink(); ?>" target="_blank" rel="noopener noreferrer" class="auth-link" data-token="<?php echo esc_attr( $token ); ?>"><?php the_title(); ?></a>
				<?php else : ?>
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				<?php endif; ?>
			</h4>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>

			<footer class="entry-meta">
				<button href="<?php comments_link(); ?>" target="_blank" rel="noopener noreferrer" class="comments auth-link" data-token="<?php echo esc_attr( $token ); ?>">
					<span class="dashicons dashicons-admin-comments"></span>
					<?php comments_number( '', 1, '%' ); ?>
				</button>
				<?php echo $friends->reactions->post_reactions(); ?>
			</footer>
		</article>
	<?php endwhile; ?>
</section>
<?php
include __DIR__ . '/footer.php';
