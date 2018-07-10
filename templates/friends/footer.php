<?php
/**
 * The /friends/ footer
 *
 * @package Friends
 */

?>
	<nav class="friends-sidebar">
		<?php if ( ! dynamic_sidebar( 'Friends Sidebar' ) ) : ?>
			<div class="friends-widget">
				<?php
				the_widget(
					'Friends_Widget_Refresh', '', array(
						'before_widget' => '<div class="friends-widget">',
						'after_widget'  => '</div>',
						'before_title'  => '<h4>',
						'after_title'   => '</h4>',
					)
				);
				?>
			</div>
			<div class="friends-widget">
				<?php
				the_widget(
					'Friends_Widget_Friend_List', 'title=Friends', array(
						'before_widget' => '<div class="friends-widget">',
						'after_widget'  => '</div>',
						'before_title'  => '<h4>',
						'after_title'   => '</h4>',
					)
				);
				?>
			</div>
			<div class="friends-widget">
				<?php
				the_widget(
					'Friends_Widget_Friend_Request', '', array(
						'before_widget' => '<div class="friends-widget">',
						'after_widget'  => '</div>',
						'before_title'  => '<h4>',
						'after_title'   => '</h4>',
					)
				);
				?>
			</div>
		<?php endif; ?>
	</nav>
</div>
<?php wp_footer(); ?>
</body>
</html>
