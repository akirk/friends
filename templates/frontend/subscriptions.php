<?php
/**
 * This is the Followers list
 *
 * @version 1.0
 * @package Friends
 */

$args['title'] = __( 'Your Subscriptions', 'friends' );
$args['no-bottom-margin'] = true;

Friends\Friends::template_loader()->get_template_part( 'frontend/header', null, $args );

?>
<section class="subscriptions">
	<p>This page is under construction.</p>
	<ul>
		<?php
		foreach ( Friends\User_Query::all_subscriptions()->get_results() as $subscription ) {
			?>
			<li>
				<a href="<?php echo esc_attr( $subscription->get_local_friends_page_url() ); ?>"><?php echo esc_html( $subscription->display_name ); ?></a>
			</li>
			<?php
		}
		?>
</section>
<?php
Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	null,
	$args
);
