<?php

class RequestTest extends WpTestCase
{
    public function setUp()
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

    public function tearDown()
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
    public function assertQueryTrue(/* ... */)
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
            } elseif ( $result ) {
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

    protected function getAllPostIds($type='post')
    {
        global $wpdb;
        $type = $wpdb->escape($type);

        return $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='{$type}' and post_status='publish'");
    }

    public function testHome()
    {
        $this->request('/');
        $this->assertQueryTrue('is_home');
    }

    public function test404()
    {
        $this->request('/' . substr(md5(uniqid(rand())), 0, 20));
        $this->assertQueryTrue('is_404');
    }

    public function testPermalink()
    {
        $this->request( get_permalink($this->getPostIdByName('hello-world')) );
        $this->assertQueryTrue('is_single', 'is_singular');
    }

    public function testPostCommentsFeed()
    {
        $this->request(get_post_comments_feed_link($this->getPostIdByName('hello-world')));
        $this->assertQueryTrue('is_feed', 'is_single', 'is_singular', 'is_comment_feed');
    }

    public function testPage()
    {
        $page_id = $this->getPostIdByName('about');
        $this->request(get_permalink($page_id));
        $this->assertQueryTrue('is_page','is_singular');
    }

    public function testParentPage()
    {
        $page_id = $this->getPostIdByName('parent-page');
        $this->request(get_permalink($page_id));

        $this->assertQueryTrue('is_page','is_singular');
    }

    public function testChildPage1()
    {
        $page_id = $this->getPostIdByName('child-page-1');
        $this->request(get_permalink($page_id));

        $this->assertQueryTrue('is_page','is_singular');
    }

    public function testChildPage2()
    {
        $page_id = $this->getPostIdByName('child-page-2');
        $this->request(get_permalink($page_id));

        $this->assertQueryTrue('is_page','is_singular');
    }

    // '(about)/trackback/?$' => 'index.php?pagename=$matches[1]&tb=1'
    public function testPageTrackback()
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
    public function testPageFeed()
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
    public function testPageFeedAtom()
    {
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
    public function testPagePage2()
    {
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
    public function testPagePage2Short()
    {
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
    public function testMainFeed2()
    {
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

    public function testMainFeed()
    {
        $types = array('rss2', 'rss', 'atom');
        foreach ($types as $type) {
            $this->request(get_feed_link($type));
            $this->assertQueryTrue('is_feed');
        }
    }

    // 'page/?([0-9]{1,})/?$' => 'index.php?&paged=$matches[1]',
    public function testPaged()
    {
        for ($i=2; $i<4; $i++) {
            $this->request("/page/{$i}/");
            $this->assertQueryTrue('is_home', 'is_paged');
        }
    }

    // 'comments/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]&withcomments=1',
    // 'comments/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?&feed=$matches[1]&withcomments=1',
    public function testMainCommentsFeed()
    {
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
    public function testCommentsPage()
    {
        $this->request('/comments/page/2/');
        $this->assertQueryTrue('is_home', 'is_paged');
    }

    // 'search/(.+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?s=$matches[1]&feed=$matches[2]',
    // 'search/(.+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?s=$matches[1]&feed=$matches[2]',
    public function testSearchFeed()
    {
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
    public function testSearchPaged()
    {
        $this->request('/search/test/page/2/');
        $this->assertQueryTrue('is_search', 'is_paged');
    }

    // 'search/(.+)/?$' => 'index.php?s=$matches[1]',
    public function testSearch()
    {
        $this->request('/search/test/');
        $this->assertQueryTrue('is_search');
    }

    // 'category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
    // 'category/(.+?)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
    public function testCategoryFeed()
    {
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
    public function testCategoryPaged()
    {
        $this->request('/category/uncategorized/page/2/');
        $this->assertQueryTrue('is_archive', 'is_category', 'is_paged');
    }

    // 'category/(.+?)/?$' => 'index.php?category_name=$matches[1]',
    public function testCategory()
    {
        $this->request('/category/cat-a/');
        $this->assertQueryTrue('is_archive', 'is_category');
    }

    // 'tag/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?tag=$matches[1]&feed=$matches[2]',
    // 'tag/(.+?)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?tag=$matches[1]&feed=$matches[2]',
    public function testTagFeed()
    {
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
    public function test_tag()
    {
        $this->request('/tag/tag-a/');
        $this->assertQueryTrue('is_archive', 'is_tag');
    }

    // 'author/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?author_name=$matches[1]&feed=$matches[2]',
    // 'author/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?author_name=$matches[1]&feed=$matches[2]',
    public function testAuthorFeed()
    {
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
    public function testAuthorPaged()
    {
        $this->request('/author/user-a/page/2/');
        $this->assertQueryTrue('is_archive', 'is_author', 'is_paged');
    }

    // 'author/([^/]+)/?$' => 'index.php?author_name=$matches[1]',
    public function testAuthor()
    {
        $this->request('/author/user-a/');
        $this->assertQueryTrue('is_archive', 'is_author');
    }

    // '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
    // '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]',
    public function testYmdFeed()
    {
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
    public function testYmdPaged()
    {
        $this->request('/2007/09/04/page/2/');
        $this->assertQueryTrue('is_archive', 'is_day', 'is_date', 'is_paged');
    }

    // '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]',
    public function testYmd()
    {
        $this->request('/2007/09/04/');
        $this->assertQueryTrue('is_archive', 'is_day', 'is_date');
    }

    // '([0-9]{4})/([0-9]{1,2})/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
    // '([0-9]{4})/([0-9]{1,2})/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]',
    public function testYmFeed()
    {
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
    public function testYmPaged()
    {
        $this->request('/2007/09/page/2/');
        $this->assertQueryTrue('is_archive', 'is_date', 'is_month', 'is_paged');
    }

    // '([0-9]{4})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]',
    public function testYm()
    {
        $this->request('/2007/09/');
        $this->assertQueryTrue('is_archive', 'is_date', 'is_month');
    }

    // '([0-9]{4})/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&feed=$matches[2]',
    // '([0-9]{4})/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&feed=$matches[2]',
    public function testYFeed()
    {
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
    public function testYPaged()
    {
        $this->request('/2007/page/2/');
        $this->assertQueryTrue('is_archive', 'is_date', 'is_year', 'is_paged');
    }

    // '([0-9]{4})/?$' => 'index.php?year=$matches[1]',
    public function testY()
    {
        $this->request('/2007/');
        $this->assertQueryTrue('is_archive', 'is_date', 'is_year');
    }

    // '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/trackback/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&tb=1',
    public function testPostTrackback()
    {
        foreach ($this->getAllPostIds() as $id) {
            $permalink = get_permalink($id);
            $this->request("{$permalink}trackback/");
            $this->assertQueryTrue('is_single', 'is_singular', 'is_trackback');
        }
    }

    // '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]',
    // '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]',
    public function testPostCommentFeed()
    {
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
