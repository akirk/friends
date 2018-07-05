<?php
/**
 * This is the reaction section for posts.
 *
 * @package Friends
 */

foreach ( $reactions as $slug => $users ) {
	$classes = array();
	if ( isset( $users[ get_current_user_id() ] ) ) {
		$classes[] = 'pressed';
	}
	echo '<button class="reaction ' . implode( ' ', $classes ) . '" data-id="' . esc_attr( get_the_ID() ) . '" data-emoji="' . esc_attr( $slug ) . '"><span>';
	echo Friends_Reactions::get_emoji_html( $slug );
	echo '</span> ' . count( $users );
	echo '</button>';
}
?>
<button class="reaction new-reaction" data-id="<?php echo esc_attr( get_the_ID() ); ?>">
	<span>&#xf132;</span>
</button>
