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
			__( 'Welcome', 'friends' )              => 'friends',
			__( 'Settings', 'friends' )             => 'friends-settings',
			__( 'Notification Manager', 'friends' ) => 'friends-notification-manager',
		)
	);
}

if ( empty( $args['active'] ) ) {
	$args['active'] = false;
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

			foreach ( $args['menu'] as $label => $page ) {
				if ( ! $page ) {
					?>
					<span class="friends-tab">
					<?php echo esc_html( $label ); ?>
					</span>
					<?php
					continue;
				}
				$url = admin_url( 'admin.php?page=' . $page );
				if ( $page === $args['active'] ) {
					?>
					<a href="<?php echo esc_attr( $url ); ?>" class="friends-tab active" aria-current="true">
					<?php echo esc_html( $label ); ?>
					</a>
					<?php
				} else {
					?>
					<a href="<?php echo esc_attr( $url ); ?>" class="friends-tab">
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
	<div class="friends-body <?php echo esc_attr( $args['active'] ); ?>">
