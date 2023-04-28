<?php
/**
 * This is the main /friends/ template.
 *
 * @version 1.0
 * @package Friends
 */

$friends = Friends\Friends::get_instance();
$args = array(
	'friends' => $friends,
);
if ( isset( $_GET['in_reply_to'] ) && wp_parse_url( $_GET['in_reply_to'] ) ) {
	$args['in_reply_to'] = $friends->frontend->get_in_reply_to_metadata( $_GET['in_reply_to'] );
}

Friends\Friends::template_loader()->get_template_part(
	'frontend/header',
	null,
	$args
);

?>
<section class="posts columns translator-exclude">
	<?php
	if ( ! have_posts() ) {
		?>
		<div class="card">
			<div class="card-body">
			<?php
			if ( $friends->frontend->post_format ) {
				$post_formats = get_post_format_strings();

				if ( get_the_author() ) {
					echo esc_html(
						sprintf(
						// translators: %s is the name of an author.
							__( '%1$s hasn\'t posted anything with the post format %2$s yet!', 'friends' ),
							get_the_author(),
							$post_formats[ $friends->frontend->post_format ]
						)
					);

					$friend_user = new Friends\User( get_the_author_meta( 'ID' ) );
					?>
					<a href="<?php echo esc_url( $friend_user->get_local_friends_page_url() ); ?>"><?php esc_html_e( 'Remove post format filter', 'friends' ); ?></a>
					<?php
				} else {
					echo esc_html(
						sprintf(
						// translators: %s is a post format title.
							__( "Your friends haven't posted anything with the post format %s yet!", 'friends' ),
							$post_formats[ $friends->frontend->post_format ]
						)
					);
					?>
					<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>"><?php esc_html_e( 'Remove post format filter', 'friends' ); ?></a>
					<?php
				}
			} else {
				$any_friends = Friends\User_Query::all_associated_users();
				if ( $any_friends->get_total() > 0 ) {
					Friends\Friends::template_loader()->get_template_part( 'frontend/no-posts' );
				} else {
					Friends\Friends::template_loader()->get_template_part( 'frontend/no-friends' );
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
	null,
	$args
);
