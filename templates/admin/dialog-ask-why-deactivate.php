<dialog id="friends-why-deactivate-dialog">
	<form action="">
		<h1><?php esc_html_e( 'Deactivating the Friends Plugin', 'friends' ); ?></h1>
		<label for="why-deactivate"><?php esc_html_e( 'Thank you for tell us why you are deactivating this plugin:', 'friends' ); ?></label>
		<ul>
			<li><label><input type="checkbox" name="reason[]" value="complicated"> <?php esc_html_e( 'Too complicated', 'friends' ); ?></label></li>
			<li><label><input type="checkbox" name="reason[]" value="ugly"> <?php esc_html_e( 'Too Ugly', 'friends' ); ?></label></li>
		</ul>
		<textarea id="why-deactivate" name="why-deactivate" placeholder="Something else"></textarea>
		<p>
			<?php esc_html_e( 'Your response will be submitted anonymously.', 'friends' ); ?>
			<?php
			echo wp_kses(
				sprintf(
					// translators: %s: URL to the GitHub issue tracker.
					__( 'If you encountered a bug, <a href="%s">please report it</a>!', 'friends' ),
					'https://github.com/akirk/friends'
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
		</p>
		<p>
			<button type="submit" class="button btn-primary"><?php esc_html_e( 'Deactivate', 'friends' ); ?></button>
			<button type="button" class="button btn-secondary"><?php esc_html_e( 'Cancel', 'friends' ); ?></button>
	</p>
	</form>
</dialog>
