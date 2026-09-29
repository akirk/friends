<?php
/**
 * This template contains the content part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$content = get_the_content();
$content_warning = get_post_meta( get_the_ID(), 'friends_content_warning', true );
?>
<div class="card-body">
	<?php
	if ( $content_warning ) :
		?>
		<details class="friends-content-warning">
			<summary>
				<span class="friends-content-warning-text"><?php echo esc_html( $content_warning ); ?></span>
				<span class="friends-content-warning-toggle"><?php esc_html_e( 'Show content', 'friends' ); ?></span>
			</summary>
			<div class="friends-content-warning-content">
		<?php
	endif;

	if ( empty( $content ) ) {
		the_title();
	} else {
		the_content();
	}

	Friends\Friends::template_loader()->get_template_part( 'frontend/parts/link-preview', get_post_format(), $args );

	if ( $content_warning ) :
		?>
			</div>
		</details>
		<?php
	endif;
	?>
</div>
