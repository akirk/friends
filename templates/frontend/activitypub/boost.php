<?php
/**
 * This template contains the form for boosting a URL from the frontend.
 *
 * @version 1.0
 * @package Friends
 */

?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="quick-post-panel" class="<?php echo esc_html( $args['form_class'] ); ?>">
	<?php wp_nonce_field( 'friends-reblog' ); ?>
	<input type="hidden" name="action" value="friends-reblog" />

	<label for="boost"><?php esc_html_e( 'Boost this URL:', 'friends' ); ?></label>
	<small>
	<?php
	echo wp_kses(
		sprintf(
			// translators: %s is a URL.
			__( 'You could also <a href=%s>reply to</a> this.', 'friends' ),
			'"' . ( ! empty( $args['boost'] ) ? ( '?in_reply_to=' . urlencode( $args['boost']['url'] ) ) : '' ) . '" class="reply-to-link"'
		),
		array(
			'a' => array(
				'href'  => array(),
				'class' => array(),
			),
		)
	);
	?>
	</small>
	<input class="form-input activitypub_preview_url" type="url" name="boost" placeholder="<?php esc_attr_e( 'Boost https://...', 'friends' ); ?>" value="<?php echo esc_attr( ! empty( $args['boost'] ) ? $args['boost']['url'] : '' ); ?>" autocomplete="off"/>
	<div class="activitypub_preview"><?php echo wp_kses_post( ! empty( $args['boost'] ) ? $args['boost']['html'] : '' ); ?></div>

	<div class="form-group">
		<button class="btn"><?php esc_html_e( 'Boost', 'friends' ); ?></button>
		<a href="#" class="quick-post-panel-toggle"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Cancel' ); ?></a>
	</div>
</form>
