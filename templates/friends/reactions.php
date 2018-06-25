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
		<button class="reaction <?php echo implode( ' ', $classes ); ?>" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-emoji="<?php echo esc_attr( $slug ); ?>">
			<?php
			switch ( $slug ) {
				case 'smile':
					echo '&#x1F604;';
					break;
				case 'sob':
					echo '&#x1F62D;';
					break;
			}
			?>
			<?php echo count( $users ); ?>
		</button>
		<?php
	}
	?>
	<button class="new-reaction" data-id="<?php echo esc_attr( get_the_ID() ); ?>">
		<span class="dashicons dashicons-plus"></span>
	</button>
</div>
