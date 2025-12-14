<?php
/**
 * This template contains the comments in the footer for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$has_mention = get_post_meta( get_the_ID(), '_has_mention_in_comments', true );
$args['friends']->frontend->link(
	get_comments_link(),
	$has_mention ? __( 'Comments (You were mentioned)', 'friends' ) : __( 'Comments' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
	array(
		'class'          => 'comments btn btn-link ml-1 text',
		'dashicon_front' => $has_mention ? 'format-status' : 'admin-comments',
		'data-id'        => get_the_ID(),
		'data-cnonce'    => wp_create_nonce( 'comments-' . get_the_ID() ),
	)
);
