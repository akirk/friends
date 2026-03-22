<?php
/**
 * Class BlockThemeTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Block Theme functionality.
 */
class BlockThemeTest extends \WP_UnitTestCase {
	/**
	 * The Friends instance.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * A subscription user.
	 *
	 * @var User
	 */
	private $friend_user;

	/**
	 * Setup the unit tests.
	 */
	public function set_up() {
		parent::set_up();
		$this->friends = Friends::get_instance();

		$this->friend_user = User::create( 'friend.local', 'subscription', 'http://friend.local/' );

		// Create some friend posts.
		$this->factory->post->create(
			array(
				'post_type'   => Friends::CPT,
				'post_title'  => 'Test Friend Post',
				'post_status' => 'publish',
				'post_author' => $this->friend_user->ID,
				'guid'        => 'http://friend.local/2024/test-post',
			)
		);
	}

	/**
	 * Test that all blocks are registered.
	 */
	public function test_blocks_registered() {
		$registry = \WP_Block_Type_Registry::get_instance();

		$expected_blocks = array(
			'friends/stats',
			'friends/refresh',
			'friends/post-formats',
			'friends/add-subscription',
			'friends/starred-friends-list',
			'friends/search',
			'friends/feed-header',
			'friends/post-content',
			'friends/post-permalink',
			'friends/author-header',
			'friends/subscriptions-query',
			'friends/subscription',
			'friends/followers',
		);

		foreach ( $expected_blocks as $block_name ) {
			$this->assertNotNull(
				$registry->get_registered( $block_name ),
				"Block $block_name should be registered"
			);
		}
	}

	/**
	 * Test that block templates exist on disk.
	 */
	public function test_template_files_exist() {
		$templates = array(
			'themes/friends/templates/index.html',
			'themes/friends/templates/friends-author-index.html',
			'themes/friends/templates/friends-followers.html',
			'themes/friends/templates/friends-subscriptions.html',
			'themes/friends/templates/single-friend_post_cache.html',
		);

		foreach ( $templates as $template ) {
			$this->assertFileExists(
				FRIENDS_PLUGIN_DIR . $template,
				"Template file $template should exist"
			);
		}
	}

	/**
	 * Test that template part files exist on disk.
	 */
	public function test_template_part_files_exist() {
		$parts = array(
			'themes/friends/parts/sidebar.html',
			'themes/friends/parts/header.html',
			'themes/friends/parts/author-header.html',
		);

		foreach ( $parts as $part ) {
			$this->assertFileExists(
				FRIENDS_PLUGIN_DIR . $part,
				"Template part file $part should exist"
			);
		}

		// Footer should not exist.
		$this->assertFileDoesNotExist(
			FRIENDS_PLUGIN_DIR . 'themes/friends/parts/footer.html',
			'Footer template part should not exist'
		);
	}

	/**
	 * Test that template content map covers all templates.
	 */
	public function test_get_block_template_content_for() {
		$frontend = $this->friends->frontend;

		// Valid mappings should return content.
		$this->assertNotFalse( $frontend->get_block_template_content_for( 'frontend/index' ) );
		$this->assertNotFalse( $frontend->get_block_template_content_for( 'frontend/author-index' ) );
		$this->assertNotFalse( $frontend->get_block_template_content_for( 'frontend/single' ) );
		$this->assertNotFalse( $frontend->get_block_template_content_for( 'frontend/followers' ) );
		$this->assertNotFalse( $frontend->get_block_template_content_for( 'frontend/subscriptions' ) );

		// Unknown template should return false.
		$this->assertFalse( $frontend->get_block_template_content_for( 'frontend/nonexistent' ) );
	}

	/**
	 * Test the search block renders a form.
	 */
	public function test_render_search_block() {
		$blocks = new Blocks();
		$output = $blocks->render_search_block();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'name="s"', $output );
		$this->assertStringContainsString( 'data-nonce=', $output );
	}

	/**
	 * Test the feed header block renders title and chips.
	 */
	public function test_render_feed_header_block() {
		$blocks = new Blocks();
		$output = $blocks->render_feed_header_block();

		$this->assertStringContainsString( 'wp-block-friends-feed-header', $output );
		$this->assertStringContainsString( '/friends/', $output );
		$this->assertStringContainsString( 'chip', $output );
	}

	/**
	 * Test the stats block renders subscription count.
	 */
	public function test_render_stats_block() {
		$blocks = new Blocks();
		$output = $blocks->render_stats_block();

		$this->assertStringContainsString( 'wp-block-friends-stats', $output );
		$this->assertStringContainsString( '/friends/subscriptions/', $output );
	}

	/**
	 * Test the refresh block renders a link.
	 */
	public function test_render_refresh_block() {
		$blocks = new Blocks();
		$output = $blocks->render_refresh_block();

		$this->assertStringContainsString( '?refresh', $output );
	}

	/**
	 * Test the post formats block renders format links.
	 */
	public function test_render_post_formats_block() {
		$blocks = new Blocks();
		$output = $blocks->render_post_formats_block();

		$this->assertStringContainsString( 'wp-block-friends-post-formats', $output );
		$this->assertStringContainsString( '/friends/', $output );
		$this->assertStringContainsString( '/friends/type/', $output );
	}

	/**
	 * Test the add subscription block renders a link.
	 */
	public function test_render_add_subscription_block() {
		$blocks = new Blocks();
		$output = $blocks->render_add_subscription_block();

		$this->assertStringContainsString( 'page=add-friend', $output );
	}

	/**
	 * Test the post-permalink block renders placeholder without post context.
	 */
	public function test_render_post_permalink_block_no_post() {
		global $post;
		$post = null;

		$blocks = new Blocks();
		$output = $blocks->render_post_permalink_block();

		$this->assertStringContainsString( 'wp-block-friends-post-permalink', $output );
		$this->assertStringContainsString( 'example.com', $output );
	}

	/**
	 * Test the post-permalink block renders with a post.
	 */
	public function test_render_post_permalink_block_with_post() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Friends::CPT,
				'post_title'  => 'Permalink Test Post',
				'post_status' => 'publish',
				'post_author' => $this->friend_user->ID,
				'guid'        => 'http://friend.local/2024/permalink-test',
			)
		);

		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		$blocks = new Blocks();
		$output = $blocks->render_post_permalink_block();

		$this->assertStringContainsString( 'wp-block-friends-post-permalink', $output );
		$this->assertStringContainsString( 'friend.local', $output );
		$this->assertStringContainsString( 'ago', $output );

		wp_reset_postdata();
	}

	/**
	 * Test the post-content block renders placeholder without post context.
	 */
	public function test_render_post_content_block_no_post() {
		global $post;
		$post = null;

		$blocks = new Blocks();
		$output = $blocks->render_post_content_block();

		$this->assertStringContainsString( 'wp-block-friends-post-content', $output );
		$this->assertStringContainsString( 'Post content will appear here', $output );
	}

	/**
	 * Test the author header block renders placeholder without author context.
	 */
	public function test_render_author_header_block_no_author() {
		$blocks = new Blocks();
		$output = $blocks->render_author_header_block();

		$this->assertStringContainsString( 'wp-block-friends-author-header', $output );
		$this->assertStringContainsString( 'Author Header', $output );
	}

	/**
	 * Test the starred friends list block renders empty when none starred.
	 */
	public function test_render_starred_friends_list_block_empty() {
		$blocks = new Blocks();
		$output = $blocks->render_starred_friends_list_block();

		$this->assertEmpty( $output );
	}

	/**
	 * Test that templates reference expected blocks.
	 */
	public function test_templates_contain_expected_blocks() {
		$index = file_get_contents( FRIENDS_PLUGIN_DIR . 'themes/friends/templates/index.html' );

		$this->assertStringContainsString( 'wp:template-part {"slug":"sidebar","theme":"friends"', $index );
		$this->assertStringContainsString( 'wp:template-part {"slug":"header","theme":"friends"', $index );
		$this->assertStringContainsString( 'wp:post-author-name', $index );
		$this->assertStringContainsString( 'wp:post-title', $index );
		$this->assertStringContainsString( 'wp:friends/post-content', $index );
		$this->assertStringContainsString( 'wp:friends/post-permalink', $index );
		$this->assertStringContainsString( 'wp:query-pagination', $index );

		// Footer should not be referenced.
		$this->assertStringNotContainsString( 'slug":"footer"', $index );
	}

	/**
	 * Test the author-index template uses the author-header part.
	 */
	public function test_author_index_template() {
		$author_index = file_get_contents( FRIENDS_PLUGIN_DIR . 'themes/friends/templates/friends-author-index.html' );

		$this->assertStringContainsString( 'wp:template-part {"slug":"author-header","theme":"friends"', $author_index );
		$this->assertStringContainsString( 'wp:friends/post-permalink', $author_index );
	}

	/**
	 * Test the single post template uses minimal header.
	 */
	public function test_single_template_minimal_header() {
		$single = file_get_contents( FRIENDS_PLUGIN_DIR . 'themes/friends/templates/single-friend_post_cache.html' );

		// Should have author name directly, not a template part header.
		$this->assertStringContainsString( 'wp:post-author-name', $single );
		$this->assertStringNotContainsString( 'slug":"header"', $single );
		$this->assertStringNotContainsString( 'friends/feed-header', $single );
	}

	/**
	 * Test the sidebar template part has expected structure.
	 */
	public function test_sidebar_structure() {
		$sidebar = file_get_contents( FRIENDS_PLUGIN_DIR . 'themes/friends/parts/sidebar.html' );

		$this->assertStringContainsString( 'wp:friends/stats', $sidebar );
		$this->assertStringContainsString( 'wp:friends/refresh', $sidebar );
		$this->assertStringContainsString( 'wp:friends/post-formats', $sidebar );
		$this->assertStringContainsString( 'wp:friends/starred-friends-list', $sidebar );
		$this->assertStringContainsString( 'wp:friends/friends-list', $sidebar );
		$this->assertStringContainsString( 'wp:friends/add-subscription', $sidebar );
	}

	/**
	 * Test the header template part has search in right column.
	 */
	public function test_header_has_search() {
		$header = file_get_contents( FRIENDS_PLUGIN_DIR . 'themes/friends/parts/header.html' );

		$this->assertStringContainsString( 'wp:friends/feed-header', $header );
		$this->assertStringContainsString( 'wp:friends/search', $header );
		$this->assertStringContainsString( 'wp:columns', $header );
	}

	/**
	 * Test calculate_read_time is accessible.
	 */
	public function test_calculate_read_time() {
		// Short text.
		$short = Frontend::calculate_read_time( 'Hello world' );
		$this->assertLessThan( 60, $short );

		// Longer text (~200 words = ~1 min).
		$long = Frontend::calculate_read_time( str_repeat( 'word ', 400 ) );
		$this->assertGreaterThanOrEqual( 60, $long );
	}
}
