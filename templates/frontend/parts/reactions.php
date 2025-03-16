<?php
/**
 * This template contains the reactions in the footer for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$reactions = Friends\Reactions::get_post_reactions();
foreach ( $reactions as $slug => $reaction ) {
	$classes = array();
	if ( $reaction->user_reacted ) {
		$classes[] = 'pressed';
	}
	echo '<button class="btn btn-link ml-1 friends-action friends-reaction ' . esc_attr( implode( ' ', $classes ) ) . '" data-id="' . esc_attr( get_the_ID() ) . '" data-emoji="' . esc_attr( $slug ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'friends-reaction' ) ) . '" title="' . esc_attr( $reaction->usernames ) . '"><span>';
	echo esc_html( $reaction->emoji );
	echo '</span> ' . esc_html( $reaction->count );
	echo '</button>' . PHP_EOL;
}

if ( ( in_array( get_post_type(), apply_filters( 'friends_frontend_post_types', array() ), true ) || count( $reactions ) || get_the_author_meta( 'ID' ) !== get_current_user_id() ) && ( Friends\Friends::is_main_user() || current_user_can( 'friend' ) || current_user_can( 'acquaintance' ) ) ) :
	?>
	<div class="friends-dropdown">
		<a class="btn btn-link ml-1 friends-action new-reaction friends-dropdown-toggle" tabindex="0" title="<?php echo esc_attr_x( 'Reaction', '+ Reaction', 'friends' ); ?>">
			<i class="dashicons dashicons-plus"></i> <span class="text"><?php echo esc_html_x( 'Reaction', '+ Reaction', 'friends' ); ?></span>
		</a>
		<?php Friends\Friends::template_loader()->get_template_part( 'frontend/reactions-picker', null, $args ); ?>
	</div>
	<?php
endif;
