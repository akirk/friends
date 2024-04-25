<div class="card column col-12">
	<div class="card-body message">
		<strong class="message"><?php echo wp_kses_post( $args['message'] ); ?></strong>
		<?php if ( $args['error'] ) : ?>
		<div class="error"><?php echo wp_kses_post( $args['error'] ); ?></div>
		<?php endif; ?>
	</div>
</div>
