<?php
/**
 * This is the Followers list
 *
 * @version 1.0
 * @package Friends
 */

$args = array_merge( $friends_args, $args );
$blog_followers = class_exists( '\ActivityPub\Collection\Actors' ) && \ActivityPub\Collection\Actors::BLOG_USER_ID === $args['user_id'];
$args['title'] = __( 'Your Followers', 'friends' );
if ( $blog_followers ) {
	$args['title'] = __( 'Your Blog Followers', 'friends' );
}

$only_mutual = false;
if ( isset( $_GET['mutual'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$only_mutual = true;
	$friends_args['title'] = __( 'Your Mutual Followers', 'friends' );
	if ( $blog_followers ) {
		$args['title'] = __( 'Your Mutual Blog Followers', 'friends' );
	}
}

$args['no-bottom-margin'] = true;

Friends\Friends::template_loader()->get_template_part( 'frontend/header', null, $args );

$followers_list_page = 'users.php?page=activitypub-followers-list';
if ( $blog_followers ) {
	$followers_list_page = 'options-general.php?page=activitypub&tab=followers';
}
?>
<section class="followers">
	<?php
	if ( class_exists( '\ActivityPub\Collection\Followers' ) ) {
		$follower_data = \ActivityPub\Collection\Followers::get_followers_with_count( $args['user_id'] );
		$total = $follower_data['total'];
		$already_following = 0;
		foreach ( $follower_data['followers'] as $k => $follower ) {
			$data = $follower->to_array();

			$data['url'] = \ActivityPub\object_to_uri( $data['url'] );
			$data['server'] = wp_parse_url( $data['url'], PHP_URL_HOST );
			$data['css_class'] = '';

			$following = Friends\User_Feed::get_by_url( $data['url'] );
			if ( ! $following || is_wp_error( $following ) ) {
				$following = Friends\User_Feed::get_by_url( str_replace( '@', 'users/', $data['url'] ) );
			}
			if ( $following && ! is_wp_error( $following ) ) {
				++$already_following;
				$data['friend_user'] = $following->get_friend_user();
				$data['action_url'] = $following->get_friend_user()->get_local_friends_page_url();
				$data['url'] = $following->get_friend_user()->get_local_friends_page_url();
				if ( ! $only_mutual ) {
					$data['css_class'] = ' already-following';
				}
			} else {
				$data['friend_user'] = false;
				$data['action_url'] = add_query_arg( 'url', $data['url'], admin_url( 'admin.php?page=add-friend' ) );
			}
			$data['remove_action_url'] = add_query_arg( 's', $data['url'], admin_url( $followers_list_page ) );
			$follower_data['followers'][ $k ] = $data;
		}
		?>
		<p>
		<?php
		if ( $only_mutual ) {
			echo ' <a href="?">';
		}
		if ( $blog_followers ) {
			echo esc_html(
				sprintf(
					// translators: %s is the number of followers.
					_n( 'You have %s blog follower.', 'You have %s blog followers.', $total, 'friends' ),
					$total
				)
			);
		} else {
			echo esc_html(
				sprintf(
					// translators: %s is the number of followers.
					_n( 'You have %s follower.', 'You have %s followers.', $total, 'friends' ),
					$total
				)
			);
		}
		if ( $only_mutual ) {
			echo '</a> ';
			$not_yet_following = $total - $already_following;

			echo esc_html(
				sprintf(
					// translators: %s is the number of followers.
					_n( "You're not yet following %s of them.", "You're not yet following %s of them.", $not_yet_following, 'friends' ),
					$not_yet_following
				)
			);
		} else {
			echo ' <a href="?mutual">';
			echo esc_html(
				sprintf(
					// translators: %s is the number of followers.
					_n( "You're following %s of them.", "You're following %s of them.", $already_following, 'friends' ),
					$already_following
				)
			);
			echo '</a>';
		}


		echo ' <a href="' . esc_attr( admin_url( $followers_list_page ) ) . '">';
		if ( $blog_followers ) {
			esc_html_e( 'View all blog followers in wp-admin', 'friends' );
		} else {
			esc_html_e( 'View all followers in wp-admin', 'friends' );
		}
		echo '</a>';

		?>
		</p><ul>
		<?php
		foreach ( $follower_data['followers'] as $follower ) {
			if ( $only_mutual && ! $follower['friend_user'] ) {
				continue;
			}

			?>
			<li>
				<details data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-preview' ) ); ?>" data-following="<?php echo esc_attr( $follower['following'] ); ?>" data-followers="<?php echo esc_attr( $follower['followers'] ); ?>" data-id="<?php echo esc_attr( $follower['id'] ); ?>"><summary><a href="<?php echo esc_url( $follower['url'] ); ?>" class="follower<?php echo esc_attr( $follower['css_class'] ); ?>">
					<img width="40" height="40" src="<?php echo esc_attr( $follower['icon']['url'] ); ?>" class="avatar activitypub-avatar" />
					<span class="activitypub-actor"><strong class="activitypub-name"><?php echo esc_html( $follower['name'] ); ?></strong> (<span class="activitypub-handle">@<?php echo esc_html( $follower['preferredUsername'] . '@' . $follower['server'] ); ?></span>)</span></a>
				<span class="since">since <?php echo esc_html( $follower['published'] ); ?></span>
				<span class="their-followers"></span>
				<span class="their-following"></span>
				&nbsp;&nbsp;
			<?php if ( $follower['friend_user'] ) : ?>
					<span class="follower" title="<?php esc_attr_e( 'Already following', 'friends' ); ?>">
						<span class="ab-icon dashicons dashicons-businessperson" style="vertical-align: middle;"><span class="ab-icon dashicons dashicons-yes"></span></span>
					</span>
				<?php else : ?>
					<a href="<?php echo esc_url( $follower['action_url'] ); ?>" class="follower follower-add">
						<span class="ab-icon dashicons dashicons-businessperson" style="vertical-align: middle;"><span class="ab-icon dashicons dashicons-plus"></span></span>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $follower['remove_action_url'] ); ?>" class="follower follower-delete" title="<?php esc_attr_e( 'Remove follower', 'friends' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-followers' ) ); ?>" data-handle="<?php echo esc_attr( $follower['preferredUsername'] . '@' . $follower['server'] ); ?>" data-id="<?php echo esc_attr( $follower['id'] ); ?>">
					<span class="ab-icon dashicons dashicons-admin-users" style="vertical-align: middle;"><span class="ab-icon dashicons dashicons-no"></span></span>
				</a>
				<p class="description">
					<?php
					echo wp_kses(
						$follower['summary'],
						array(
							'a' => array(
								'href' => array(),
							),
						)
					);
					?>
				</p>
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
	} else {
		?>
		<p><?php esc_html_e( 'The follower list is currently dependent on the ActivityPub plugin.', 'friends' ); ?></p>
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
