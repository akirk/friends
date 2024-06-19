<?php
/**
 * This contains the frontend reactions picker.
 *
 * @package Friends
 */

?>
<div class="friends-reaction-picker menu" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-reaction' ) ); ?>" data-id="<?php echo esc_attr( get_the_ID() ); ?>">
	<?php foreach ( Friends\Reactions::get_available_emojis() as $_id => $data ) : ?>
		<button class="btn btn-link" data-emoji="<?php echo esc_attr( $_id ); ?>" title="<?php echo esc_attr( $data->name ); ?>"><?php echo esc_html( $data->char ); ?></button>
	<?php endforeach; ?>
</div>
