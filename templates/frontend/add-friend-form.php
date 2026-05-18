<?php
/**
 * The Add Friend form.
 *
 * @version 1.0
 * @package Friends
 */

?>
<section class="subscriptions add-friend-subscriptions">
	<div class="card add-friend-card">
		<div class="card-body">
			<form id="add-subscription-form" action="" method="post">
				<?php wp_nonce_field( 'friends_add_subscription' ); ?>
				<div class="add-subscription-input">
					<textarea name="url" id="subscription-url" class="form-input" rows="4" placeholder="<?php esc_attr_e( 'Enter URLs, @user@instance handles, or paste OPML content', 'friends' ); ?>" required></textarea>
					<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Review', 'friends' ); ?></button>
				</div>
				<p class="opml-file-hint">
					<label for="opml-file"><?php esc_html_e( 'Or upload an OPML file:', 'friends' ); ?></label>
					<input type="file" id="opml-file" accept=".opml,.xml,application/xml,text/xml">
				</p>
			</form>
			<div id="preview-subscription"></div>
		</div>
	</div>

	<div class="card add-friend-discovery-card">
		<div class="card-body">
			<h5><?php esc_html_e( 'Discover People', 'friends' ); ?></h5>
			<p><?php esc_html_e( 'Browse the local timeline of a Mastodon server to find interesting people. Copy their profile URL and paste it in the field above to follow them.', 'friends' ); ?></p>
			<ul>
				<?php
				$instances = apply_filters(
					'friends_mastodon_instances',
					array(
						'mastodon.social',
						'mastodon.online',
						'fosstodon.org',
						'hachyderm.io',
						'infosec.exchange',
						'indieweb.social',
					)
				);
				foreach ( $instances as $instance ) :
					?>
					<li><a href="<?php echo esc_url( 'https://' . $instance . '/public/local' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $instance ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
