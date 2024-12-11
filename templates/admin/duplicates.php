<?php
/**
 * Remove duplicates from the database
 *
 * @package Friends
 */

namespace Friends;

?>
<h2><?php esc_html_e( 'Duplicates', 'friends' ); ?></h2>
<form method="post">
	<p>
		<?php

		$duplicate_count = 0;
		foreach ( $args['friend_posts']->get_posts() as $_post ) {
			if ( ! isset( $args['uniques'][ $_post->ID ] ) ) {
				++$duplicate_count;
			}
		}

		echo esc_html(
			sprintf(
			// translators: %d is the number of duplicates.
				_n( '%d post was identified as aduplicate.', '%d posts were identified as duplicate.', $duplicate_count, 'friends' ),
				$duplicate_count
			)
		);
		echo ' ';
		esc_html_e( 'You can check or uncheck the posts before you click the button to delete them.', 'friends' );
		?>

	</p>
	<p>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Delete selected duplicates', 'friends' ); ?></button>
	</p>
<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-bottom: 2em; margin-right: 1em">
	<tbody>
		<tr>
			<th class="check-column"></th>
			<th class="column-primary column-title"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Title' ); ?></th>
			<th class="column-primary column-date"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Date' ); ?></th>
		</tr>
		<?php
		foreach ( $args['friend_posts']->get_posts() as $_post ) {
			$_title = get_the_title( $_post );
			if ( empty( $_title ) ) {
				$_title = wp_trim_words( get_the_excerpt( $_post ), 10 );
			}

			?>
	<tr>
		<td class="duplicate"><input type="checkbox" name="deleteduplicate[<?php echo esc_attr( $_post->ID ); ?>]" <?php checked( ! isset( $args['uniques'][ $_post->ID ] ) ); ?>></td>
		<td class="title column-title column-primary" data-colname="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Title' ); ?>">
			<?php
			// show the post format as a label.
			$post_format = get_post_format( $_post );
			if ( ! empty( $post_format ) ) {
				?>
				<span class="post-format-icon post-format-<?php echo esc_attr( $post_format ); ?>" title="<?php echo esc_attr( get_post_format_string( $post_format ) ); ?>"></span>
				<?php
			}
			?>
			<a href="<?php the_permalink( $_post ); ?>" rel="noopener noreferrer"><?php echo esc_html( $_title ); ?></a>
		</td>
		<td class="date column-date" data-colname="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Date' ); ?>"><?php echo esc_html( date_i18n( __( 'F j, Y g:i a' ), strtotime( $_post->post_date ) ) ); ?></td>
	</tr>
			<?php
		}
		?>
	</tbody>
</table>
<button type="submit" class="button button-primary"><?php esc_html_e( 'Delete selected duplicates', 'friends' ); ?></button>

</form>
