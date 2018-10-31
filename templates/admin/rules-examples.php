<?php
/**
 * This template contains the rules examples.
 *
 * @package Friends
 */

?><h2><?php esc_html_e( 'Examples' ); ?></h2>
<p class="description"><?php esc_html_e( 'See how the rules apply to the last feed items:', 'friends' ); ?> <button id="refresh-preview-rules" data-id="<?php echo esc_attr( $friend->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'preview-rules-' . $friend->ID ) ); ?>"><?php esc_html_e( 'Refresh', 'friends' ); ?></button></p>
