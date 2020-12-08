<?php
/**
 * The /friends/ header
 *
 * @package Friends
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Friends</title>
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'off-canvas off-canvas-sidebar-show' ); ?>>
	<a class="off-canvas-toggle btn btn-primary btn-action" href="#sidebar-id">
		<i class="icon icon-menu"></i>
	</a>

	<div id="friends-sidebar" class="off-canvas-sidebar">
		<div class="friends-brand">
			<a class="friends-logo" href="<?php echo esc_attr( site_url( '/friends/' ) ); ?>"><h2><?php echo esc_html( 'Friends' ); ?></h2></a>
		</div>
		<div class="friends-nav accordion-container">
			<?php dynamic_sidebar( 'friends-sidebar' ); ?>
		</div>
	</div>
</div>

<a class="off-canvas-overlay" href="#close"></a>

<div class="off-canvas-content">
	<header class="navbar">
		<section class="navbar-section">
		</section>
		<section class="navbar-section">
			<div class="input-group input-inline">
				<input class="form-input" type="text" placeholder="search">
				<button class="btn btn-primary input-group-btn">Search</button>
			</div>
		</section>
	</header>

