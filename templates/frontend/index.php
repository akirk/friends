<?php
/**
 * This is the main /friends/ template.
 *
 * @version 1.0
 * @package Friends
 */

$friends = Friends::get_instance();
Friends::template_loader()->get_template_part(
	'frontend/header',
	null,
	array(
		'friends' => $friends,
	)
);

?>
<section class="posts">
	<?php
	if ( ! have_posts() ) {
		?>
		<div class="card">
			<div class="card-body">
			<?php
			if ( $friends->frontend->post_format ) {
				$post_formats = get_post_format_strings();
				// translators: %s is a post format title.
				echo esc_html( sprintf( __( "Your friends haven't posted anything with the post format %s yet!", 'friends' ), $post_formats[ $friends->frontend->post_format ] ) );
			} else {
				$any_friends = Friend_User_Query::all_friends_subscriptions();
				if ( $any_friends->get_total() > 0 ) {
					Friends::template_loader()->get_template_part( 'frontend/no-posts' );
				} else {
					Friends::template_loader()->get_template_part( 'frontend/no-friends' );
				}
			}
			?>
			</div>
		</div>
		<?php
	} else {
		while ( have_posts() ) {
			the_post();

			Friends::template_loader()->get_template_part(
				'frontend/parts/content',
				get_post_format(),
				array(
					'friends'     => $friends,
					'friend_user' => new Friend_User( get_the_author_meta( 'ID' ) ),
					'avatar'      => get_post_meta( get_the_ID(), 'gravatar', true ),
				)
			);
		}

		the_posts_navigation();
	}
	?>
</section>
<?php
Friends::template_loader()->get_template_part(
	'frontend/footer',
	null,
	array(
		'friends' => $friends,
	)
);
