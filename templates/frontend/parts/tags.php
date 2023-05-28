<?php
/**
 * This template contains the reactions in the footer for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$tags = get_the_tags();
if ( ! $tags ) {
	return;
}
?><div>
<?php
foreach ( $tags as $tag ) {
	?>
	<a href="<?php echo esc_attr( home_url( '/friends/tag/' . $tag->slug ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a>
	<?php
}
?>
</div>
<?php
