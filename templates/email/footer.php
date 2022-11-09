<?php
/**
 * This template contains the HTML footer for HTML e-mails.
 *
 * @version 1.0
 * @package Friends
 */

?>
</div>
<div class="footer">
	<?php
		// translators: %s is a site name.
		echo wp_kses( sprintf( __( 'This notification was sent by the Friends plugin on %s.', 'friends' ), '<a href="' . esc_attr( home_url() ) . '">' . get_option( 'blogname' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
	?>
</div>

</body>
</html>
