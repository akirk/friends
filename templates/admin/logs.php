<?php
/**
 * This template displays the Friends Log.
 *
 * @package Friends
 */

?>
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
				<td><?php echo wp_kses_post( $log->post_title ); ?></td>
				<td><?php echo esc_html( get_the_author_meta( 'display_name', $log->post_author ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
