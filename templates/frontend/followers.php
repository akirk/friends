<?php
/**
 * This is the Followers list
 *
 * @version 1.0
 * @package Friends
 */

Friends\Friends::template_loader()->get_template_part(
	'frontend/header',
	null,
	array_merge(
		$args,
		array(
			'title'            => __( 'Your Followers', 'friends' ),
			'no-bottom-margin' => true,
		)
	)
);

?>
<section class="followers">
	<?php
	if ( class_exists( '\ActivityPub\Collection\Followers' ) ) {
		$follower_data = \ActivityPub\Collection\Followers::get_followers_with_count( get_current_user_id() );
		$total = $follower_data['total'];
		$already_following = 0;
		foreach ( $follower_data['followers'] as $k => $follower ) {
			$data = $follower->to_array();

			$data['url'] = \ActivityPub\object_to_uri( $data['url'] );
			$data['server'] = wp_parse_url( $data['url'], PHP_URL_HOST );
			$data['css_class'] = '';

			$following = Friends\User_Feed::get_by_url( $data['url'] );
			if ( $following && ! is_wp_error( $following ) ) {
				++$already_following;
				$data['friend_user'] = $following->get_friend_user();
				$data['action_url'] = $following->get_friend_user()->get_local_friends_page_url();
				$data['url'] = $following->get_friend_user()->get_local_friends_page_url();
				$data['css_class'] = ' already-following';
			} else {
				$data['friend_user'] = false;
				$data['action_url'] = add_query_arg( 'url', $data['url'], admin_url( 'admin.php?page=add-friend' ) );
			}
			$follower_data['followers'][ $k ] = $data;
		}
		?>
		<p>
		<?php
		echo esc_html(
			sprintf(
				// translators: %s is the number of followers.
				_n( 'You have %s follower.', 'You have %s followers.', $total, 'friends' ),
				$total
			)
		);
		echo ' ';
		echo esc_html(
			sprintf(
				// translators: %s is the number of followers.
				_n( "You're following %s of them.", "You're following %s of them.", $already_following, 'friends' ),
				$already_following
			)
		);
		?>
		</p><ul>
		<?php
		foreach ( $follower_data['followers'] as $follower ) {
			?>
			<li>
				<details data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-preview' ) ); ?>" data-following="<?php echo esc_attr( $follower['following'] ); ?>" data-followers="<?php echo esc_attr( $follower['followers'] ); ?>"><summary><a href="<?php echo esc_url( $follower['url'] ); ?>" class="follower<?php echo esc_attr( $follower['css_class'] ); ?>">
					<img width="40" height="40" src="<?php echo esc_attr( $follower['icon']['url'] ); ?>" class="avatar activitypub-avatar" />
					<span class="activitypub-actor"><strong class="activitypub-name"><?php echo esc_html( $follower['name'] ); ?></strong> (<span class="activitypub-handle">@<?php echo esc_html( $follower['preferredUsername'] . '@' . $follower['server'] ); ?></span>)</span></a>
				<span class="since">since <?php echo esc_html( $follower['published'] ); ?></span>
				<span class="their-followers"></span>
				<span class="their-following"></span>
				&nbsp;&nbsp;
			<?php if ( $follower['friend_user'] ) : ?>
					<a href="<?php echo esc_url( $follower['action_url'] ); ?>" class="follower">
						<span class="ab-icon dashicons dashicons-businessperson" style="vertical-align: middle;"><span class="ab-icon dashicons dashicons-yes"></span></span>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( $follower['action_url'] ); ?>" class="follower">
						<span class="ab-icon dashicons dashicons-businessperson" style="vertical-align: middle;"><span class="ab-icon dashicons dashicons-plus"></span></span>
					</a>
				<?php endif; ?>
				<p class="description"><?php echo esc_html( $follower['summary'] ); ?></p>
			</summary><p class="loading-posts">
				<span><?php esc_html_e( 'Loading posts', 'friends' ); ?></span>
				<i class="form-icon loading"></i>
			</p></details>
			</li>
			<?php
		}
		?>
		</ul>
		<?php
	}
	?>
</section>
<?php
Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	null,
	$args
);
