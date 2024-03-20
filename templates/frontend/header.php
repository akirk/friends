<?php
/**
 * The /friends/ header
 *
 * @version 1.0
 * @package Friends
 */

$search = '';
if ( isset( $_GET['s'] ) ) {
	$search = wp_unslash( $_GET['s'] );
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'off-canvas off-canvas-sidebar-show' ); ?>>
	<div id="friends-sidebar" class="off-canvas-sidebar">
		<div class="friends-brand">
			<a class="friends-logo" href="<?php echo esc_url( home_url( '/friends/' ) ); ?>"><h2><?php esc_html_e( 'Friends', 'friends' ); ?></h2></a>

			<a class="friends-sidebar-customize" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=sidebar-widgets-friends-sidebar&url=' . urlencode( home_url( '/friends/' ) ) ) ); ?>"><?php esc_html_e( 'customize sidebar', 'friends' ); ?></a>
		</div>
		<div class="friends-nav accordion-container">
			<?php dynamic_sidebar( 'friends-sidebar' ); ?>
		</div>
	</div>

	<a class="off-canvas-overlay" href="#close"></a>

	<div class="off-canvas-content">
		<header class="<?php echo is_single() ? '' : 'navbar'; ?>">
			<section class="navbar-section author">
			<a class="off-canvas-toggle btn btn-primary bt-action" href="#friends-sidebar">
				<span class="ab-icon dashicons dashicons-menu-alt2"></span>
			</a>
			<?php
			if ( $args['friend_user'] && $args['friend_user'] instanceof Friends\User && is_singular() ) {
				Friends\Friends::template_loader()->get_template_part(
					'frontend/single-header',
					$args['post_format'],
					$args
				);
			} elseif ( $args['friend_user'] && $args['friend_user'] instanceof Friends\User ) {
				Friends\Friends::template_loader()->get_template_part(
					'frontend/author-header',
					$args['post_format'],
					$args
				);
			} else {
				Friends\Friends::template_loader()->get_template_part(
					'frontend/main-feed-header',
					$args['post_format'],
					$args
				);
			}
			?>

			</section>

			<dialog class="search-dialog">
			</dialog>


			<section class="navbar-section search<?php echo esc_attr( is_singular() ? ' d-hide' : '' ); ?>">
				<form class="input-group input-inline form-autocomplete" action="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
					<div class="form-autocomplete-input form-input">
						<div class="has-icon-right">
							<input class="form-input" type="text" tabindex="2" name="s" placeholder="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Search or paste URL' ); ?>" value="<?php echo esc_attr( $search ); ?>" id="master-search" autocomplete="off"/>
							<i class="form-icon"></i>
						</div>
					</div>
					<ul class="menu" style="display: none">
					</ul>
					<button class="btn btn-primary input-group-btn"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Search' ); ?></button>
				</form>
			</section>
		</header>
	<?php
	do_action( 'friends_after_header', $args );
