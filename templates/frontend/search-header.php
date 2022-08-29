<?php
/**
 * This template contains the author header on /friends/.
 *
 * @package Friends
 */

use Friends\User_Query;

?><header>
<div id="search-header" class="form-group">
	<h2 id="page-title"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Search' ); ?></h2>

	<form class="input-group form-autocomplete" action="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
		<div class="form-autocomplete-input form-input">
			<div class="has-icon-right">
				<input class="form-input" autofocus type="text" tabindex="2" name="s" placeholder="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Search' ); ?>" value="<?php echo esc_attr( $args['s'] ); ?>" autocomplete="off"/>
				<i class="form-icon"></i>
			</div>
		</div>
		<ul class="menu" style="display: none">
		</ul>
		<button class="btn btn-primary input-group-btn"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Search' ); ?></button>
	</form>
	<?php do_action( 'friends_search_header', $args ); ?>
</div>

<section class="search-friends">
	<h4><?php esc_html_e( 'Searching Friends...', 'friends' ); ?></h4>
	<div class="columns">
		<?php foreach ( User_Query::all_friends()->get_results() as $friend ) : ?>
		<div class="column col-4 col-xs-12"  id="friend-search-<?php echo esc_attr( $friend->ID ); ?>">
			<div class="card">
				<div class="card-header">
					<div class="card-title h5">via <?php $args['friends']->frontend->link( $friend->get_local_friends_page_url(), $friend->display_name ); ?></div>
				</div>
				<div class="card-body">
					<div class="loading"></div>
					<script>
						wp.ajax.send( 'friends-search-friend', {
							data: {
								_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'friends-search-friend-' . $friend->ID ) ); ?>',
								friend_id: <?php echo esc_js( $friend->ID ); ?>,
								s: "<?php echo esc_js( $args['s'] ); ?>",
							},
							error: function( response ) {
								jQuery( '#friend-search-<?php echo esc_attr( $friend->ID ); ?>' ).remove();
							},
							success: function( response ) {
								jQuery( '#friend-search-<?php echo esc_attr( $friend->ID ); ?> div.card-body' ).html( response );
							}
						} );

					</script>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>
</section>
</header>
