<div class="columns">
	<div class="card column col-12">
		<div class="card-body message">
			<strong class="message"><?php echo wp_kses_post( $args['message'] ); ?></strong>
			<?php if ( $args['error'] ) : ?>
			<blockquote class="error"><?php echo wp_kses_post( $args['error'] ); ?></blockquote>
			<?php endif; ?>
		</div>
	</div>
</div>
