<?php
/**
 * This template contains the text for the Lost Follower notification e-mail.
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
printf( __( 'Sorry to inform you that you lost follower %s.', 'friends' ), $args['follower']->get_name() . ' (' . $args['follower']->get_preferred_username() . '@' . $args['server'] . ')' );
echo PHP_EOL;
echo PHP_EOL;
echo '> ' . wp_strip_all_tags( $args['follower']->get_summary() );
echo PHP_EOL;
// translators: %s is a time duration.
printf( __( 'They have been following you for: %s', 'friends' ), $args['duration'] );
