<?php
/**
 * This template contains the friend editor.
 *
 * @package Friends
 */

?><table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-bottom: 2em; margin-right: 1em">
	<tbody>
		<tr>
			<th class="column-primary column-title"><?php _e( 'Title', 'friends' ); ?></th>
			<th class="column-author"><?php _e( 'Author' ); ?></th>
			<th class="column-date"><?php _e( 'Date' ); ?></th>
			<th class="column-action"><?php _e( 'Action', 'friends' ); ?></th>
		</tr>
		<?php while ( $friend_posts->have_posts() ) : ?>
			<?php $post = $friend_posts->next_post(); ?>
			<?php $modified_post = $feed->apply_feed_rules( $post, null, $friend ); ?>
			<tr>
				<td class="title column-title column-primary" data-colname="<?php esc_attr_e( 'Title', 'friends' ); ?>"><a href="<?php the_permalink( $modified_post ? $modified_post : $post ); ?>" rel="noopener noreferrer"><?php echo esc_html( $modified_post ? $modified_post->post_title : $post->post_title ); ?></a></td>
				<td class="author column-author" data-colname="<?php esc_attr_e( 'Author' ); ?>"><?php echo esc_html( $modified_post ? $modified_post->author : get_post_meta( get_the_ID( $post ), 'author', true ) ); ?></td>
				<td class="date column-date" data-colname="<?php esc_attr_e( 'Date' ); ?>"><?php echo date_i18n( __( 'F j, Y g:i a' ), strtotime( $post->post_date ) ); ?></td>
				<td class="action column-action" data-colname="<?php esc_attr_e( 'Action', 'friends' ); ?>">
					<?php
					if ( ! $modified_post || $modified_post->_feed_rule_delete ) {
						echo esc_html( _x( 'Delete', 'verb', 'friends' ) );
					} elseif ( isset( $post->_feed_rule_transform['post_status'] ) && 'trash' === $post->_feed_rule_transform['post_status'] ) {
						echo esc_html( _x( 'Trash', 'verb' ) );
					} else {
						echo esc_html( _x( 'Accept', 'verb', 'friends' ) );
					}
					?>
				</td>
			</tr>
		<?php endwhile; ?>
	</tbody>
</table>
