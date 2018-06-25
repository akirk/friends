<?php
/**
 * This is the main /friends/ template.
 *
 * @package Friends
 */

?>
<?php include __DIR__ . '/header.php'; ?>
<section class="posts">
	<div class="friends-topbar">
		<?php if ( ! dynamic_sidebar( 'Friends Topbar' ) ) : ?>
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
				<button href="<?php comments_link(); ?>" target="_blank" rel="noopener noreferrer" class="auth-link" data-token="<?php echo esc_attr( $token ); ?>">
					<span class="dashicons dashicons-admin-comments"></span>
					<?php comments_number( '' ); ?>
				</button>
				<?php echo Friends_Reactions::post_reactions(); ?>
			</footer>
		</article>
	<?php endwhile; ?>
</section>
<?php
include __DIR__ . '/footer.php';
