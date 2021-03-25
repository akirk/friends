<?php
/**
 * The /friends/ header
 *
 * @version 1.0
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
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'off-canvas off-canvas-sidebar-show' ); ?>>
	<div id="friends-sidebar" class="off-canvas-sidebar">
		<div class="friends-brand">
			<a class="friends-logo" href="<?php echo esc_url( site_url( '/friends/' ) ); ?>"><h2><?php esc_html_e( 'Friends', 'friends' ); ?></h2></a>
		</div>
		<div class="friends-nav accordion-container">
			<?php dynamic_sidebar( 'friends-sidebar' ); ?>
		</div>
	</div>

	<a class="off-canvas-overlay" href="#close"></a>

	<div class="off-canvas-content">
		<header class="navbar">
			<section class="navbar-section author">
			<a class="off-canvas-toggle btn btn-primary bt-action" href="#friends-sidebar">
				<span class="ab-icon dashicons dashicons-menu-alt2"></span>
			</a>
			<?php
			if ( get_the_author() && ( is_author() || is_singular() ) ) {
				Friends::template_loader()->get_template_part(
					'frontend/author-header',
					null,
					array_merge(
						$args,
						array(
							'friend_user' => new Friend_User( get_the_author_meta( 'ID' ) ),
						)
					)
				);
			} else {
				Friends::template_loader()->get_template_part(
					'frontend/main-feed-header',
					null,
					$args
				);
			}
			?>

			</section>
			<section class="navbar-section">
				<form class="input-group input-inline form-autocomplete" action="<?php echo esc_url( site_url( '/friends/' ) ); ?>">
					<div class="form-autocomplete-input form-input">
						<div class="has-icon-right">
							<input class="form-input" type="text" tabindex="2" name="s" placeholder="<?php _e( 'Search' ); ?>" value="<?php echo esc_attr( $search ); ?>" id="master-search" autocomplete="off"/>
							<i class="form-icon"></i>
						</div>
					</div>
					<ul class="menu" style="display: none">
					</ul>
					<button class="btn btn-primary input-group-btn"><?php _e( 'Search' ); ?></button>
				</form>
			</section>
		</header>

