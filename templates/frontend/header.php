<?php
/**
 * The /friends/ header
 *
 * @version 1.0
 * @package Friends
 */

$search = '';
if ( isset( $_GET['s'] ) ) {
	$search = wp_unslash( $_GET['s'] );
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<script>
		if (typeof navigator.registerProtocolHandler === "function") {
			navigator.registerProtocolHandler( 'web+follow', '<?php echo esc_js( add_query_arg( 'add-friend', '%s', home_url() ) ); ?>', 'Follow' );
		}
	</script>
</head>

<body <?php body_class( 'off-canvas off-canvas-sidebar-show' ); ?>>
	<div id="friends-sidebar" class="off-canvas-sidebar">
		<div class="friends-brand">
			<a class="friends-logo" href="<?php echo esc_url( home_url( '/friends/' ) ); ?>"><h2><?php esc_html_e( 'Friends', 'friends' ); ?></h2></a>
		</div>
		<div class="friends-nav accordion-container">
			<?php dynamic_sidebar( 'friends-sidebar' ); ?>
		</div>
	</div>

	<a class="off-canvas-overlay" href="#close"></a>

	<div class="off-canvas-content">
		<header class="<?php echo is_single() ? '' : 'navbar'; ?>">
			<section class="navbar-section author">
			<a class="off-canvas-toggle btn btn-primary bt-action" href="#friends-sidebar">
				<span class="ab-icon dashicons dashicons-menu-alt2"></span>
			</a>
			<?php
			if ( $args['friend_user'] && $args['friend_user'] instanceof Friends\User && is_singular() ) {
				Friends\Friends::template_loader()->get_template_part(
					'frontend/single-header',
					$args['post_format'],
					$args
				);
			} elseif ( $args['friend_user'] && $args['friend_user'] instanceof Friends\User ) {
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

			</section>
			<?php if ( ! is_singular() ) : ?>
			<section class="navbar-section">
				<form class="input-group input-inline form-autocomplete" action="<?php echo esc_url( home_url( '/friends/' ) ); ?>">
					<div class="form-autocomplete-input form-input">
						<div class="has-icon-right">
							<input class="form-input" type="text" tabindex="2" name="s" placeholder="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Search' ); ?>" value="<?php echo esc_attr( $search ); ?>" id="master-search" autocomplete="off"/>
							<i class="form-icon"></i>
						</div>
					</div>
					<ul class="menu" style="display: none">
					</ul>
					<button class="btn btn-primary input-group-btn"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Search' ); ?></button>
				</form>
			</section>
			<?php endif; ?>
		</header>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="quick-post-panel" class="<?php echo isset( $_REQUEST['in_reply_to'] ) ? 'open' : ''; ?>">
			<?php wp_nonce_field( 'friends_publish' ); ?>
			<input type="hidden" name="action" value="friends_publish" />
			<input type="hidden" name="format" value="status" />
			<input type="hidden" name="status" value="publish" />
				<div class="form-group">
					<div class="has-icon-right">
						Reply to: <input class="form-input" type="url" name="in_reply_to" placeholder="<?php esc_attr_e( 'In reply to https://...', 'friends' ); ?>" value="<?php echo esc_attr( ! empty( $args['in_reply_to'] ) ? $args['in_reply_to']['url'] : '' ); ?>" id="friends_in_reply_to" autocomplete="off"/>
						<i class="form-icon"></i>
					</div>
				</div>
				<div id="in_reply_to_preview"><?php echo wp_kses_post( ! empty( $args['in_reply_to'] ) ? $args['in_reply_to']['html'] : '' ); ?></div>
				<p class="description">Click the mentions to copy them into your reply.</p>
				<div class="form-group<?php echo esc_attr( $args['blocks-everywhere'] ? ' blocks-everywhere iso-editor__loading' : '' ); ?>">
					<textarea class="form-input friends-status-content<?php echo esc_attr( $args['blocks-everywhere'] ? ' blocks-everywhere-enabled' : '' ); ?>" name="content" rows="5" cols="70" placeholder="<?php echo /* translators: %s is a user display name. */ esc_attr( sprintf( __( "What's on your mind, %s?", 'friends' ), wp_get_current_user()->display_name ) ); ?>"><?php echo wp_kses_post( ! empty( $args['in_reply_to'] ) ? ( $args['blocks-everywhere'] ? '<!-- wp:paragraph -->' . PHP_EOL . '<p>' . $args['in_reply_to']['mention'] . PHP_EOL . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL : $args['in_reply_to']['mention'] . ' ' ) : '' ); ?></textarea><br />
					<?php
					do_action( 'friends_post_status_form' );
					?>
				</div>

				<div class="form-group col-4">
					<select name="status" class="form-select">
						<option value="publish"><?php esc_html_e( 'Visible to everyone', 'friends' ); ?></option>
						<option value="private"><?php esc_html_e( 'Only visible to my friends', 'friends' ); ?></option>
					</select>
				</div>

				<div class="form-group col-4">
					<p>
					<?php
					echo esc_html(
						sprintf(
							// translators: %s is the name of a post format.
							__( 'Post Format: %s', 'friends' ),
							__( 'Status' ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
						)
					);
					?>
				</p>
				</div>

				<div class="form-group">
					<button class="btn"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Publish' ); ?></button>
					<a href="#" class="quick-post-panel-toggle"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Cancel' ); ?></a>
				</div>
		</form>
<?php
do_action( 'friends_after_header', $args );
