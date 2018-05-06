<?php get_header(); ?>
	<h1><a href="<?php site_url( '/friends/' ); ?>">Friends</a></h1>
	<div class="friends-sidebar">
		<?php if ( ! dynamic_sidebar( 'Friends Sidebar' ) ) {
			the_widget( 'Friends_Widget_Refresh' );
			the_widget( 'Friends_Widget_Friend_List' );
			the_widget( 'Friends_Widget_Friend_Request' );
		} ?>
	</div>
	<?php if ( ! is_singular() ) : ?>
		<?php include __DIR__ . '/editor.php'; ?>
	<?php endif; ?>
	<?php while ( have_posts() ) : the_post(); ?>
		<?php $token = get_user_option( 'friends_out_token', get_the_author_meta( 'ID' ) ); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<?php if ( Friends::FRIEND_POST_CACHE === get_post_type() ) : ?>
					<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" class="author-avatar auth-link" data-token="<?php echo esc_attr( $token ); ?>">
						<img src="<?php echo esc_url( get_post_meta( get_the_ID(), 'gravatar', true ) ); ?>" width="36" height="36" class="avatar"/>
						<strong><?php echo esc_html( get_post_meta( get_the_ID(), 'author', true ) ); ?> @ <?php the_author() ?></strong>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( get_the_author_meta( 'url' ) ); ?>" class="author-avatar auth-link" data-token="<?php echo esc_attr( $token ); ?>"
						<img src="<?php echo esc_url( get_avatar_url( get_the_author_meta( 'ID' ) ) ); ?>" width="36" height="36" class="avatar"/>
						<strong><?php the_author() ?></strong>
					</a>
				<?php endif; ?>
				<span class="post-meta">
					<span class="post-date"><?php the_date(); ?></span>
					<span class="post-comment"> &nbsp;|&nbsp; <a href="<?php comments_link(); ?>" class="auth-link" data-token="<?php echo esc_attr( $token ); ?>"><?php comments_number(); ?></a></span>
				<?php if ( Friends::FRIEND_POST_CACHE !== get_post_type() ) : ?>
					<a href=""></a>
				<?php endif; ?>
				</span>

				<h4 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark"<?php if ( Friends::FRIEND_POST_CACHE === get_post_type() ) echo ' class="auth-link" data-token="' . esc_attr( $token ) . '"'; ?>><?php the_title(); ?></a></h4>
			</header>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>

			<footer class="entry-meta">
				 <?php edit_post_link(); ?>
			</footer>
		</article>
	<?php endwhile; ?>
	<?php the_posts_pagination(); ?>
<?php get_footer(); ?>
