<?php
/**
 * Displayed if user has no friends.
 *
 * @version 1.0
 * @package Friends
 */

Friends\Friends::template_loader()->get_template_part(
	'admin/welcome',
	null,
	array(
		'plugin-list' => false,
	)
);
