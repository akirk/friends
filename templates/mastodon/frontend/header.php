<?php
/**
 * Mastodon theme: page header with 3-column layout.
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

$mastodon_current_user = wp_get_current_user();

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="light dark">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

	<!-- Left column: user info + search -->
	<aside class="mastodon-left-col">
		<a class="mastodon-user-card" href="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
			<?php echo get_avatar( $mastodon_current_user->ID, 46, '', '', array( 'class' => 'mastodon-avatar' ) ); ?>
			<div class="mastodon-user-info">
				<strong class="mastodon-display-name"><?php echo esc_html( $mastodon_current_user->display_name ); ?></strong>
				<span class="mastodon-handle">@<?php echo esc_html( $mastodon_current_user->user_login ); ?></span>
			</div>
		</a>
		<form class="mastodon-search-form" action="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
			<div class="mastodon-search-wrap">
				<input class="mastodon-search-input" type="text" name="s" placeholder="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Search or paste URL' ); ?>" value="<?php echo esc_attr( $_search ); ?>" autocomplete="off" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-autocomplete' ) ); ?>" id="master-search" />
			</div>
		</form>
	</aside>

	<!-- Center column: timeline (left open, footer closes it) -->
	<div class="mastodon-center-col">
		<div class="mastodon-col-header<?php echo is_single() ? ' is-single' : ''; ?>">
			<div class="mastodon-col-title-row">
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
						echo '<img src="' . esc_url( $args['friend_user']->get_avatar_url() ) . '" width="24" height="24" class="mastodon-title-avatar" /> ';
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
			</div>
			<?php if ( ! is_single() && empty( $args['no-bottom-margin'] ) ) : ?>
			<div class="mastodon-chips-area">
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

	<?php do_action( 'friends_after_header', $args ); ?>
