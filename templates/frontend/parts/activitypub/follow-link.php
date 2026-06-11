<?php
/**
 * This template contains the activitypub follow link.
 *
 * @version 1.0
 * @package Friends
 */

$name_html = Friends\Feed_Parser_ActivityPub::replace_custom_emojis( $args['name'], $args['emojis'] ?? array() );

?><span class="boosted" title="<?php echo esc_attr( $args['name'] . ' (' . $args['handle'] . '): ' . $args['summary'] ); ?>">
	<?php
	echo wp_kses(
		sprintf(
			// translators: %s is a username.
			__( 'boosted %s', 'friends' ),
			'<a href="' . esc_url( add_query_arg( 'url', $args['url'], admin_url( 'admin.php?page=add-friend' ) ) ) . '" class="has-icon-left follow-button" title="' . esc_attr( $args['summary'] ) . '"><span class="dashicons dashicons-plus"></span>' . wp_kses(
				/* translators: %s is a username. */
				sprintf( __( 'Follow %s', 'friends' ), '<span class="name">' . $name_html . '</span>' ),
				array(
					'img'  => array(
						'alt'     => true,
						'class'   => true,
						'loading' => true,
						'src'     => true,
						'title'   => true,
					),
					'span' => array(
						'class' => true,
					),
				)
			) . '</a>' .
			'<a href="' . esc_url( $args['url'] ) . '" class="name">' . $name_html . '</a>'
		),
		array(
			'a'    => array(
				'href'  => true,
				'class' => true,
			),
			'img'  => array(
				'alt'     => true,
				'class'   => true,
				'loading' => true,
				'src'     => true,
				'title'   => true,
			),
			'span' => array(
				'class' => true,
			),

		)
	);
	?>
</span>
