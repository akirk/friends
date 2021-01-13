<?php
/**
 * The /friends/ header
 *
 * @package Friends
 */

$search = '';
if ( isset( $_GET['s'] ) ) {
	$search = $_GET['s'];
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php _e( 'Friends', 'friends' ); ?></title>
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'off-canvas off-canvas-sidebar-show' ); ?>>
	<div id="friends-sidebar" class="off-canvas-sidebar">
		<div class="friends-brand">
			<a class="friends-logo" href="<?php echo esc_attr( site_url( '/friends/' ) ); ?>"><h2><?php echo esc_html( 'Friends', 'friends' ); ?></h2></a>
		</div>
		<div class="friends-nav accordion-container">
			<?php dynamic_sidebar( 'friends-sidebar' ); ?>
		</div>
	</div>

	<a class="off-canvas-overlay" href="#close"></a>

	<div class="off-canvas-content">
		<header class="navbar">
			<section class="navbar-section">
			<a class="off-canvas-toggle btn btn-primary bt-action" href="#friends-sidebar">
				<span class="ab-icon dashicons dashicons-menu-alt2"></span>
			</a>

			</section>
			<section class="navbar-section">
				<form class="input-group input-inline">
					<input class="form-input" type="text" name="s" placeholder="<?php _e( 'Search' ); ?>" value="<?php echo esc_attr( $search ); ?>"/>
					<button class="btn btn-primary input-group-btn"><?php _e( 'Search' ); ?></button>
				</form>
			</section>
		</header>

