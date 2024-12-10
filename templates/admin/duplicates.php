<?php
/**
 * Remove duplicates from the database
 *
 * @package Friends
 */

namespace Friends;

?>
<h2><?php esc_html_e( 'Last feed items', 'friends' ); ?></h2>

<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-bottom: 2em; margin-right: 1em">
	<tbody>
		<tr>
			<th class="column-action"><?php esc_html_e( 'Action', 'friends' ); ?></th>
			<th class="column-primary column-title"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Title' ); ?></th>
			<th class="column-primary column-date"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Date' ); ?></th>
		</tr>
		<?php
		foreach ( $args['friend_posts']->get_posts() as $_post ) {
			?>
	<tr>
		<td class="duplicate"><input type="checkbox" checked="<?php echo checked(true); ?>"></td>
		<td class="title column-title column-primary" data-colname="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Title' ); ?>"><a href="<?php the_permalink( $_post ); ?>" rel="noopener noreferrer"><?php the_title(); ?></a></td>
		<td class="date column-date" data-colname="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Date' ); ?>"><?php echo esc_html( date_i18n( __( 'F j, Y g:i a' ), strtotime( $_post->post_date ) ) ); ?></td>
	</tr>
	<?php
		}
		?>
	</tbody>
</table>
