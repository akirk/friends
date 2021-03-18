<?php
/**
 * This template contains a preview of rules.
 *
 * @package Friends
 */

/**
 * Render a preview row.
 *
 * @param      WP_Post $post   The post.
 * @param      array   $args   The arguments.
 */
function preview_row( $post, $args ) {
	$modified_post = $args['feed']->apply_feed_rules( $post, null, $args['friend'] );
	?>
	<tr>
		<td class="title column-title column-primary" data-colname="<?php esc_attr_e( 'Title', 'friends' ); ?>"><a href="<?php the_permalink( $modified_post ? $modified_post : $post ); ?>" rel="noopener noreferrer"><?php echo esc_html( $modified_post ? $modified_post->post_title : $post->post_title ); ?></a></td>
		<td class="author column-author" data-colname="<?php esc_attr_e( 'Author' ); ?>"><?php echo esc_html( $modified_post ? $modified_post->author : get_post_meta( get_the_ID( $post ), 'author', true ) ); ?></td>
		<td class="date column-date" data-colname="<?php esc_attr_e( 'Date' ); ?>"><?php echo date_i18n( __( 'F j, Y g:i a' ), strtotime( $post->post_date ) ); ?></td>
		<td class="action column-action" data-colname="<?php esc_attr_e( 'Action', 'friends' ); ?>">
			<?php
			if ( ! $modified_post || $modified_post->_feed_rule_delete ) {
				echo esc_html( _x( 'Delete', 'verb', 'friends' ) );
			} elseif ( isset( $modified_post->_feed_rule_transform['post_status'] ) && 'trash' === $modified_post->_feed_rule_transform['post_status'] ) {
				?>
				<a href="<?php echo esc_url( $args['friend']->get_local_friends_page_url( $post->ID ) ); ?>?in-trash"><?php echo esc_html( _x( 'Trash', 'verb' ) ); ?></a>
				<?php
			} else {
				?>
				<a href="<?php echo esc_url( $args['friend']->get_local_friends_page_url( $post->ID ) ); ?>"><?php echo esc_html( _x( 'Accept', 'verb', 'friends' ) ); ?></a>
				<?php
			}
			?>
		</td>
	</tr>
	<?php
}

if ( $args['post'] ) :
	?>
	<h2><?php esc_html_e( 'Selected post' ); ?></h2>

	<p>
		<?php
		esc_html_e( 'You chose muffle this post. To do this, use the rule section above to enter keywords that only match this or similar items. Remember to save your changes.', 'friends' );
		?>
	</p>
	<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-bottom: 2em; margin-right: 1em">
		<tbody>
			<tr>
				<th class="column-primary column-title"><?php _e( 'Title', 'friends' ); ?></th>
				<th class="column-author"><?php _e( 'Author' ); ?></th>
				<th class="column-date"><?php _e( 'Date' ); ?></th>
				<th class="column-action"><?php _e( 'Action', 'friends' ); ?></th>
			</tr>
			<?php preview_row( $args['post'], $args ); ?>
			<tr>
				<td colspan="4"><?php echo get_the_excerpt( $args['post'] ); ?></td>
			</tr>
		</tbody>
	</table>
<?php endif; ?>

<h2><?php esc_html_e( 'Last feed items' ); ?></h2>

<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-bottom: 2em; margin-right: 1em">
	<tbody>
		<tr>
			<th class="column-primary column-title"><?php _e( 'Title', 'friends' ); ?></th>
			<th class="column-author"><?php _e( 'Author' ); ?></th>
			<th class="column-date"><?php _e( 'Date' ); ?></th>
			<th class="column-action"><?php _e( 'Action', 'friends' ); ?></th>
		</tr>
		<?php
		while ( $args['friend_posts']->have_posts() ) {
			preview_row( $args['friend_posts']->next_post(), $args );
		}
		?>
	</tbody>
</table>
