<?php
/**
 * This template contains the Friends Settings Header.
 *
 * @package Friends
 */

if ( empty( $args['menu'] ) ) {
	$args['menu'] = apply_filters(
		'friends_admin_tabs',
		array(
			__( 'Welcome', 'friends' )       => 'friends',
			__( 'Settings' )                 => 'friends-settings', // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			__( 'Friendships', 'friends' )   => 'friends-wp-friendships',
			__( 'Notifications', 'friends' ) => 'friends-notification-manager',
			__( 'Import/Export', 'friends' ) => 'friends-import-export',
		)
	);
}

if ( empty( $args['active'] ) ) {
	$args['active'] = false;
}

if ( empty( $args['title'] ) ) {
	$args['title'] = __( 'Friends', 'friends' );
}

?>
<div class="wrap">
	<div class="friends-header">
		<div class="friends-title-section">
			<h1>
				<?php echo esc_html( $args['title'] ); ?>
			</h1>
		</div>
		<?php
		if ( ! empty( $args['menu'] ) ) {
			?>
			<nav class="friends-tabs-wrapper hide-if-no-js" aria-label="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Secondary menu' ); ?>">
			<?php

			foreach ( $args['menu'] as $label => $_page ) {
				if ( ! $_page ) {
					?>
					<span class="friends-tab">
					<?php echo esc_html( $label ); ?>
					</span>
					<?php
					continue;
				}
				if ( is_array( $_page ) ) {
					$query = $_page;
					$_page = $_page['page'];
				} else {
					$query = array(
						'page' => $_page,
					);
				}
				if ( $_page === $args['active'] ) {
					?>
					<span class="friends-tab active" aria-current="true">
					<?php echo esc_html( $label ); ?>
					</span>
					<?php
				} else {
					if ( filter_var( $query['page'], FILTER_VALIDATE_URL ) ) {
						$url = $query['page'];
					} else {
						$url = add_query_arg( $query, admin_url( 'admin.php' ) );
					}
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="friends-tab">
					<?php echo esc_html( $label ); ?>
					</a>
					<?php
				}
			}
			?>
		</nav>
			<?php
		}
		?>
	</div>
	<div class="wp-header-end"></div>
	<div class="friends-body <?php echo esc_attr( strtok( $args['active'], '&' ) ); ?>">
