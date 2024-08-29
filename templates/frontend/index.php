<?php
/**
 * This is the main /friends/ template.
 *
 * @version 1.0
 * @package Friends
 */

Friends\Friends::template_loader()->get_template_part(
	'frontend/header',
	$args['post_format'],
	$args
);

$show_welcome = isset( $args['show_welcome'] ) && $args['show_welcome'];

?>
<section class="posts columns <?php echo 'collapsed' === $args['frontend_default_view'] ? ' all-collapsed' : ''; ?>">
	<?php
	if ( $show_welcome || ! have_posts() ) {
		?>
		<div class="card columns col-12">
			<div class="card-body">
			<?php
			if ( ! $show_welcome && $args['friends']->frontend->post_format ) {
				$post_formats = get_post_format_strings();

				if ( $args['friend_user'] ) {
					echo esc_html(
						sprintf(
						// translators: %s is the name of an author.
							__( '%1$s hasn\'t posted anything with the post format %2$s yet!', 'friends' ),
							get_the_author(),
							$post_formats[ $args['friends']->frontend->post_format ]
						)
					);
					?>
					<a href="<?php echo esc_url( $args['friend_user']->get_local_friends_page_url() ); ?>"><?php esc_html_e( 'Remove post format filter', 'friends' ); ?></a>
					<?php
				} else {
					echo esc_html(
						sprintf(
						// translators: %s is a post format title.
							__( "Your friends haven't posted anything with the post format %s yet!", 'friends' ),
							$post_formats[ $args['friends']->frontend->post_format ]
						)
					);
					?>
					<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>"><?php esc_html_e( 'Remove post format filter', 'friends' ); ?></a>
					<?php
				}
			} else {
				$any_friends = Friends\User_Query::all_associated_users();
				if ( ! $show_welcome && $any_friends->get_total() > 0 ) {
					Friends\Friends::template_loader()->get_template_part( 'frontend/no-posts', $args['post_format'], $args );
				} else {
					Friends\Friends::template_loader()->get_template_part( 'frontend/no-friends', $args['post_format'], $args );
				}
			}
			?>
			</div>
		</div>
		<?php
	} else {
		Friends\Frontend::have_posts();
		the_posts_navigation();
	}
	?>
</section>
<?php
Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	$args['post_format'],
	$args
);
