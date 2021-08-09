<?php
/**
 * This contains the admin reactions picker.
 *
 * @package Friends
 */

Friends::template_loader()->get_template_part(
	'admin/add-reaction-li',
	null,
	array(
		'template' => true,
	)
);

?>
<div id="friends-reaction-picker" style="display: none">
	<?php foreach ( Friends_Reactions::get_all_emojis() as $id => $data ) : ?>
		<button data-emoji="<?php echo esc_attr( $id ); ?>" title="<?php echo esc_attr( $data->name ); ?>"><?php echo esc_html( $data->char ); ?></button>
	<?php endforeach; ?>
</div>
