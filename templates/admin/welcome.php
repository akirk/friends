<?php
/**
 * This template contains the Friends Welcome Page.
 *
 * @package Friends
 */

$first_friend = array(
	// translators: %s is Alex Kirk.
	'display_name' => sprintf( __( 'Add %s, creator of the Friends plugin, as a friend now', 'friends' ), 'Alex Kirk' ),
	'url'          => 'https://alex.kirk.at/',
);

?>
<h1><?php esc_html_e( 'Welcome to the Friends Plugin!', 'friends' ); ?></h1>
<p>
	<?php esc_html_e( 'With this plugin you can make your WordPress the center of your online activity.', 'friends' ); ?>
</p>
<p>
	<span><?php esc_html_e( 'By building up your network based on your own website, you can stay independent of external social networks.', 'friends' ); ?></span>
	<span><?php esc_html_e( 'This is how it works:', 'friends' ); ?></span>
</p>
<ul>
	<li>
		<?php
		// translators: %s is the URL of the user's friends page.
		echo wp_kses( sprintf( __( "Your <a href=%s>Friends page</a> is the place where you'll find the latest posts, messages, videos, etc. from your network.", 'friends' ), '"' . home_url( '/friends/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</li>
	<li>
		<span>
		<?php
		// translators: %s is the URL of the user's friends page.
		echo wp_kses( sprintf( __( 'You can extend your network by <a href=%1$s>subscribing to web sites</a>, <a href=%1$s>sending friend requests</a> or <a href=%2$s>responding to received friend requests</a>.', 'friends' ), '"' . admin_url( 'admin.php?page=add-friend' ) . '"', '"' . admin_url( 'users.php?role=friend_request' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>

		</span>
		<form action="<?php echo esc_url( self_admin_url( 'admin.php?page=add-friend' ) ); ?>" method="post" class="form-horizontal">
		<?php wp_nonce_field( 'add-friend' ); ?>
			<input type="hidden" name="friend_url" value="<?php echo esc_attr( $first_friend['url'] ); ?>" />
		<div class="form-group">
			<button class="btn btn-link btn-arrow"><?php echo esc_html( $first_friend['display_name'] ); ?></button>
		</div>
	</form>
	</li>
	<li>
		<span><?php esc_html_e( 'A lot of the functionality you might know from other networks is provided by this plugin, just without outside dependencies.', 'friends' ); ?></span>
		<span>
		<?php
		// translators: %s is the URL of the user's friends page.
		echo wp_kses( sprintf( __( 'For example, when you take certain actions like subcribe to a new site, <a href=%1$s>automatic status posts</a> will be created but you decide when and if you want to publish them.', 'friends' ), '"' . admin_url( 'admin.php?page=automatic-status' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
		</span>
	</li>
	<li>
		<span>
		<?php
		// translators: %s is the URL of the user's friends page.
		echo wp_kses( sprintf( __( 'You can <a href=%s>install more plugins</a> that extend the Friends plugin with further functionality.', 'friends' ), '"' . admin_url( 'admin.php?page=friends-plugins' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
		</span>
		<?php if ( ! isset( $args['plugin-list'] ) || $args['plugin-list'] ) : ?>
		<ul>
			<li><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=activitypub&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal install-now" target="_blank"><?php \esc_html_e( 'ActivityPub Plugin', 'friends' ); ?></a>: <span><?php esc_html_e( 'Be part of the Fediverse!', 'friends' ); ?></span> <span><?php esc_html_e( 'People can follow your blog via ActivityPub (e.g. Mastodon) and you can follow people there.', 'friends' ); ?></span> </li>
			<li><a href="<?php echo \esc_url_raw( \admin_url( 'plugin-install.php?tab=plugin-information&plugin=enable-mastodon-apps&TB_iframe=true' ) ); ?>" class="thickbox open-plugin-details-modal install-now" target="_blank"><?php \esc_html_e( 'Enable Mastodon Apps Plugin', 'friends' ); ?></a>: <span><?php esc_html_e( 'Enjoy the comfort of Mastodon apps!', 'friends' ); ?></span> <span><?php esc_html_e( 'With this plugin you can use your favorite Mastodon app like Tusky, Ivory, or others to stay up to date and publish new status posts.', 'friends' ); ?></span> </li>
			<li><a href="<?php echo \esc_url_raw( \admin_url( 'admin.php?page=friends-plugins' ) ); ?>"><?php \esc_html_e( 'Post Collection Plugin', 'friends' ); ?></a>: <span><?php esc_html_e( 'Collect posts from around the web and create feeds.', 'friends' ); ?></span> </li>
			<li><a href="<?php echo \esc_url_raw( \admin_url( 'admin.php?page=friends-plugins' ) ); ?>"><?php \esc_html_e( 'Send to E-Reader Plugin', 'friends' ); ?></a>: <span><?php esc_html_e( 'Send new articles directly to your e-reader via e-mail or download the ePub.', 'friends' ); ?></span> </li>
		</ul>
		<?php endif; ?>
	</li>
</ul>
