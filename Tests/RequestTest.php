<?php

class RequestTest extends WpTestCase 
{
	function setUp() 
	{
		parent::setUp();

		global $wp_rewrite;
		
		static::nukeMainTables();
		$this->loadSqlDump(dirname(__FILE__).'/../Resources/Data/asdftestblog1.2007-11-23.fixed.sql');

		// set permalink structure
		$wp_rewrite->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
		create_initial_taxonomies();
		$wp_rewrite->flush_rules();
	}

	function tearDown() 
	{
		global $wp_rewrite;

		$wp_rewrite->set_permalink_structure('');
		parent::tearDown();
	}

	/**
	 * Check each of the WP_Query is_* functions/properties against expected boolean value.
	 *
	 * Any properties that are listed by name as parameters will be expected to be true; any others are
	 * expected to be false. For example, assertQueryTrue('is_single', 'is_feed') means is_single()
	 * and is_feed() must be true and everything else must be false to pass.
	 *
	 * @param string $prop,... Any number of WP_Query properties that are expected to be true for the current request.
	 */
	function assertQueryTrue(/* ... */) 
	{
		global $wp_query;
		$all = array(
			'is_single', 'is_preview', 'is_page', 'is_archive', 'is_date', 'is_year', 'is_month', 'is_day', 'is_time',
			'is_author', 'is_category', 'is_tag', 'is_tax', 'is_search', 'is_feed', 'is_comment_feed', 'is_trackback',
			'is_home', 'is_404', 'is_comments_popup', 'is_paged', 'is_admin', 'is_attachment', 'is_singular', 'is_robots',
			'is_posts_page', 'is_post_type_archive',
		);
		$true = func_get_args();

		$passed = true;
		$not_false = $not_true = array(); // properties that were not set to expected values

		foreach ( $all as $query_thing ) {
			$result = is_callable( $query_thing ) ? call_user_func( $query_thing ) : $wp_query->$query_thing;

			if ( in_array( $query_thing, $true ) ) {
				if ( ! $result ) {
					array_push( $not_true, $query_thing );
					$passed = false;
				}
			} else if ( $result ) {
				array_push( $not_false, $query_thing );
				$passed = false;
			}
		}

		$message = '';
		if ( count($not_true) )
			$message .= implode( $not_true, ', ' ) . ' should be true. ';
		if ( count($not_false) )
			$message .= implode( $not_false, ', ' ) . ' should be false.';
		$this->assertTrue( $passed, $message );
	}


	protected function getPostIdByName($name) 
	{
		global $wpdb;
		$name = $wpdb->escape($name);

		$id = $wpdb->get_var("SELECT ID from {$wpdb->posts} WHERE post_name = '{$name}' LIMIT 1");
		assert(is_numeric($id));
		return $id;
	}

	protected function getAllPostIds($type='post') {
		global $wpdb;
		$type = $wpdb->escape($type);
		return $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='{$type}' and post_status='publish'");
	}

	function testHome() 
	{
		$this->request('/');
		$this->assertQueryTrue('is_home');
	}

	function test404() 
	{
		$this->request('/' . substr(md5(uniqid(rand())), 0, 20));
		$this->assertQueryTrue('is_404');
	}

	function testPermalink() 
	{
		$this->request( get_permalink($this->getPostIdByName('hello-world')) );
		$this->assertQueryTrue('is_single', 'is_singular');
	}

	function testPostCommentsFeed() 
	{
		$this->request(get_post_comments_feed_link($this->getPostIdByName('hello-world')));
		$this->assertQueryTrue('is_feed', 'is_single', 'is_singular', 'is_comment_feed');
	}

	function testPage() 
	{
		$page_id = $this->getPostIdByName('about');
		$this->request(get_permalink($page_id));
		$this->assertQueryTrue('is_page','is_singular');
	}

	function testParentPage() 
	{
		$page_id = $this->getPostIdByName('parent-page');
		$this->request(get_permalink($page_id));

		$this->assertQueryTrue('is_page','is_singular');
	}

	function testChildPage1() 
	{
		$page_id = $this->getPostIdByName('child-page-1');
		$this->request(get_permalink($page_id));

		$this->assertQueryTrue('is_page','is_singular');
	}

	function testChildPage2() 
	{
		$page_id = $this->getPostIdByName('child-page-2');
		$this->request(get_permalink($page_id));

		$this->assertQueryTrue('is_page','is_singular');
	}

	// '(about)/trackback/?$' => 'index.php?pagename=$matches[1]&tb=1'
	function testPageTrackback() 
	{
		$pages = array('about', 'lorem-ipsum', 'parent-page', 'child-page-1', 'child-page-2');
		foreach ($pages as $name) {
			$page_id = $this->getPostIdByName($name);
			$url = get_permalink($page_id);
			$this->request("{$url}trackback/");

			// make sure the correct wp_query flags are set
			$this->assertQueryTrue('is_page','is_singular','is_trackback');

			// make sure the correct page was fetched
			global $wp_query;
			$this->assertEquals( $page_id, $wp_query->get_queried_object()->ID );
		}
	}

	//'(about)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?pagename=$matches[1]&feed=$matches[2]'
	function testPageFeed() 
	{
		$pages = array('about', 'lorem-ipsum', 'parent-page', 'child-page-1', 'child-page-2');
		foreach ($pages as $name) {
			$page_id = $this->getPostIdByName($name);
			$url = get_permalink($page_id);
			$this->request("{$url}feed/");

			// make sure the correct wp_query flags are set
			$this->assertQueryTrue('is_page', 'is_singular', 'is_feed', 'is_comment_feed');

			// make sure the correct page was fetched
			global $wp_query;
			$this->assertEquals( $page_id, $wp_query->get_queried_object()->ID );
		}
	}


	// '(about)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?pagename=$matches[1]&feed=$matches[2]'
	function test_page_feed_atom() {
		$pages = array('about', 'lorem-ipsum', 'parent-page', 'child-page-1', 'child-page-2');
		foreach ($pages as $name) {
			$page_id = $this->getPostIdByName($name);
			$url = get_permalink($page_id);
			$this->request("{$url}feed/atom/");

			// make sure the correct wp_query flags are set
			$this->assertQueryTrue('is_page', 'is_singular', 'is_feed', 'is_comment_feed');

			// make sure the correct page was fetched
			global $wp_query;
			$this->assertEquals( $page_id, $wp_query->get_queried_object()->ID );
		}
	}

	// '(about)/page/?([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&paged=$matches[2]'
	function test_page_page_2() {
		$pages = array('about', 'lorem-ipsum', 'parent-page', 'child-page-1', 'child-page-2');
		foreach ($pages as $name) {
			$page_id = $this->getPostIdByName($name);
			$url = get_permalink($page_id);
			$this->request("{$url}page/2/");

			// make sure the correct wp_query flags are set
			$this->assertQueryTrue('is_page', 'is_singular', 'is_paged');

			// make sure the correct page was fetched
			global $wp_query;
			$this->assertEquals( $page_id, $wp_query->get_queried_object()->ID );
		}
	}

	// FIXME: what is this for?
	// '(about)(/[0-9]+)?/?$' => 'index.php?pagename=$matches[1]&page=$matches[2]'
	function test_page_page_2_short() {
		//return $this->markTestSkipped();
		// identical to /about/page/2/ ?
		$this->request('/about/2/');

		$this->assertQueryTrue('is_page', 'is_singular');
	}

	// FIXME: no tests for these yet
	// 'about/attachment/([^/]+)/?$' => 'index.php?attachment=$matches[1]',
	// 'about/attachment/([^/]+)/trackback/?$' => 'index.php?attachment=$matches[1]&tb=1',
	// 'about/attachment/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',
	// 'about/attachment/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?attachment=$matches[1]&feed=$matches[2]',

	// 'feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]',
	// '(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]',
	function test_main_feed_2() {
		$feeds = array('feed', 'rdf', 'rss', 'rss2', 'atom');

		// long version
		foreach ($feeds as $feed) {
			$this->request("/feed/{$feed}/");
			$this->assertQueryTrue('is_feed');
		}

		// short version
		foreach ($feeds as $feed) {
			$this->request("/{$feed}/");
			$this->assertQueryTrue('is_feed');
		}

	}

	function test_main_feed() {
		$types = array('rss2', 'rss', 'atom');
		foreach ($types as $type) {
			$this->request(get_feed_link($type));
			$this->assertQueryTrue('is_feed');
		}
	}

	// 'page/?([0-9]{1,})/?$' => 'index.php?&paged=$matches[1]',
	function test_paged() {
		for ($i=2; $i<4; $i++) {
			$this->request("/page/{$i}/");
			$this->assertQueryTrue('is_home', 'is_paged');
		}
	}

	// 'comments/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]&withcomments=1',
	// 'comments/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]&withcomments=1',
	function test_main_comments_feed() {
		// check the url as generated by get_post_comments_feed_link()
		$this->request(get_post_comments_feed_link($this->getPostIdByName('hello-world')));
		$this->assertQueryTrue('is_feed', 'is_single', 'is_singular', 'is_comment_feed');

		// check the long form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/comments/feed/{$type}");
				$this->assertQueryTrue('is_feed', 'is_comment_feed');
		}

		// check the short form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/comments/{$type}");
				$this->assertQueryTrue('is_feed', 'is_comment_feed');
		}

	}

	// 'comments/page/?([0-9]{1,})/?$' => 'index.php?&paged=$matches[1]',
	function test_comments_page() {
		$this->request('/comments/page/2/');
		$this->assertQueryTrue('is_home', 'is_paged');
	}


	// 'search/(.+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?s=$matches[1]&feed=$matches[2]',
	// 'search/(.+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?s=$matches[1]&feed=$matches[2]',
	function test_search_feed() {
		// check the long form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/search/test/feed/{$type}");
				$this->assertQueryTrue('is_feed', 'is_search');
		}

		// check the short form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/search/test/{$type}");
				$this->assertQueryTrue('is_feed', 'is_search');
		}
	}

	// 'search/(.+)/page/?([0-9]{1,})/?$' => 'index.php?s=$matches[1]&paged=$matches[2]',
	function test_search_paged() {
		$this->request('/search/test/page/2/');
		$this->assertQueryTrue('is_search', 'is_paged');
	}

	// 'search/(.+)/?$' => 'index.php?s=$matches[1]',
	function test_search() {
		$this->request('/search/test/');
		$this->assertQueryTrue('is_search');
	}

	// 'category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
	// 'category/(.+?)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
	function test_category_feed() {
		// check the long form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/category/cat-a/feed/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_category');
		}

		// check the short form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/category/cat-a/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_category');
		}
	}

	// 'category/(.+?)/page/?([0-9]{1,})/?$' => 'index.php?category_name=$matches[1]&paged=$matches[2]',
	function test_category_paged() {
		$this->request('/category/uncategorized/page/2/');
		$this->assertQueryTrue('is_archive', 'is_category', 'is_paged');
	}

	// 'category/(.+?)/?$' => 'index.php?category_name=$matches[1]',
	function test_category() {
		$this->request('/category/cat-a/');
		$this->assertQueryTrue('is_archive', 'is_category');
	}

	// 'tag/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?tag=$matches[1]&feed=$matches[2]',
	// 'tag/(.+?)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?tag=$matches[1]&feed=$matches[2]',
	function test_tag_feed() {
		// check the long form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/tag/tag-a/feed/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_tag');
		}

		// check the short form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/tag/tag-a/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_tag');
		}
	}

	// 'tag/(.+?)/?$' => 'index.php?tag=$matches[1]',
	function test_tag() {
		$this->request('/tag/tag-a/');
		$this->assertQueryTrue('is_archive', 'is_tag');
	}

	// 'author/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?author_name=$matches[1]&feed=$matches[2]',
	// 'author/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?author_name=$matches[1]&feed=$matches[2]',
	function test_author_feed() {
		// check the long form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/author/user-a/feed/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_author');
		}

		// check the short form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/author/user-a/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_author');
		}
	}

	// 'author/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?author_name=$matches[1]&paged=$matches[2]',
	function test_author_paged() {
		$this->request('/author/user-a/page/2/');
		$this->assertQueryTrue('is_archive', 'is_author', 'is_paged');
	}

	// 'author/([^/]+)/?$' => 'index.php?author_name=$matches[1]',
	function test_author() {
		$this->request('/author/user-a/');
		$this->assertQueryTrue('is_archive', 'is_author');
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
	function test_ymd_feed() {
		// check the long form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/2007/09/04/feed/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_day', 'is_date');
		}

		// check the short form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/2007/09/04/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_day', 'is_date');
		}
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]',
	function test_ymd_paged() {
		$this->request('/2007/09/04/page/2/');
		$this->assertQueryTrue('is_archive', 'is_day', 'is_date', 'is_paged');
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]',
	function test_ymd() {
		$this->request('/2007/09/04/');
		$this->assertQueryTrue('is_archive', 'is_day', 'is_date');
	}

	// '([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
	// '([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
	function test_ym_feed() {
		// check the long form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/2007/09/feed/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_month', 'is_date');
		}

		// check the short form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/2007/09/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_month', 'is_date');
		}
	}

	// '([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]',
	function test_ym_paged() {
		$this->request('/2007/09/page/2/');
		$this->assertQueryTrue('is_archive', 'is_date', 'is_month', 'is_paged');
	}

	// '([0-9]{4})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]',
	function test_ym() {
		$this->request('/2007/09/');
		$this->assertQueryTrue('is_archive', 'is_date', 'is_month');
	}

	// '([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&feed=$matches[2]',
	// '([0-9]{4})/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&feed=$matches[2]',
	function test_y_feed() {
		// check the long form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/2007/feed/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_year', 'is_date');
		}

		// check the short form
		$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
		foreach ($types as $type) {
				$this->request("/2007/{$type}");
				$this->assertQueryTrue('is_archive', 'is_feed', 'is_year', 'is_date');
		}
	}

	// '([0-9]{4})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&paged=$matches[2]',
	function test_y_paged() {
		$this->request('/2007/page/2/');
		$this->assertQueryTrue('is_archive', 'is_date', 'is_year', 'is_paged');
	}

	// '([0-9]{4})/?$' => 'index.php?year=$matches[1]',
	function test_y() {
		$this->request('/2007/');
		$this->assertQueryTrue('is_archive', 'is_date', 'is_year');
	}


	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/trackback/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&tb=1',
	function test_post_trackback() {
		foreach ($this->getAllPostIds() as $id) {
			$permalink = get_permalink($id);
			$this->request("{$permalink}trackback/");
			$this->assertQueryTrue('is_single', 'is_singular', 'is_trackback');
		}
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]',
	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]',
	function test_post_comment_feed() {
		foreach ($this->getAllPostIds() as $id) {
			$permalink = get_permalink($id);

			$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
			foreach ($types as $type) {
					$this->request("{$permalink}feed/{$type}");
					$this->assertQueryTrue('is_single', 'is_singular', 'is_feed', 'is_comment_feed');
			}

			// check the short form
			$types = array('feed', 'rdf', 'rss', 'rss2', 'atom');
			foreach ($types as $type) {
					$this->request("{$permalink}{$type}");
					$this->assertQueryTrue('is_single', 'is_singular', 'is_feed', 'is_comment_feed');
			}

		}
	}
}
