<?php
/**
 * This template contains the content part for an article on /friends/.
 *
 * @package Friends
 */

?>
<div class="entry-content">
	<?php
	if ( $friends->post_types->is_cached_post_type( get_post_type() ) && $recommendation ) {
		$friend_name = $friends->frontend->get_link( get_the_author_meta( 'url' ), get_the_author() );
		?>
		<p class="friend-recommendation">
		<?php
		if ( is_string( $recommendation ) && '1' !== $recommendation ) {
			echo wp_kses(
				// translators: %1$s is the friend's name, %2$s is the message.
				sprintf( __( 'Your friend %1$s recommended this with the message: %2$s', 'friends' ), $friend_name, '<span>' . esc_html( $recommendation ) . '</span>' ),
				array(
					'a'    => array(
						'class'      => array(),
						'data-token' => array(),
						'href'       => array(),
					),
					'span' => array(),
				)
			);
		} else {
			echo wp_kses(
				// translators: %s is the friend's name.
				sprintf( __( 'Your friend %s recommended this.', 'friends' ), $friend_name ),
				array(
					'a' => array(
						'class'      => array(),
						'data-token' => array(),
						'href'       => array(),
					),
				)
			);
		}
		echo ' ', esc_html__( 'Be aware that this post might have been altered by your friend. Please verify with the original when in doubt.', 'friends' );
		?>
		</p>
		<?php
	}
	?>
	<?php the_content(); ?>
</div>
