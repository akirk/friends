<?php
/**
 * Class Friends_Saved_articleTest
 *
 * @package Friends
 */

/**
 * Test the Saved_Articles
 */
class Friends_Saved_ArticleTest extends WP_UnitTestCase {
	/**
	 * Setup the unit tests.
	 */
	public function setUp() {
		parent::setUp();
		$friends        = Friends::get_instance();
		$friends->saved = new Friends_Saved_Testable( $friends );
	}

	/**
	 * Test generating site_config filenames
	 */
	public function test_generate_site_config_filenames() {
		$friends = Friends::get_instance();
		$this->assertEquals( array( 'nutrition.about.com.txt', '.about.com.txt' ), $friends->saved->get_site_config_filenames( 'http://nutrition.about.com/od/changeyourdiet/qt/healthysnacks.htm' ) );
		$this->assertEquals( array( 'bits.blogs.nytimes.com.txt', '.blogs.nytimes.com.txt' ), $friends->saved->get_site_config_filenames( 'http://bits.blogs.nytimes.com/2012/01/16/wikipedia-plans-to-go-dark-on-wednesday-to-protest-sopa/' ) );
		$this->assertEquals( array( 'appleinsider.com.txt' ), $friends->saved->get_site_config_filenames( 'http://www.appleinsider.com/articles/12/02/29/inside_os_x_108_mountain_lion_safari_52_gets_a_simplified_user_interface_with_new_sharing_features' ) );
		$this->assertEquals( array( 'derstandard.at.txt' ), $friends->saved->get_site_config_filenames( 'http://derstandard.at/1318726018343/Breitband-LTE-Was-bringt-die-neue-Mobilfunk-Generation' ) );
	}

	/**
	 * Test get a site config
	 */
	public function test_get_site_config() {
		$friends = Friends::get_instance();

		$site_config = $friends->saved->get_site_config( 'https://somewhere.siteconfig.example.com/test' );
		$this->assertEquals( '//h1[@class="header"]', $site_config['title'] );
	}

	/**
	 * Test parsing a site config
	 */
	public function test_parse_site_config() {
		$friends     = Friends::get_instance();
		$site_config = $friends->saved->parse_site_config( file_get_contents( __DIR__ . '/data/.siteconfig.example.com.txt' ) );

		$this->assertEquals( '//h1[@class="header"]', $site_config['title'] );
		$this->assertEquals( "//p[contains(@class, 'our-author')]/a", $site_config['author'] );
		$this->assertEquals( "//p[contains(@class, 'date')]", $site_config['date'] );
		$this->assertEquals( '//div[@class="article"]', $site_config['body'] );
		$this->assertEquals( 'send=cookie; another=one', $site_config['http_header']['cookie'] );
		$this->assertEquals( 'PHP/5.3', $site_config['http_header']['user-agent'] );
		$this->assertEquals( 'https://t.co/fromtwitter', $site_config['http_header']['referer'] );
		$this->assertEquals( '<div>', $site_config['replace']['<noscript>'] );
		$this->assertEquals( '</div>', $site_config['replace']['</noscript>'] );
	}

	/**
	 * Test extracting a basic page
	 */
	public function test_extract_basic_page() {
		$friends = Friends::get_instance();
		$item    = $friends->saved->extract_content( file_get_contents( __DIR__ . '/data/basic-page.html' ) );

		$this->assertContains( 'First Title', $item->title );
		$this->assertContains( 'This is the article.', $item->content );
		$this->assertEquals( 1, substr_count( $item->content, 'This is the article.' ) );
		$this->assertContains( 'This should be removed with a site config.', $item->content );
		$this->assertNotContains( 'This is not the article.', $item->content );
	}

	/**
	 * Test extracting a basic page
	 */
	public function test_extract_basic_page_with_site_config() {
		$friends     = Friends::get_instance();
		$site_config = $friends->saved->get_site_config( 'https://somewhere.siteconfig.example.com/test' );
		$item        = $friends->saved->extract_content( file_get_contents( __DIR__ . '/data/basic-page.html' ), $site_config );

		$this->assertEquals( 'Site Config Title', $item->title );
		$this->assertContains( 'This is the article.', $item->content );
		$this->assertNotContains( 'This is not the article.', $item->content );
		$this->assertNotContains( 'This should be removed with a site config.', $item->content );
	}
}

/**
 * Make the Saved_articles testable
 */
class Friends_Saved_Testable extends Friends_Saved {
	/**
	 * Download site config for a URL if it exists
	 *
	 * @param  string $filename The filename to download.
	 * @return string|false The site config.
	 */
	public function download_site_config( $filename ) {
		$filename = __DIR__ . '/data/' . basename( $filename );
		if ( file_exists( $filename ) ) {
			return file_get_contents( $filename );
		}
		return false;
	}
}
