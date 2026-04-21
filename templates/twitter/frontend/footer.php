<?php
/**
 * Twitter theme: page footer.
 *
 * @package Friends
 */

?>
	</div><!-- .twitter-center-col -->

	<!-- Right column: search + trending -->
	<aside class="twitter-right-col" id="friends-sidebar">
		<a class="twitter-sidebar-close" href="#close" aria-label="<?php esc_attr_e( 'Close sidebar', 'friends' ); ?>">&times;</a>
		<form class="twitter-search-form form-autocomplete" action="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
			<div class="form-autocomplete-input twitter-search-wrap">
				<?php
				$_search = '';
				if ( isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$_search = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
				?>
				<input class="twitter-search-input master-search" type="text" name="s" placeholder="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Search' ); ?>" value="<?php echo esc_attr( $_search ); ?>" autocomplete="off" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-autocomplete' ) ); ?>" id="master-search" />
				<i class="form-icon"></i>
			</div>
			<ul class="menu" style="display: none"></ul>
		</form>
		<div class="twitter-right-widgets">
			<?php dynamic_sidebar( 'friends-sidebar-2' ); ?>
		</div>
		<div class="twitter-right-customize">
			<a href="<?php echo esc_url( add_query_arg( 'url', home_url( '/friends/' ), admin_url( 'customize.php?autofocus[section]=sidebar-widgets-friends-sidebar-2' ) ) ); ?>"><?php esc_html_e( 'customize sidebar', 'friends' ); ?></a>
		</div>
	</aside>

	<a class="twitter-overlay" href="#close"></a>

<?php wp_footer(); ?>
</body>
</html>
