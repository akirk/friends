<?php
/**
 * This contains the admin reactions picker.
 *
 * @package Friends
 */

?>
<div id="friends-reaction-picker" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-reaction' ) ); ?>" style="display: none">
	<?php foreach ( Friends_Reactions::get_all_emojis() as $id => $data ) : ?>
		<button data-emoji="<?php echo esc_attr( $id ); ?>" title="<?php echo esc_attr( $data->name ); ?>"><?php echo esc_html( $data->char ); ?></button>
	<?php endforeach; ?>
</div>
