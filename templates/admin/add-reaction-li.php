<?php
/**
 * This template contains the friend editor.
 *
 * @version 1.0
 * @package Friends
 */

if ( isset( $args['template'] ) && $args['template'] ) {
	$_id = '%1$s';
	$emoji = '%2$s';
	echo '<div id="available-emojis-template">';
} else {
	$_id = $args['id'];
	$emoji = $args['emoji'];
}
?>
<li id="emoji-<?php echo esc_attr( $_id ); ?>">
	<input type="hidden" name="available_emojis[]" value="<?php echo esc_attr( $_id ); ?>">
	<?php echo esc_html( $emoji ); ?>
	<small><a href="" class="delete-emoji"><?php esc_html_e( 'delete', 'friends' ); ?></a></small>
</li>
<?php

if ( isset( $args['template'] ) && $args['template'] ) {
	echo '</div>';
}
