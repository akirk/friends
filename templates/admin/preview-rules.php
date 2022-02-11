<?php
/**
 * This template contains a preview of rules.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Render a preview row.
 *
 * @param      \WP_Post $post   The post.
 * @param      array    $args   The arguments.
 */
function preview_row( $post, $args ) {
	$modified_post = $args['feed']->apply_feed_rules( $post, null, $args['friend'] );
	?>
	<tr>
		<td class="title column-title column-primary" data-colname="<?php esc_attr_e( 'Title', 'friends' ); ?>"><a href="<?php the_permalink( $modified_post ? $modified_post : $post ); ?>" rel="noopener noreferrer"><?php echo esc_html( $modified_post ? $modified_post->post_title : $post->post_title ); ?></a></td>
		<td class="author column-author" data-colname="<?php esc_attr_e( 'Author' ); ?>"><?php echo esc_html( get_post_meta( $post->ID, 'author', true ) ); ?></td>
		<td class="date column-date" data-colname="<?php esc_attr_e( 'Date' ); ?>"><?php echo esc_html( date_i18n( __( 'F j, Y g:i a' ), strtotime( $post->post_date ) ) ); ?></td>
		<td class="action column-action" data-colname="<?php esc_attr_e( 'Action', 'friends' ); ?>">
			<?php
			if ( ! $modified_post || $modified_post->_feed_rule_delete ) {
				echo esc_html( _x( 'Delete', 'verb', 'friends' ) );
				?>
				</td><td class="view column-view" data-colname="<?php esc_attr_e( 'View', 'friends' ); ?>">
				<?php
			} else {
				if ( isset( $modified_post->_feed_rule_transform['post_status'] ) && 'trash' === $modified_post->_feed_rule_transform['post_status'] ) {
					echo esc_html( _x( 'Trash', 'verb' ) );
				} else {
					echo esc_html( _x( 'Accept', 'verb', 'friends' ) );
				}
				?>
				</td>
				<td class="view column-view" data-colname="<?php esc_attr_e( 'View', 'friends' ); ?>">
				<a href="<?php echo esc_url( $args['friend']->get_local_friends_page_url( $post->ID ) ); ?>?maybe-in-trash"><?php esc_html_e( 'View post', 'friends' ); ?></a>
				<?php
			}
			?>
			</td>
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
		<thead>
			<tr>
				<th class="column-primary column-title"><?php esc_html_e( 'Title', 'friends' ); ?></th>
				<th class="column-author"><?php esc_html_e( 'Author' ); ?></th>
				<th class="column-date"><?php esc_html_e( 'Date' ); ?></th>
				<th class="column-action"><?php esc_html_e( 'Action', 'friends' ); ?></th>
				<th class="column-view"><?php esc_html_e( 'Friends Page', 'friends' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php preview_row( $args['post'], $args ); ?>
			<tr>
				<td colspan="5"><?php echo esc_html( get_the_excerpt( $args['post'] ) ); ?></td>
			</tr>
		</tbody>
	</table>
<?php endif; ?>

<h2><?php esc_html_e( 'Last feed items' ); ?></h2>

<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-bottom: 2em; margin-right: 1em">
	<tbody>
		<tr>
			<th class="column-primary column-title"><?php esc_html_e( 'Title', 'friends' ); ?></th>
			<th class="column-author"><?php esc_html_e( 'Author' ); ?></th>
			<th class="column-date"><?php esc_html_e( 'Date' ); ?></th>
			<th class="column-action"><?php esc_html_e( 'Action', 'friends' ); ?></th>
			<th class="column-view"><?php esc_html_e( 'Friends Page', 'friends' ); ?></th>
		</tr>
		<?php
		while ( $args['friend_posts']->have_posts() ) {
			preview_row( $args['friend_posts']->next_post(), $args );
		}
		?>
	</tbody>
</table>
