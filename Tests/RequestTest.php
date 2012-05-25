<?php

class RequestTest extends WpTestCase 
{
	function setUp() {
		parent::setUp();

		global $wp_rewrite;
		
		// set permalink structure
		$wp_rewrite->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
		create_initial_taxonomies();
		$wp_rewrite->flush_rules();
	}

	function tearDown() {
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
	function assertQueryTrue(/* ... */) {
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


	private function getPostIdByName($name) {
		global $wpdb;
		$name = $wpdb->escape($name);

		$id = $wpdb->get_var("SELECT ID from {$wpdb->posts} WHERE post_name = '{$name}' LIMIT 1");
		assert(is_numeric($id));
		return $id;
	}

	function testHome() {
		$this->request('/');
		$this->assertQueryTrue('is_home');
	}

	function test404() {
		$this->request('/' . substr(md5(uniqid(rand())), 0, 20));
		$this->assertQueryTrue('is_404');
	}

	function testPermalink() {
		$this->request( get_permalink($this->getPostIdByName('hello-world')) );
		$this->assertQueryTrue('is_single', 'is_singular');
	}

	function testPostCommentsFeed() {
		$this->request(get_post_comments_feed_link($this->getPostIdByName('hello-world')));
		$this->assertQueryTrue('is_feed', 'is_single', 'is_singular', 'is_comment_feed');
	}

	/*
	function testPage() {
		$page_id = $this->getPostIdByName('about');
		$this->request(get_permalink($page_id));
		$this->assertQueryTrue('is_page','is_singular');
	}

	function testParentPage() {
		$page_id = $this->getPostIdByName('parent-page');
		$this->request(get_permalink($page_id));

		$this->assertQueryTrue('is_page','is_singular');
	}

	function testChildPage1() {
		$page_id = $this->getPostIdByName('child-page-1');
		$this->request(get_permalink($page_id));

		$this->assertQueryTrue('is_page','is_singular');
	}

	function testChildPage2() {
		$page_id = $this->getPostIdByName('child-page-2');
		$this->request(get_permalink($page_id));

		$this->assertQueryTrue('is_page','is_singular');
	}

	// '(about)/trackback/?$' => 'index.php?pagename=$matches[1]&tb=1'
	function testPageTrackback() {
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
	function testPageFeed() {
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

	*/

}
