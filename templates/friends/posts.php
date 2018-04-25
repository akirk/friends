<?php include __DIR__ . '/../header.php'; ?>
    <h1>Posts by Your Friends</h1>
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<a href="<?php echo esc_url( get_the_author_url() ); ?>" class="author-avatar auth-link" data-token="<?php echo get_the_author_meta( 'friends_token' ); ?>">
					<img src="<?php echo esc_url( get_post_meta( get_the_ID(), 'gravatar', true ) ); ?>" width="48" height="48" class="avatar"/>
					<strong><?php echo esc_html( get_post_meta( get_the_ID(), 'author', true ) ); ?> at <?php the_author() ?></strong>
				</a>

				<span class="post-date"><?php the_date(); ?></span>
				<span class="post-comment"> &nbsp;|&nbsp; <a href="<?php comments_link(); ?>"><?php comments_number(); ?></a></span>

				<h4 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark" class="auth-link" data-token="<?php echo get_the_author_meta( 'friends_token' ); ?>"><?php the_title(); ?></a></h4>
			</header>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>

			<footer class="entry-meta">
			</footer>
		</article>
	<?php endwhile; ?>
<script type="text/javascript">
	jQuery( document ).on( 'click', 'a.auth-link', function() {
		var $this = jQuery( this );
		if ( $this.attr( 'href' ).indexOf( '?friend_auth=' ) >= 0 ) return;
		$this.attr( 'href', $this.attr( 'href' ) + '?friend_auth=' + $this.data( 'token' ) );
	} );
</script>
<?php include __DIR__ . '/../footer.php'; ?>
