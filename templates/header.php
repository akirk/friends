<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 * @version 1.0
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="http://gmpg.org/xfn/11">

<?php wp_head(); ?>
<style type="text/css">
  #content {
    padding: 2em;
    max-width: 66%;
  }
  article {
    margin-bottom: 4em;
    padding: 0;
  }
  article header h4 {
    margin-top: .5em;
    margin-bottom: 1em;
    padding: 0;
  }

  article header span.post-meta {
    font-size: 80%;
  }

  img.avatar {
    border-radius: 100%;
  }

</style>
</head>

<body <?php body_class(); ?>>
<div id="page" class="site">
  <div id="content" class="site-content">
