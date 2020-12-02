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

<body <?php body_class(); ?>>
	<div class="friends-topbar">
		<?php dynamic_sidebar( 'friends-topbar' ); ?>
	</div>
	<div class="container">

