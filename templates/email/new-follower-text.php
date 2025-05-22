<?php
/**
 * This template contains the text for the New Follower notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

// This is a text e-mail, not a place for HTML escaping.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

// translators: %s is a user display name.
printf( __( 'Hi %s!', 'friends' ), esc_html( $args['user']->display_name ) );
echo PHP_EOL;
echo PHP_EOL;
// translators: %s is a username.
printf( __( 'You have a new follower %s.', 'friends' ), $args['follower']->get_name() );
echo PHP_EOL;
echo PHP_EOL;
echo '> ' . wp_strip_all_tags( $args['follower']->get_summary() );
echo PHP_EOL;
// translators: %s is a URL.
printf( __( 'You can view their profile at %s', 'friends' ), esc_url( $args['url'] ) );
echo PHP_EOL;
echo PHP_EOL;
echo __( 'Maybe you want to follow them back?', 'friends' ), ' ', esc_url( add_query_arg( 'url', $args['url'], admin_url( 'admin.php?page=add-friend' ) ) );
echo PHP_EOL;
