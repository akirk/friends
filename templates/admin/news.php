<?php
/**
 * This template renders the Friends News page with a sidebar TOC.
 *
 * @package Friends
 */

namespace Friends;

$entries      = $args['entries'];
$active_index = 0;

?>
<div class="friends-news">
	<nav class="friends-news-toc">
		<?php
		foreach ( $entries as $index => $entry ) :
			$classes = 'friends-news-toc-item';
			if ( $index === $active_index ) {
				$classes .= ' is-active';
			}
			?>
			<a href="#" class="<?php echo esc_attr( $classes ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
				<?php echo esc_html( $entry['title'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php
	foreach ( $entries as $index => $entry ) :
		$is_welcome = '0' === $entry['version'];

		$entry_args = array();
		if ( ! empty( $entry['migration_version'] ) ) {
			$entry_args = Admin::get_migration_data( $entry['migration_version'] );
		}
		$entry_args['version'] = $entry['version'];
		if ( $is_welcome ) {
			$entry_args['installed_plugins'] = get_plugins();
			$entry_args['plugin-list']       = true;
		}
		?>
		<div class="friends-news-content" data-index="<?php echo esc_attr( $index ); ?>" <?php echo $index !== $active_index ? 'style="display:none"' : ''; ?>>
			<?php Friends::template_loader()->get_template_part( $entry['template'], null, $entry_args ); ?>
		</div>
	<?php endforeach; ?>
</div>

<script>
jQuery(document).ready(function($) {
	$('.friends-news-toc-item').on('click', function(e) {
		e.preventDefault();
		var index = $(this).data('index');
		$('.friends-news-toc-item').removeClass('is-active');
		$(this).addClass('is-active');
		$('.friends-news-content').hide();
		$('.friends-news-content[data-index="' + index + '"]').show();
	});
});
</script>

<style>
.friends-news {
	position: relative;
}
.friends-news-toc {
	position: fixed;
	width: 200px;
	margin-left: -220px;
	display: flex;
	flex-direction: column;
	gap: 2px;
}
.friends-news-toc-item {
	display: block;
	padding: 6px 10px;
	font-size: 12px;
	color: #50575e;
	text-decoration: none;
	border-radius: 3px;
	line-height: 1.4;
	text-wrap: balance;
}
.friends-news-toc-item:hover {
	color: #1d2327;
	background: #f0f0f1;
}
.friends-news-toc-item.is-active {
	color: #2271b1;
}
/* News entry content styles */
.friends-news-changes {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
	margin-bottom: 24px;
}
.friends-news-change {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 16px;
}
.friends-news-changes > h3 {
	grid-column: 1 / -1;
	margin: 8px 0 0;
	padding-bottom: 4px;
	border-bottom: 1px solid #c3c4c7;
}
.friends-news-changes > h3:first-child {
	margin-top: 0;
}
.friends-news-change h4 {
	margin-top: 0;
	font-size: 1.3em;
}
.friends-news-change p {
	margin-bottom: 0;
}
.friends-news-status-complete,
.friends-news-status-progress {
	margin: 16px 0;
	padding: 12px 16px;
	border-radius: 4px;
}
.friends-news-status-complete {
	background: #d4edda;
	color: #155724;
}
.friends-news-status-progress {
	background: #fff3cd;
	color: #856404;
}
.friends-news-status-complete .dashicons,
.friends-news-status-progress .dashicons {
	vertical-align: text-bottom;
	margin-right: 4px;
}
.friends-news-content .status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}
.friends-news-content .status-completed {
	background: #d4edda;
	color: #155724;
}
.friends-news-content .status-pending {
	background: #fff3cd;
	color: #856404;
}
.friends-news-content .status-in-progress {
	background: #cce5ff;
	color: #004085;
}
.friends-news-content .status-no-tracking {
	background: #e2e3e5;
	color: #383d41;
}
.friends-news-content .progress-bar {
	width: 100%;
	height: 8px;
	background: #e0e0e0;
	border-radius: 4px;
	margin-top: 5px;
	overflow: hidden;
}
.friends-news-content .progress-bar-fill {
	height: 100%;
	background: #0073aa;
	transition: width 0.3s ease;
}
@media screen and (max-width: 1200px) {
	.friends-news-toc {
		position: static;
		width: auto;
		margin-left: 0;
		flex-direction: row;
		flex-wrap: wrap;
		margin-bottom: 16px;
		gap: 4px;
	}
}
@media screen and (max-width: 782px) {
	.friends-news-changes {
		grid-template-columns: 1fr;
	}
}
</style>
