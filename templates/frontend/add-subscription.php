<?php
/**
 * This is the Add Subscription page
 *
 * @version 1.0
 * @package Friends
 */

$args['title'] = __( 'Add Subscription', 'friends' );
$args['no-bottom-margin'] = true;

Friends\Friends::template_loader()->get_template_part( 'frontend/header', null, $args );

?>
<section class="subscriptions">
	<p>This page is under construction.</p>
</section>
<?php
Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	null,
	$args
);
