<?php
/**
 * This template contains the comments content in the footer for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

?><footer class="comments-content card-footer" style="display: block;">
<?php
if ( is_single() ) {
	\Friends\Feed_Parser_ActivityPub::comment_form( get_the_ID() );
}
?>
</footer>
