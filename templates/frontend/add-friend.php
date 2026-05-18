<?php
/**
 * This is the Add Friend page
 *
 * @version 1.0
 * @package Friends
 */

$args['title'] = __( 'Add Friend', 'friends' );
$args['no-bottom-margin'] = true;

Friends\Friends::template_loader()->get_template_part( 'frontend/header', null, $args );

Friends\Friends::template_loader()->get_template_part( 'frontend/add-friend-form', null, $args );

Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	null,
	$args
);
