<?php
/**
 * Class LinkPreviewTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test link previews.
 */
class LinkPreviewTest extends \WP_UnitTestCase {
	/**
	 * Standard-format Friend posts support link previews.
	 */
	public function test_standard_posts_support_link_previews() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => '<p><a href="https://example.com/article">An article</a></p>',
				'post_status'  => 'publish',
				'guid'         => 'https://friend.example/posts/1',
			)
		);

		$this->assertTrue( Link_Preview::is_supported_post( get_post( $post_id ) ) );
	}
}
