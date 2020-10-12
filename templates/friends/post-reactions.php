<?php
/**
 * This is the reaction section for a post.
 *
 * @package Friends
 */

foreach ( $reactions as $slug => $reaction ) {
	$classes = array();
	if ( $reaction->user_reacted ) {
		$classes[] = 'pressed';
	}
	echo '<button class="friends-action friends-reaction ' . implode( ' ', $classes ) . '" data-id="' . esc_attr( get_the_ID() ) . '" data-emoji="' . esc_attr( $slug ) . '"  data-nonce="' . esc_attr( wp_create_nonce( 'friends-reaction' ) ) . '" title="' . esc_attr( $reaction->usernames ) . '"><span>';
	echo $reaction->html_entity;
	echo '</span> ' . $reaction->count;
	echo '</button>';
}

if ( Friends::CPT === get_post_type() || count( $reactions ) || get_the_author_meta( 'ID' ) !== get_current_user_id() ) :
	?>
	<button class="friends-action new-reaction" data-id="<?php echo esc_attr( get_the_ID() ); ?>">
		<span>&#xf132;</span> <?php esc_html_e( 'Add reaction', 'friends' ); ?>
	</button>
	<?php
endif;
