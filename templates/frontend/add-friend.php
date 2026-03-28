<?php
/**
 * This is the Add Friend page
 *
 * @version 1.0
 * @package Friends
 */

$args['title'] = __( 'Add Friend', 'friends' );
$args['no-bottom-margin'] = true;

Friends\Friends::template_loader()->get_template_part( 'frontend/header', null, $args );

?>
<section class="subscriptions">
	<div class="card">
		<div class="card-body">
			<form id="add-subscription-form" action="" method="post">
				<?php wp_nonce_field( 'friends_add_subscription' ); ?>
				<div class="input-group">
					<input type="text" name="url" id="subscription-url" class="form-input" placeholder="<?php esc_attr_e( 'Enter a URL or @user@instance', 'friends' ); ?>" required>
					<button type="submit" class="btn btn-primary input-group-btn"><?php esc_html_e( 'Follow', 'friends' ); ?></button>
				</div>
			</form>
			<div id="preview-subscription"></div>
		</div>
	</div>

	<div class="card">
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
<?php
Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	null,
	$args
);
