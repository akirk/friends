<?php
/**
 * Initial asynchronous feed loading state.
 *
 * @package Friends
 */

$fallback_url = add_query_arg( 'friends_sync', '1' );
?>
<div class="friends-feed-loading card columns col-12" aria-busy="true">
	<p role="status" aria-live="polite">
		<span class="loading" aria-hidden="true"></span>
		<span class="friends-feed-loading-text"><?php esc_html_e( 'Loading feed…', 'friends' ); ?></span>
	</p>
	<button type="button" class="btn friends-feed-retry" hidden><?php esc_html_e( 'Try again', 'friends' ); ?></button>
	<a class="friends-feed-fallback" href="<?php echo esc_url( $fallback_url ); ?>" hidden><?php esc_html_e( 'Load feed directly', 'friends' ); ?></a>
	<noscript><a href="<?php echo esc_url( $fallback_url ); ?>"><?php esc_html_e( 'Load feed', 'friends' ); ?></a></noscript>
</div>
