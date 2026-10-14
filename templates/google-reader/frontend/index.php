<?php
/**
 * Google Reader theme: main /friends/ template.
 * Always starts in collapsed (list) mode.
 *
 * @package Friends
 */

Friends\Friends::template_loader()->get_template_part(
	'frontend/header',
	$args['post_format'],
	$args
);

?>
<section class="posts columns<?php echo is_single() ? '' : ' all-collapsed'; ?>">
	<?php
	Friends\Friends::template_loader()->get_template_part(
		$args['friends']->frontend->is_feed_deferred() ? 'frontend/feed-loading' : 'frontend/feed-content',
		null,
		$args
	);
	?>
</section>
<?php
Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	$args['post_format'],
	$args
);
