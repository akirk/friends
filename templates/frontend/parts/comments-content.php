<?php
/**
 * This template contains the comments content in the footer for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

?><footer class="comments-content card-footer">
<div class="comments-list"></div>
<?php
comment_form(
	array(
		'title_reply'  => __( 'Send a reply', 'friends' ),
		'logged_in_as' => '',
	)
);
?>
</footer>
