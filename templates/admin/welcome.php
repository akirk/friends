<?php
/**
 * This template contains the Friends Welcome Page.
 *
 * @package Friends
 */

?>

<h1><?php esc_html_e( 'Welcome to the Friends Plugin!', 'friends' ); ?></h1>
<p>
	<?php esc_html_e( 'With this plugin you can make your WordPress the center of your online activity.', 'friends' ); ?>
</p>
<p>
	<?php esc_html_e( 'By building up your network based on your own website, you can stay independent of external social networks.', 'friends' ); ?>
	<?php esc_html_e( 'This is how it works:', 'friends' ); ?>
</p>
<ul>
	<li>
		<?php
		// translators: %s is the URL of the user's friends page.
		echo wp_kses( sprintf( __( "Your <a href=%s>Friends page</a> is the place where you'll find the latest posts, messages, videos, etc. from your network.", 'friends' ), '"' . home_url( '/friends/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</li>
	<li>
		<?php
		// translators: %s is the URL of the user's friends page.
		echo wp_kses( sprintf( __( 'You can extend your network by <a href=%1$s>subscribing to web sites</a>, <a href=%1$s>sending friend requests</a> or <a href=%2$s>responding to received friend requests</a>.', 'friends' ), '"' . admin_url( 'admin.php?page=add-friend' ) . '"', '"' . admin_url( 'users.php?role=friend_request' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</li>
	<li>
		<?php esc_html_e( 'A lot of the functionality you might know from other networks is provided by this plugin, just without outside dependencies.', 'friends' ); ?>
		<?php
		// translators: %s is the URL of the user's friends page.
		echo wp_kses( sprintf( __( 'For example, when you take certain actions like subcribe to a new site, <a href=%1$s>automatic status posts</a> will be created but you decide when and if you want to publish them.', 'friends' ), '"' . admin_url( 'admin.php?page=automatic-status' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</li>
	<li>
		<?php
		// translators: %s is the URL of the user's friends page.
		echo wp_kses( sprintf( __( 'You can <a href=%s>install more plugins</a> that extend the Friends plugin with further functionality.', 'friends' ), '"' . admin_url( 'admin.php?page=friends-plugins' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</li>
</ul>
