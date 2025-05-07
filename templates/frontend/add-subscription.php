<?php
/**
 * This is the Add Subscription page
 *
 * @version 1.0
 * @package Friends
 */

$args['title'] = __( 'Add Subscription', 'friends' );
$args['no-bottom-margin'] = true;

Friends\Friends::template_loader()->get_template_part( 'frontend/header', null, $args );

?>
<section class="subscriptions">
	<div class="card">
		<div class="card-body">
			<form id="add-subscription-form" action="" method="post">
				<?php wp_nonce_field( 'friends_add_subscription' ); ?>
				<div class="form-group">
					<label for="subscription-url" class="form-label"><?php esc_html_e( 'URL', 'friends' ); ?></label>
					<input type="text" name="url" id="subscription-url" class="form-input" required>
				</div>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Preview', 'friends' ); ?></button>
			</form>
			<div id="preview-subscription">

			</div>



		</div>
	</div>

</section>
<?php
Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	null,
	$args
);
