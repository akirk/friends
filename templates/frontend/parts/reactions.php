<?php
/**
 * This template contains the reactions in the footer for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

foreach ( Friends\Reactions::get_post_reactions() as $slug => $reaction ) {
	$classes = array();
	if ( $reaction->user_reacted ) {
		$classes[] = 'pressed';
	}
	echo '<button class="btn ml-1 friends-action friends-reaction ' . esc_attr( implode( ' ', $classes ) ) . '" data-id="' . esc_attr( get_the_ID() ) . '" data-emoji="' . esc_attr( $slug ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'friends-reaction' ) ) . '" title="' . esc_attr( $reaction->usernames ) . '"><span>';
	echo esc_html( $reaction->emoji );
	echo '</span> ' . esc_html( $reaction->count );
	echo '</button>' . PHP_EOL;
}

if ( ( in_array( get_post_type(), Friends\Friends::get_frontend_post_types(), true ) || count( $reactions ) || get_the_author_meta( 'ID' ) !== get_current_user_id() ) && ( Friends\Friends::is_main_user() || current_user_can( 'friend' ) || current_user_can( 'acquaintance' ) ) ) :
	?>
	<div class="friends-dropdown">
		<a class="btn ml-1 friends-action new-reaction friends-dropdown-toggle" tabindex="0">
			<i class="dashicons dashicons-plus"></i> <?php echo esc_html( _x( 'Reaction', '+ Reaction', 'friends' ) ); ?>
		</a>
		<?php Friends\Friends::template_loader()->get_template_part( 'frontend/reactions-picker', null, $args ); ?>
	</div>
	<?php
endif;
