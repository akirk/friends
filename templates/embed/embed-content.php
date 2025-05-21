<?php
/**
 * Copy of the post embed content template part
 *
 * When a post is embedded in an iframe, this file is used to create the content template part
 * output if the active theme does not include an embed-content.php template.
 *
 * @package Friends
 */

?>
	<div class="wp-embed">

		<p class="wp-embed-heading">
			<a href="<?php the_permalink(); ?>" target="_top">
				<?php the_title(); ?>
			</a>
		</p>

		<div class="wp-embed-excerpt"><?php echo wp_kses_post( get_the_content( null, false, $args['post'] ) ); ?></div>

		<?php
		/**
		 * Prints additional content after the embed excerpt.
		 *
		 * @since 4.4.0
		 */
		do_action( 'embed_content' );
		?>

		<div class="wp-embed-footer">
			<div class="wp-embed-site-title">
				<a href="<?php the_permalink( $args['post'] ); ?>" target="_top">
					<img src="<?php echo esc_url( $avatar ? $avatar : get_avatar_url( get_the_author_meta( 'ID' ) ) ); /* phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage */ ?>" width="36" height="36" class="wp-embed-site-icon" />
					<span><?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?></span>
				</a>
			</div>

			<div class="wp-embed-meta">
				<?php
				/**
				 * Prints additional meta content in the embed template.
				 *
				 * @since 4.4.0
				 */
				do_action( 'embed_content_meta' );
				?>
			</div>
		</div>
	</div>
<?php
