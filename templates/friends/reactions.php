<?php
/**
 * This is the reaction section for posts.
 *
 * @package Friends
 */

foreach ( $reactions as $slug => $reaction ) {
	$classes = array();
	if ( $reaction->user_reacted ) {
		$classes[] = 'pressed';
	}
	echo '<button class="friends-reaction ' . implode( ' ', $classes ) . '" data-id="' . esc_attr( get_the_ID() ) . '" data-emoji="' . esc_attr( $slug ) . '" title="' . esc_attr( $reaction->usernames ) . '"><span>';
	echo $reaction->html_entity;
	echo '</span> ' . $reaction->count;
	echo '</button>';
}
?>
<button class="friends-reaction new-reaction" data-id="<?php echo esc_attr( get_the_ID() ); ?>">
	<span>&#xf132;</span>
</button>
