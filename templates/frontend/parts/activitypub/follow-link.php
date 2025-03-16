<?php
/**
 * This template contains the activitypub follow link.
 *
 * @version 1.0
 * @package Friends
 */

?><span class="boosted" title="<?php echo esc_attr( $args['name'] . ' (' . $args['handle'] . '): ' . $args['summary'] ); ?>">
	<?php
	echo wp_kses(
		sprintf(
			// translators: %s is a username.
			__( 'boosted %s', 'friends' ),
			'<a href="' . esc_url( add_query_arg( 'url', $args['url'], admin_url( 'admin.php?page=add-friend' ) ) ) . '" class="has-icon-left follow-button" title="' . esc_attr( $args['summary'] ) . '"><span class="dashicons dashicons-plus"></span>' . wp_kses( /* translators: %s is a username. */ sprintf( __( 'Follow %s', 'friends' ), '<span class="name">' . esc_html( $args['name'] ) . '</span>' ), array( 'span' => array( 'class' => true ) ) ) . '</a>' .
			'<a href="' . esc_url( $args['url'] ) . '" class="name">' . esc_html( $args['name'] ) . '</a>'
		),
		array(
			'a'    => array(
				'href'  => true,
				'class' => true,
			),
			'span' => array(
				'class' => true,
			),

		)
	);
	?>
</span>
