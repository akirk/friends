<?php
/**
 * Mastodon theme: page footer.
 *
 * @package Friends
 */

?>
	</div><!-- .mastodon-center-col -->

	<!-- Right column: navigation sidebar -->
	<aside class="mastodon-right-col" id="friends-sidebar">
		<a class="mastodon-sidebar-close" href="#close" aria-label="<?php esc_attr_e( 'Close sidebar', 'friends' ); ?>">&times;</a>
		<div class="mastodon-brand">
			<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
				<h2><?php esc_html_e( 'Friends', 'friends' ); ?></h2>
			</a>
		</div>
		<form class="mastodon-sidebar-search form-autocomplete" action="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
			<div class="form-autocomplete-input mastodon-search-wrap">
					<?php
					$_sidebar_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					?>
				<input class="mastodon-search-input master-search" type="text" name="s" placeholder="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Search or paste URL' ); ?>" value="<?php echo esc_attr( $_sidebar_search ); ?>" autocomplete="off" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-autocomplete' ) ); ?>" />
				<i class="form-icon"></i>
			</div>
			<ul class="menu" style="display: none"></ul>
		</form>
		<nav class="mastodon-right-nav">
			<?php dynamic_sidebar( 'friends-sidebar' ); ?>
		</nav>
		<div class="mastodon-right-customize">
			<a href="<?php echo esc_url( add_query_arg( 'url', home_url( '/friends/' ), admin_url( 'customize.php?autofocus[section]=sidebar-widgets-friends-sidebar' ) ) ); ?>"><?php esc_html_e( 'customize sidebar', 'friends' ); ?></a>
		</div>
	</aside>

	<a class="mastodon-overlay" href="#close"></a>

<?php wp_footer(); ?>
</body>
</html>
