<?php
/**
 * This template contains the comments in the footer for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$args['friends']->frontend->link(
	get_comments_link(),
	get_comments_number(),
	array(
		'class'          => 'comments btn ml-1',
		'dashicon_front' => 'admin-comments',
		'data-id'        => get_the_ID(),
		'data-cnonce'    => wp_create_nonce( 'comments-' . get_the_ID() ),
	)
);
