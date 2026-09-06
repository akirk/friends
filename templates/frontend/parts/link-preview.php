<?php
/**
 * This template contains the link preview card for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

/**
 * Temporary diagnostics for link previews. The actual remote request runs in
 * a scheduled WordPress event, so expose the frontend state in the browser
 * console while testing in WordPress Playground.
 *
 * @var WP_Post|null $current_post
 */
$current_post    = get_post();
$checked         = $current_post ? intval( get_post_meta( $current_post->ID, Friends\Link_Preview::META_CHECKED, true ) ) : 0;
$is_supported    = $current_post ? Friends\Link_Preview::is_supported_post( $current_post ) : false;
$candidate_url   = $current_post ? Friends\Link_Preview::extract_url( $current_post ) : false;
$link_preview    = Friends\Link_Preview::get_for_post( $current_post );
$diagnostic_data = array(
	'postId'        => $current_post ? $current_post->ID : 0,
	'enabled'       => Friends\Link_Preview::is_enabled(),
	'supportedPost' => $is_supported,
	'candidateUrl'  => $candidate_url ? $candidate_url : null,
	'checkedAt'     => $checked ? gmdate( 'c', $checked ) : null,
	'cronScheduled' => $current_post ? (bool) wp_next_scheduled( Friends\Link_Preview::CRON_HOOK, array( $current_post->ID ) ) : false,
	'hasPreview'    => (bool) $link_preview,
);
?>
<script>
	console.log( '[Friends link preview]', <?php echo wp_json_encode( $diagnostic_data ); ?> );
</script>
<script type="application/json" class="friends-link-preview-state"><?php echo wp_json_encode( $diagnostic_data ); ?></script>
<?php
if ( ! $link_preview ) {
	return;
}

$link_preview_host = ! empty( $link_preview['site_name'] ) ? $link_preview['site_name'] : $link_preview['host'];
?>
<a class="friends-link-preview<?php echo empty( $link_preview['image'] ) ? ' no-image' : ''; ?>" href="<?php echo esc_url( $link_preview['url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow">
	<?php if ( ! empty( $link_preview['image'] ) ) : ?>
		<span class="friends-link-preview-image">
			<img src="<?php echo esc_url( $link_preview['image'] ); ?>" alt="" loading="lazy" decoding="async"
			<?php
			if ( ! empty( $link_preview['image_width'] ) && ! empty( $link_preview['image_height'] ) ) {
				echo ' width="' . esc_attr( $link_preview['image_width'] ) . '" height="' . esc_attr( $link_preview['image_height'] ) . '"';
			}
			?>
			/>
		</span>
	<?php endif; ?>
	<span class="friends-link-preview-text">
		<?php if ( $link_preview_host ) : ?>
			<span class="friends-link-preview-host"><?php echo esc_html( $link_preview_host ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $link_preview['title'] ) ) : ?>
			<strong class="friends-link-preview-title"><?php echo esc_html( $link_preview['title'] ); ?></strong>
		<?php endif; ?>
		<?php if ( ! empty( $link_preview['description'] ) ) : ?>
			<span class="friends-link-preview-description"><?php echo esc_html( $link_preview['description'] ); ?></span>
		<?php endif; ?>
	</span>
</a>
