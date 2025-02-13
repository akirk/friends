<?php
/**
 * This template contains the Friends Settings.
 *
 * @package Friends
 */

do_action( 'friends_settings_before_form' );

?>
<form method="post">
	<?php wp_nonce_field( 'friends-settings' ); ?>
	<table class="form-table">
		<tbody>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<tr>
				<th scope="row"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Friendships' ); ?></th>
				<td>
					<fieldset>
						<label for="enable_wp_friendships">
							<input name="enable_wp_friendships" type="checkbox" id="enable_wp_friendships" value="1" <?php checked( '1', $args['enable_wp_friendships'] ); ?> />
							<span><?php esc_html_e( 'Enable friendships between WordPresses.', 'friends' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
					<?php
			endif;
			?>
			<tr>
				<th><?php esc_html_e( 'Retention', 'friends' ); ?></th>
				<td>
					<fieldset>
						<div>
							<input type="checkbox" name="friends_enable_retention_days" id="friends_enable_retention_days" value="1" <?php checked( '1', $args['retention_days_enabled'] ); ?> />
							<span id="friends_enable_retention_days_line" class="<?php echo esc_attr( $args['retention_days_enabled'] ? '' : 'disabled' ); ?>">
							<?php
							echo '<span>';
							echo wp_kses(
								sprintf(
									// translators: %s is an input field that allows specifying a number.
									__( 'Only keep posts for %s days.', 'friends' ),
									'<input type="number" min="1" id="friends_retention_days" name="friends_retention_days" value="' . esc_attr( $args['retention_days'] ) . '"' . ( $args['retention_days_enabled'] ? '' : ' disabled="disabled"' ) . ' size="3">'
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
							echo '</span> <span>';
							echo esc_html(
								sprintf(
								// translators: %s is a date.
									__( 'Earliest post: %s', 'friends' ),
									/* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ date_i18n( __( 'F j, Y' ), $args['earliest_post_date'] )
								)
							);
							echo '</span>';
							?>
							</span>
						</div>
						<div>
							<input type="checkbox" name="friends_enable_retention_number" id="friends_enable_retention_number" value="1" <?php checked( '1', $args['retention_number_enabled'] ); ?> />
							<span id="friends_enable_retention_number_line" class="<?php echo esc_attr( $args['retention_number_enabled'] ? '' : 'disabled' ); ?>">
							<?php
							echo '<span>';
							echo wp_kses(
								sprintf(
									// translators: %s is an input field that allows specifying a number.
									__( 'Only keep the last %s posts.', 'friends' ),
									'<input type="number" min="1" id="friends_retention_number" name="friends_retention_number" value="' . esc_attr( $args['retention_number'] ) . '"' . ( $args['retention_number_enabled'] ? '' : ' disabled="disabled"' ) . ' size="3">'
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
							echo '</span> <span>';
							echo esc_html(
								sprintf(
								// translators: %s is a date.
									__( 'Current number of posts: %s', 'friends' ),
									number_format_i18n( $args['post_count'] )
								)
							);
							echo '</span>';
							?>
							</span>
						</div>
						<div>
							<input type="checkbox" name="retention_delete_reacted" id="retention_delete_reacted" value="1" <?php checked( ! $args['retention_delete_reacted'] ); ?> />
							<label for="retention_delete_reacted"><?php esc_html_e( 'Protect posts from deletion that I have reacted on.', 'friends' ); ?></label>
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
						<span><?php esc_html_e( 'If you need to limit the amount of space, choose one of the options above (they can be combined).', 'friends' ); ?></span>
						<span>
						<?php
						esc_html_e( 'The next auto-delete will kick in when refreshing the feeds.', 'friends' );
						?>
						</span>
					</p>
					<p class="description">
						<?php
						esc_html_e( 'You can also specify this for individual friends.', 'friends' );
						?>
					</p>
				</td>
			</tr>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Post Formats', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="force_enable_post_formats">
							<input name="force_enable_post_formats" type="checkbox" id="force_enable_post_formats" value="1" <?php checked( '1', $args['force_enable_post_formats'] ); ?>>
							<span><?php esc_html_e( 'Always enable Post Formats, regardless of the theme support.', 'friends' ); ?></span>
							<p class="description">
								<?php
								echo wp_kses(
									__( 'With <a href="https://wordpress.org/support/article/post-formats/#supported-formats">Post Formats</a> you can categorize your content in a more detailed way. Examples for post formats are "photo" or "link."', 'friends' ),
									array(
										'a' => array(
											'href'   => array(),
											'rel'    => array(),
											'target' => array(),
										),
									)
								);
								?>
							</p>
						</label><br/>

						<label for="expose_post_format_feeds">
							<?php if ( current_theme_supports( 'post-format-feeds' ) ) : ?>
								<span><?php esc_html_e( 'Your theme already supports exposing Post Formats as alternate feeds on your homepage.', 'friends' ); ?></span>
							<?php else : ?>
							<input name="expose_post_format_feeds" type="checkbox" id="expose_post_format_feeds" value="1" <?php checked( '1', $args['expose_post_format_feeds'] ); ?>>
								<span>
								<?php
								// translators: %s is a HTML snippet.
								echo wp_kses( sprintf( __( 'Expose Post Formats as alternate feeds on your homepage (as %s).', 'friends' ), '<code>&lt;link rel="alternate"/ &gt;</code>' ), array( 'code' => array() ) );
								?>
								</span>
						<?php endif; ?>
					</fieldset>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Reactions', 'friends' ); ?></th>
				<td>
					<fieldset>
						<span><?php esc_html_e( 'Allow these emojis for reactions:', 'friends' ); ?></span>

						<ol id="available-emojis">
						<?php
						foreach ( Friends\Reactions::get_available_emojis() as $_id => $data ) {
							Friends\Friends::template_loader()->get_template_part(
								'admin/add-reaction-li',
								null,
								array(
									'id'    => $_id,
									'emoji' => $data->char,
								)
							);
						}
						?>
						</ol>
						<a href="" id="admin-add-emoji"><?php esc_html_e( 'Add an emoji', 'friends' ); ?></a>
						<?php Friends\Friends::template_loader()->get_template_part( 'admin/reactions-picker' ); ?>
						<?php if ( class_exists( 'Activitypub\Activitypub' ) ) : ?>
							<p class="description"><?php esc_html_e( 'This will always send a "Like" on ActivityPub but you can use different reactions to distinguish them for yourself. For example ⭐️ for bookmarks and ❤️ for likes.', 'friends' ); ?></p>
						<?php endif; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row" rowspan="3"><?php esc_html_e( 'Frontend', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="frontend-default-view">
							<span><?php esc_html_e( 'Default view:', 'friends' ); ?></span>
							<select name="frontend_default_view" id="frontend-default-view">
								<option value="expanded"<?php selected( $args['frontend_default_view'], 'expanded' ); ?>><?php esc_html_e( 'Expanded mode', 'friends' ); ?></option>
								<option value="collapsed"<?php selected( $args['frontend_default_view'], 'collapsed' ); ?>><?php esc_html_e( 'Compact mode', 'friends' ); ?></option>
							</select>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<td>
					<fieldset>
						<label for="frontend-theme">
							<span><?php esc_html_e( 'Main Theme:', 'friends' ); ?></span>
							<select name="friends_frontend_theme" id="frontend-theme">
								<?php foreach ( Friends\Frontend::get_themes() as $theme => $theme_name ) : ?>
									<option value="<?php echo esc_attr( $theme ); ?>"<?php selected( $args['frontend_theme'], $theme ); ?>><?php echo esc_html( $theme_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<details><summary>Post Type Themes</summary>
							<table>
							<?php foreach ( $args['post_format_strings'] as $post_type_slug => $post_type_title ) : ?>
								<tr>
									<td><label for="frontend-theme-<?php echo esc_attr( $post_type_slug ); ?>"><?php echo esc_html( $post_type_title ); ?></label></td>
									<td>
										<select name="friends_frontend_theme_<?php echo esc_attr( $post_type_slug ); ?>" id="frontend-theme-<?php echo esc_attr( $post_type_slug ); ?>">
											<option value=""><?php esc_html_e( 'Main Theme', 'friends' ); ?></option>
											<?php foreach ( Friends\Frontend::get_themes() as $theme => $theme_name ) : ?>
												<option value="<?php echo esc_attr( $theme ); ?>"<?php selected( $args[ 'frontend_theme_' . $post_type_slug ], $theme ); ?>><?php echo esc_html( $theme_name ); ?></option>
											<?php endforeach; ?>
											</select>
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
						</details>
					</fieldset>
					<p>
					<?php
					echo wp_kses_post(
						sprintf(
							// translators: %1$s is a link, %2$s is a link.
							__( 'Check out the <a href=%1$s>available themes</a>, or <a href=%2$s>write your own</a>.', 'friends' ),
							'"https://github.com/akirk/friends/blob/main/THEMES.md"',
							'"https://github.com/akirk/friends/wiki/Writing-Themes"'
						)
					);
					?>
						</p>
				</td>
			</tr>
			<?php if ( ! function_exists( 'classicpress_version' ) ) : ?>
			<tr>
				<td>
					<fieldset>
						<label for="blocks_everywhere">
							<input name="blocks_everywhere" type="checkbox" id="blocks_everywhere" value="1" <?php checked( '1', $args['blocks_everywhere'] ); ?> disabled="disabled" />
							<span><?php esc_html_e( 'Unfortunately, Gutenberg on the frontend is currently unavailable.', 'friends' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php do_action( 'friends_settings_form_bottom' ); ?>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
	<?php if ( ! current_user_can( 'manage_options' ) ) : ?>
		<p class="description">
			<?php esc_html_e( 'Administrators have access to these additional settings:', 'friends' ); ?>
			<ul class="ul-disc">
				<?php
				foreach ( array(
					__( 'Comments' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					__( 'Post Formats', 'friends' ),
				) as $setting ) :
					?>
					<li><?php echo esc_html( $setting ); ?></li>
				<?php endforeach; ?>
			</ul>
		</p>
	<?php endif; ?>
</form>
<?php
do_action( 'friends_settings_after_form' );
