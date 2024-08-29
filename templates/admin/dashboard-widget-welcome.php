<?php
/**
 * This template contains the Friends Dashboard Widget Welcome.
 *
 * @package Friends
 */

?>
<p><?php esc_html_e( "In this widget, you'd usually see your latest friends' posts. But since you haven't added any friends yet, these are your options:", 'friends' ); ?></p>
<ul>
	<li><a href="<?php echo esc_url( home_url( '/friends/?welcome' ) ); ?>"><span class="ab-icon dashicons dashicons-groups"></span> <?php esc_html_e( 'Check out your Friends page to learn more', 'friends' ); ?></a></li>
	<li><a href="<?php echo esc_url( self_admin_url( 'admin.php?page=add-friend' ) ); ?>"><span class="ab-icon dashicons dashicons-businessperson"></span> <?php esc_html_e( 'Add a new friend right away', 'friends' ); ?></a></li>
</ul>
