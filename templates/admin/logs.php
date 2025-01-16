<?php
/**
 * This template displays the Friends Log.
 *
 * @package Friends
 */

?>
<style>
	del {
		background-color: #ffabaf;
		text-decoration: none;
	}

	ins {
		background-color: #68de7c;
		text-decoration: none;
	}
</style>
<table class="widefat">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Date', 'friends' ); ?></th>
			<th><?php esc_html_e( 'Message', 'friends' ); ?></th>
			<th><?php esc_html_e( 'User', 'friends' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $args['logs'] as $log ) :
			?>
			<tr>
				<td><?php echo esc_html( $log->post_date ); ?></td>
				<td>
					<details>
						<summary><?php echo wp_kses_post( $log->post_title ); ?></summary>
						<pre>
						<?php
						echo wp_kses(
							str_replace( array( '&lt;del&gt;', '&lt;/del&gt;', '&lt;ins&gt;', '&lt;/ins&gt;' ), array( '<del>', '</del>', '<ins>', '</ins>' ), esc_html( $log->post_content ) ),
							array(
								'ins' => array(),
								'del' => array(),
							)
						);
						?>
								</pre>
					</details>
				</td>
				<td><?php echo esc_html( get_the_author_meta( 'display_name', $log->post_author ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
