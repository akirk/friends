<?php
/**
 * Displayed if user has no friends.
 *
 * @version 1.0
 * @package Friends
 */

Friends\Friends::template_loader()->get_template_part(
	'admin/welcome',
	$args['post_format'],
	array(
		'plugin-list' => false,
	)
);

?><p class="note">
	<span>
	<?php
	echo wp_kses( __( '<strong>Note:</strong> This box will go away as soon as you have added your first friend or subscription.', 'friends' ), array( 'strong' => array() ) );
	?>
	</span>
	<br>
	<span>
	<?php
	echo wp_kses(
		sprintf(
			// translators: %s is the URL of the Friends admin menu.
			__( 'It will remain available via the <a href=%s>Friends admin menu</a> for later reference.', 'friends' ),
			'"' . admin_url( 'admin.php?page=friends' ) . '"'
		),
		array(
			'a'      => array( 'href' => array() ),
			'br'     => array(),
			'strong' => array(),
		)
	);
	?>
	</span>
</p>
