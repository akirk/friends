<?php
/**
 * This template contains the HTML footer for HTML e-mails.
 *
 * @version 1.0
 * @package Friends
 */

// This is a text e-mail, not a place for HTML escaping.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

echo PHP_EOL . PHP_EOL;

// translators: %1$s is a site name, %2$s is a site url.
printf( __( 'This notification was sent by the Friends plugin on %1$s at %2$s.', 'friends' ), get_option( 'blogname' ), home_url() );
