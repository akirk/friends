<?php
/**
 * Twitter theme: page header with 3-column layout.
 *
 * @package Friends
 */

$_search = '';
if ( isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$_search = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

add_filter(
	'document_title_parts',
	function ( $title ) use ( $args ) {
		if ( isset( $args['title'] ) ) {
			$title['title'] = $args['title'];
		}
		return $title;
	}
);

$twitter_current_user = wp_get_current_user();

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="light dark">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

	<!-- Left column: navigation -->
	<aside class="twitter-left-col">
		<div class="twitter-logo">
			<a href="<?php echo esc_url( home_url( '/friends/' ) ); ?>"><?php esc_html_e( 'Friends', 'friends' ); ?></a>
		</div>
		<nav class="twitter-left-nav">
			<?php dynamic_sidebar( 'friends-sidebar' ); ?>
		</nav>
		<div class="twitter-left-customize">
			<a href="<?php echo esc_url( add_query_arg( 'url', home_url( '/friends/' ), admin_url( 'customize.php?autofocus[section]=sidebar-widgets-friends-sidebar' ) ) ); ?>"><?php esc_html_e( 'customize sidebar', 'friends' ); ?></a>
		</div>
		<a class="twitter-user-card" href="<?php echo esc_url( home_url( '/friends/' ) ); ?>"><?php echo get_avatar( $twitter_current_user->ID, 40, '', '', array( 'class' => 'twitter-avatar' ) ); ?><div class="twitter-user-info">
				<strong class="twitter-display-name"><?php echo esc_html( $twitter_current_user->display_name ); ?></strong>
				<span class="twitter-handle">@<?php echo esc_html( $twitter_current_user->user_login ); ?></span>
			</div></a>
	</aside>

	<!-- Center column: timeline -->
	<div class="twitter-center-col">
		<details class="twitter-mobile-panel">
			<summary class="twitter-mobile-panel-toggle">
				<?php echo get_avatar( $twitter_current_user->ID, 32, '', '', array( 'class' => 'twitter-avatar' ) ); ?>
				<span><?php echo esc_html( $twitter_current_user->display_name ); ?></span>
				<i class="dashicons dashicons-arrow-down-alt2"></i>
			</summary>
			<div class="twitter-mobile-panel-content">
				<form class="twitter-search-form form-autocomplete" action="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
					<div class="form-autocomplete-input twitter-search-wrap">
						<input class="twitter-search-input master-search" type="text" name="s" placeholder="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Search or paste URL' ); ?>" value="<?php echo esc_attr( $_search ); ?>" autocomplete="off" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-autocomplete' ) ); ?>" />
						<i class="form-icon"></i>
					</div>
					<ul class="menu" style="display: none"></ul>
				</form>
			</div>
		</details>
		<div class="twitter-col-header<?php echo is_single() ? ' is-single' : ''; ?>">
			<div class="twitter-col-title-row">
				<?php
				if ( $args['friend_user'] && $args['friend_user'] instanceof Friends\User && is_singular() ) {
					Friends\Friends::template_loader()->get_template_part(
						'frontend/single-header',
						$args['post_format'],
						$args
					);
				} elseif ( $args['friend_user'] && $args['friend_user'] instanceof Friends\User ) {
					echo '<h2 id="page-title"><a href="' . esc_url( $args['friend_user']->get_local_friends_page_url() ) . '">';
					if ( $args['friend_user']->get_avatar_url() ) {
						echo '<img src="' . esc_url( $args['friend_user']->get_avatar_url() ) . '" width="24" height="24" class="twitter-title-avatar" /> ';
					}
					echo esc_html( $args['friend_user']->display_name );
					echo '</a></h2>';
				} elseif ( isset( $args['title'] ) ) {
					echo '<h2 id="page-title"><a href="' . esc_url( home_url( '/friends/' ) ) . '">' . esc_html( $args['title'] ) . '</a></h2>';
				} else {
					$_title = __( 'Home', 'friends' );
					echo '<h2 id="page-title"><a href="' . esc_url( home_url( '/friends/' ) ) . '">' . esc_html( $_title ) . '</a></h2>';
				}
				?>
				<a class="off-canvas-toggle" href="#friends-sidebar" aria-label="<?php esc_attr_e( 'Open sidebar', 'friends' ); ?>"><i class="dashicons dashicons-menu"></i></a>
			</div>
			<?php if ( ! is_single() && empty( $args['no-bottom-margin'] ) ) : ?>
			<div class="twitter-chips-area">
				<?php
				if ( $args['friend_user'] && $args['friend_user'] instanceof Friends\User ) {
					Friends\Friends::template_loader()->get_template_part(
						'frontend/author-header',
						$args['post_format'],
						$args
					);
				} else {
					Friends\Friends::template_loader()->get_template_part(
						'frontend/main-feed-header',
						$args['post_format'],
						$args
					);
				}
				?>
			</div>
			<?php endif; ?>
		</div>

		<!-- Compose in center column -->
		<div class="twitter-compose-inline">
			<div class="twitter-compose-avatar">
				<?php echo get_avatar( $twitter_current_user->ID, 40, '', '', array( 'class' => 'twitter-avatar' ) ); ?>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" class="twitter-compose-form friends-post-inline">
				<?php wp_nonce_field( 'friends_publish' ); ?>
				<input type="hidden" name="action" value="friends_publish" />
				<input type="hidden" name="format" value="<?php echo esc_attr( get_option( 'friends_compose_post_format', 'status' ) ); ?>" />
				<textarea name="content" rows="2" placeholder="<?php esc_attr_e( "What's happening?", 'friends' ); ?>"></textarea>
				<div class="twitter-compose-footer">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=friends-settings#compose' ) ); ?>" class="twitter-compose-settings" title="<?php esc_attr_e( 'Compose settings', 'friends' ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
					<button type="submit" class="twitter-compose-submit"><?php esc_html_e( 'Post', 'friends' ); ?></button>
				</div>
			</form>
		</div>

	<?php do_action( 'friends_after_header', $args ); ?>
