<?php
/**
 * This is the reaction section for posts.
 *
 * @package Friends
 */

?>
<div class="friend-reactions">
	<?php
	foreach ( $reactions as $slug => $users ) {
		$classes = array();
		if ( isset( $users[ get_current_user_id() ] ) ) {
			$classes[] = 'pressed';
		}
		?>
		<button class="<?php echo implode( ' ', $classes ); ?>">
			<?php
			switch ( $slug ) {
				case 'smile':
					echo '&#x1F604;';
					break;
			}
			?>
			<?php echo count( $users ); ?>
		</button>
		<?php
	}
	?>
	<button class="new-reaction">
		<span class="dashicons dashicons-plus"></span>
	</button>
</div>
