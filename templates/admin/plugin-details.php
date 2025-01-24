<html>
	<head><title><?php echo esc_html( $args['name'] ); ?></title></head>
	<body>
		<h1><?php echo esc_html( $args['name'] ); ?></h1>
		<p><?php echo esc_html( $args['short_description'] ); ?></p>
		<p>Author: <?php echo wp_kses( $args['author'], array( 'a' => array( 'href' => true ) ) ); ?></p>
		<?php foreach ( $args['sections'] as $_title => $section ) : ?>
			<h2><?php echo esc_html( $_title ); ?></h2>
			<?php echo wp_kses_post( $section ); ?>
		<?php endforeach; ?>
		<p>Website: <a href="<?php echo esc_url( $args['more_info'] ); ?>" target="_blank"><?php echo esc_html( $args['more_info'] ); ?></a></p>
	</body>
</html>
