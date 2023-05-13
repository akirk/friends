<?php
/**
 * This template contains the friend editor.
 *
 * @version 1.0
 * @package Friends
 */

$active_feeds = $args['friend']->get_active_feeds();
$feeds = $args['friend']->get_feeds();
$has_last_log = false;
?><form method="post">
	<?php wp_nonce_field( 'edit-friend-feeds-' . $args['friend']->user_login ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="url"><?php esc_html_e( 'Feeds', 'friends' ); ?></label></th>
				<td>
					<?php if ( empty( $active_feeds ) ) : ?>
						<?php esc_html_e( 'There are no active feeds.', 'friends' ); ?>
					<?php endif; ?>
					<table class="feed-table widefat<?php echo empty( $active_feeds ) ? ' hidden' : ''; ?>">
						<thead>
							<tr>
								<th class="manage-column column-cb check-column"><?php esc_html_e( 'Active', 'friends' ); ?></th>
								<th><?php esc_html_e( 'Feed URL', 'friends' ); ?></th>
								<th><?php esc_html_e( 'Parser', 'friends' ); ?></th>
								<th><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */  esc_html_e( 'Post Format' ); ?></th>
								<th><?php esc_html_e( 'Remarks', 'friends' ); ?></th>
								<?php do_action( 'friends_feed_table_header' ); ?>
								<th><?php esc_html_e( 'Actions', 'friends' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						$alternate = 0;
						foreach ( $feeds as $term_id => $feed ) :
							if ( $feed->get_last_log() ) {
								$has_last_log = true;
								$last_log = $feed->get_last_log();
							}
							?>
							<tr class="<?php echo esc_attr( ( ++$alternate % 2 ? 'alternate ' : ' ' ) . ( $feed->get_active() ? 'active' : 'inactive hidden' ) ); ?>">
								<th class="checkbox"><input type="checkbox" name="feeds[<?php echo esc_attr( $term_id ); ?>][active]" value="1" aria-label="<?php esc_attr_e( 'Feed is active', 'friends' ); ?>"<?php checked( $feed->get_active() ); ?> /></th>
								<td><input type="text" name="feeds[<?php echo esc_attr( $term_id ); ?>][url]" value="<?php echo esc_attr( $feed->get_url() ); ?>" size="30" aria-label="<?php esc_attr_e( 'Feed URL', 'friends' ); ?>" class="url" /></td>
								<td class="nowrap"><select name="feeds[<?php echo esc_attr( $term_id ); ?>][parser]" aria-label="<?php esc_attr_e( 'Parser', 'friends' ); ?>">
									<?php foreach ( $args['registered_parsers'] as $slug => $parser_name ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $slug, $feed->get_parser() ); ?>><?php echo esc_html( strip_tags( $parser_name ) ); ?></option>
									<?php endforeach; ?>
									<?php if ( 'unsupported' === $feed->get_parser() ) : ?>
										<option value="<?php echo esc_attr( $feed->get_parser() ); ?>" selected="selected">
											<?php
											// translators: %s is the name of a deleted parser.
											echo esc_html( $feed->get_parser() );
											?>
										</option>
									<?php elseif ( ! isset( $args['registered_parsers'][ $feed->get_parser() ] ) ) : ?>
										<option value="<?php echo esc_attr( $feed->get_parser() ); ?>" selected="selected">
											<?php
											// translators: %s is the name of a deleted parser.
											echo esc_html( sprintf( __( '%s (deleted)', 'friends' ), $feed->get_parser() ) );
											?>
										</option>
									<?php endif; ?>
								</select> <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=add-friend&parser=' . urlencode( $feed->get_parser() ) . '&feed=' . urlencode( $term_id ) . '&preview=' . urlencode( $feed->get_url() ) ) ), 'preview-feed' ) ); ?>" class="preview-parser" target="_blank" rel="noopener noreferrer"><?php esc_attr_e( 'Preview', 'friends' ); ?></a></td>
								<td><select name="feeds[<?php echo esc_attr( $term_id ); ?>][post-format]" aria-label="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Post Format' ); ?>">
									<?php foreach ( $args['post_formats'] as $format => $title ) : ?>
										<option value="<?php echo esc_attr( $format ); ?>"<?php selected( $format, $feed->get_post_format() ); ?>><?php echo esc_html( $title ); ?></option>
									<?php endforeach; ?>
								</select></td>
								<td><input type="text" name="feeds[<?php echo esc_attr( $term_id ); ?>][title]" value="<?php echo esc_attr( $feed->get_title() ); ?>" size="20" aria-label="<?php esc_attr_e( 'Feed Name', 'friends' ); ?>" /></td>
								<?php do_action( 'friends_feed_table_row', $feed, $term_id ); ?>
								<td><a href="#" class="delete-feed">Delete</a></td>
							</tr>
							<?php if ( $feed->get_last_log() ) : ?>
							<tr class="<?php echo esc_attr( ( $alternate % 2 ? 'alternate ' : ' ' ) . ( $feed->get_active() ? 'active' : 'inactive' ) ); ?> lastlog hidden">
								<td colspan="5" class="notice">
								<?php
								echo esc_html( $feed->get_last_log() );
								echo ' ';
								echo wp_kses_post(
									sprintf(
									// translators: %s is a date.
										__( 'Will be fetched again at %s.', 'friends' ),
										esc_html( $feed->get_next_poll() )
									)
								);
								?>
								</td>
							</tr>
							<?php endif; ?>
						<?php endforeach; ?>
						<tr class="template hidden">
							<td></td>
							<td><input type="url" name="feeds[new][url]" value="" size="20" aria-label="<?php esc_attr_e( 'Feed URL', 'friends' ); ?>" class="url" /></td>
							<td><select name="feeds[new][parser]" aria-label="<?php esc_attr_e( 'Parser', 'friends' ); ?>">
								<?php foreach ( $args['registered_parsers'] as $slug => $parser_name ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( strip_tags( $parser_name ) ); ?></option>
								<?php endforeach; ?>
							</select> <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=add-friend&parser=&preview=' ) ), 'preview-feed' ) ); ?>" class="preview-parser" target="_blank" rel="noopener noreferrer"><?php esc_attr_e( 'Preview', 'friends' ); ?></a></td>
							<td><select name="feeds[new][post-format]" aria-label="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Post Format' ); ?>">
								<?php foreach ( $args['post_formats'] as $format => $title ) : ?>
									<option value="<?php echo esc_attr( $format ); ?>"><?php echo esc_html( $title ); ?></option>
								<?php endforeach; ?>
							</select></td>
							<td><input type="text" name="feeds[new][title]" value="" size="20" aria-label="<?php esc_attr_e( 'Feed Name', 'friends' ); ?>" /></td>
							<?php do_action( 'friends_feed_table_row', $feed, 'new' ); ?>
							<td></td>
						</tr>
						</tbody>
					</table>
					<?php if ( count( $active_feeds ) !== count( $feeds ) ) : ?>
					<a href="" class="show-inactive-feeds"><?php esc_html_e( 'Show inactive feeds', 'friends' ); ?></a> |
					<?php endif; ?>
					<?php if ( $has_last_log ) : ?>
					<a href="" class="show-log-lines"><?php esc_html_e( 'Show log output', 'friends' ); ?></a> |
					<?php endif; ?>
					<a href="" class="add-feed"><?php esc_html_e( 'Add a feed', 'friends' ); ?></a>
				</td>
			</tr>
			<?php if ( $args['friend']->can_refresh_feeds() ) : ?>
			<tr>
				<th><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Posts' ); ?></th>
				<td>
					<fieldset>
						<label for="show_on_friends_page">
							<input name="show_on_friends_page" type="checkbox" id="show_on_friends_page" value="1" <?php checked( '1', ! in_array( $args['friend']->ID, $args['hide_from_friends_page'] ) ); ?>>
							<?php esc_html_e( 'Show posts on your friends page', 'friends' ); ?>
						</label>
					</fieldset>
					<fieldset>
					<a href="<?php echo esc_url( $args['friend']->get_local_friends_page_url() ); ?>">
						<?php
						// translators: %d is the number of posts.
						echo esc_html( sprintf( _n( 'View %d post', 'View %d posts', $args['post_count'], 'friends' ), $args['post_count'] ) );
						?>
					</a>
					<?php if ( apply_filters( 'friends_debug', false ) ) : ?>
						| <a href="<?php echo esc_url( self_admin_url( 'edit.php?post_type=' . Friends\Friends::CPT . '&author=' . $args['friend']->ID ) ); ?>">
							<?php
							// translators: %d is the number of posts.
							echo esc_html( sprintf( _n( 'View %d cached post', 'View %d cached posts', $args['post_count'], 'friends' ), $args['post_count'] ) );
							?>
						</a>

					<?php endif; ?>
					</fieldset>
					<p class="description">
					<?php
					// translators: %s is a URL.
					printf( __( '<a href=%s>Explicitly refresh</a> this feed now.', 'friends' ), esc_url( self_admin_url( 'admin.php?page=friends-refresh&user=' . $args['friend']->ID ) ) );
					?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Retention', 'friends' ); ?></th>
				<td>
					<fieldset>
						<div>
							<input type="checkbox" name="friends_enable_retention_days" id="friends_enable_retention_days" value="1" <?php checked( '1', $args['friend']->is_retention_days_enabled() ); ?> />
							<span id="friends_enable_retention_days_line" class="<?php echo esc_attr( $args['friend']->is_retention_days_enabled() ? '' : 'disabled' ); ?>">
							<?php
							echo wp_kses(
								sprintf(
									// translators: %s is an input field that allows specifying a number.
									__( 'Only keep posts for %s days', 'friends' ),
									'<input type="number" min="1" id="friends_retention_days" name="friends_retention_days" value="' . esc_attr( $args['friend']->get_retention_days() ) . '"' . ( $args['friend']->is_retention_days_enabled() ? '' : ' disabled="disabled"' ) . ' size="3">'
								),
								array(
									'input' => array(
										'type'     => array(),
										'min'      => array(),
										'id'       => array(),
										'name'     => array(),
										'value'    => array(),
										'size'     => array(),
										'disabled' => array(),
									),
								)
							);
							echo '. ';
							echo esc_html(
								sprintf(
								// translators: %s is a date.
									__( 'Earliest post: %s', 'friends' ),
									/* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ date_i18n( __( 'F j, Y' ), $args['earliest_post_date'] )
								)
							);
							if ( $args['global_retention_days_enabled'] ) {
								echo '. ';
								echo esc_html(
									sprintf(
									// translators: %s is a number of days.
										__( 'Global setting: %s days', 'friends' ),
										number_format_i18n( $args['global_retention_days'] )
									)
								);
							}

							?>
							</span>
						</div>
						<div>
							<input type="checkbox" name="friends_enable_retention_number" id="friends_enable_retention_number" value="1" <?php checked( '1', $args['friend']->is_retention_number_enabled() ); ?> />
							<span id="friends_enable_retention_number_line" class="<?php echo esc_attr( $args['friend']->is_retention_number_enabled() ? '' : 'disabled' ); ?>">
							<?php
							echo wp_kses(
								sprintf(
									// translators: %s is an input field that allows specifying a number.
									__( 'Only keep the last %s posts', 'friends' ),
									'<input type="number" min="1" id="friends_retention_number" name="friends_retention_number" value="' . esc_attr( $args['friend']->get_retention_number() ) . '"' . ( $args['friend']->is_retention_number_enabled() ? '' : ' disabled="disabled"' ) . ' size="3">'
								),
								array(
									'input' => array(
										'type'     => array(),
										'min'      => array(),
										'id'       => array(),
										'name'     => array(),
										'value'    => array(),
										'size'     => array(),
										'disabled' => array(),
									),
								)
							);
							if ( $args['global_retention_number_enabled'] ) {
								echo '. ';
								echo esc_html(
									sprintf(
										// translators: %s is a number.
										__( 'Global setting: %s posts', 'friends' ),
										number_format_i18n( $args['global_retention_number'] )
									)
								);
							}
							?>
							</span>
						</div>
					</fieldset>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								// translators: %s is a size in bytes or kilo bytes (kB).
								__( 'Currently the posts use %s of disk space.', 'friends' ),
								size_format( $args['total_size'], 1 )
							)
						);
						?>
					</p>
					<p class="description">
						<?php
						echo ' ';
						esc_html_e( 'If you need to limit the amount of space, choose one of the options above (they can be combined).', 'friends' );
						echo ' ';
						esc_html_e( 'The next auto-delete will kick in when refreshing the feeds of this friend.', 'friends' );
						?>
					</p>
					</p>
					<p class="description">
						<?php
						esc_html_e( 'Lower global settings have preceedence over the friend settings.', 'friends' );
						?>
					</p>
				</td>
			</tr>
			<?php endif; ?>
			<?php do_action( 'friends_edit_friend_table_end', $args['friend'] ); ?>
	</table>
	<?php do_action( 'friends_edit_friend_after_form', $args['friend'] ); ?>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
