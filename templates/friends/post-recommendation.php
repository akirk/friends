<?php
/**
 * This is the recommend section for a post.
 *
 * @package Friends
 */

?>
<button class="btn friends-action friends-recommendation" data-id="<?php echo esc_attr( get_the_ID() ); ?>">
	<?php esc_html_e( 'Recommend', 'friends' ); ?>
</button>
