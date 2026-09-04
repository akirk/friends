<?php
/**
 * This template contains the link preview card for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$link_preview = Friends\Link_Preview::get_for_post();
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
