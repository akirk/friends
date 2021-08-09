<?php
/**
 * This template contains the comments in the footer for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$args['friends']->frontend->link(
	get_comments_link(),
	get_comments_number( '', 1, '%' ),
	array(
		'class'          => 'comments btn ml-1',
		'dashicon_front' => 'admin-comments',
	)
);
