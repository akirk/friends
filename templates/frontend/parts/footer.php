<?php
/**
 * This template contains the content footer part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

?><footer class="entry-meta card-footer">
	<?php if ( in_array( get_post_type(), apply_filters( 'friends_frontend_post_types', array() ), true ) ) : ?>
		<?php
		do_action( 'friends_post_footer_first' );
		Friends\Friends::template_loader()->get_template_part( 'frontend/parts/reactions', null, $args );
		Friends\Friends::template_loader()->get_template_part( 'frontend/parts/comments', null, $args );
		?>
		<div class="friends-dropdown friends-dropdown-right">
			<a class="btn btn-link friends-dropdown-toggle" tabindex="0">
				<i class="dashicons dashicons-menu-alt2"></i>
				<span class="text"><?php esc_html_e( 'Menu', 'friends' ); ?></span>
			</a>
			<ul class="menu" style="min-width: <?php echo esc_attr( intval( _x( '250', 'dropdown-menu-width', 'friends' ) ) ); ?>px">
				<?php
				Friends\Friends::template_loader()->get_template_part(
					'frontend/parts/header-menu',
					null,
					$args
				);
				?>
			</ul>
		</div>
		<?php
		do_action( 'friends_post_footer_last' );
		?>
	<?php endif; ?>

</footer>
<?php

Friends\Friends::template_loader()->get_template_part( 'frontend/parts/comments-content', null, $args );

?>
