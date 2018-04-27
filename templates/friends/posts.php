<?php include __DIR__ . '/../header.php'; ?>
    <h1>Friends</h1>
	<?php include __DIR__ . '/editor.php'; ?>
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<?php if ( 'friend_post' === get_post_type() ) : ?>
					<a href="<?php echo esc_url( get_the_author_url() ); ?>" class="author-avatar auth-link" data-token="<?php echo get_user_option( 'friends_token', get_the_author_ID() ); ?>">
						<img src="<?php echo esc_url( get_post_meta( get_the_ID(), 'gravatar', true ) ); ?>" width="36" height="36" class="avatar"/>
						<strong><?php echo esc_html( get_post_meta( get_the_ID(), 'author', true ) ); ?> @ <?php the_author() ?></strong>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( get_the_author_url() ); ?>" class="author-avatar>">
						<img src="<?php echo esc_url( get_avatar_url( get_the_author_ID() ) ); ?>" width="36" height="36" class="avatar"/>
						<strong><?php the_author() ?></strong>
					</a>
				<?php endif; ?>
				<span class="post-meta">
					<span class="post-date"><?php the_date(); ?></span>
					<span class="post-comment"> &nbsp;|&nbsp; <a href="<?php comments_link(); ?>" class="auth-link" data-token="<?php echo get_user_option( 'friends_token', get_the_author_ID() ); ?>"><?php comments_number(); ?></a></span>
				<?php if ( 'friend_post' !== get_post_type() ) : ?>
					<a href=""></a>
				<?php endif; ?>
				</span>

				<h4 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark"<?php if ( 'friend_post' === get_post_type() ) echo ' class="auth-link" data-token="' . get_user_option( 'friends_token', get_the_author_ID() ) . '"'; ?>><?php the_title(); ?></a></h4>
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

<script type="text/javascript">
	jQuery( document ).on( 'click', 'a.auth-link', function() {
		var $this = jQuery( this ), href = $this.attr( 'href' );
		if ( href.indexOf( 'friend_auth=' ) >= 0 ) return;
		var hash = href.indexOf( '#' );
		if ( hash >= 0 ) {
			hash = href.substr( hash );
			href = href.substr( 0, href.length - hash.length );
		} else {
			hash = '';
		}

		if ( href.indexOf( '?' ) >= 0 ) {
			href += '&';
		} else {
			href += '?';
		}
		href += 'friend_auth=' + $this.data( 'token' ) + hash;

		$this.attr( 'href', href );
	} );
</script>
<?php include __DIR__ . '/../footer.php'; ?>
