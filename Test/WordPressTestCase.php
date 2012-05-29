<?php

/*
 * Abstract most of the unit test framework stuff, so we're not too dependent on one particular test library
 */

class WordPressTestCase extends PHPUnit_Framework_TestCase
{
    protected static $db;

    public static function setUpBeforeClass()
    {
        global $wpdb;

        static::$db = $wpdb;
        static::$db->suppress_errors = false;
        static::$db->show_errors = true;
        static::$db->db_connect();
    }

    protected function setUp()
    {
        static::cleanUpGlobalScope();
        static::startTransaction();
    }

    protected function tearDown()
    {
        static::rollback();
    }

    protected static function cleanUpGlobalScope()
    {
        $_GET = array();
        $_POST = array();
        $_REQUEST = array();

        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query();

        unset($GLOBALS['post']);

        if(function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    protected static function startTransaction()
    {
        static::$db->query('SET autocommit = 0;');
        static::$db->query('START TRANSACTION;');
    }

    protected static function rollback()
    {
        static::$db->query('ROLLBACK');
    }

    public function assertWPError( $actual, $message = '' )
    {
        $this->assertTrue( is_wp_error( $actual ), $message );
    }

    public function assertEqualFields( $object, $fields )
    {
        foreach( $fields as $field_name => $field_value ) {
            if ( $object->$field_name != $field_value ) {
                $this->fail();
            }
        }
    }

    public function assertDiscardWhitespace( $expected, $actual )
    {
        $this->assertEquals( preg_replace( '/\s*/', '', $expected ), preg_replace( '/\s*/', '', $actual ) );
    }

    public function checkAtLeastPHPVersion( $version )
    {
        if ( version_compare( PHP_VERSION, $version, '<' ) ) {
            $this->markTestSkipped();
        }
    }

    /**
     * Pretend that a given URL has been requested
     */
    protected static function request($url)
    {
        // note: the WP and WP_Query classes like to silently fetch parameters
        // from all over the place (globals, GET, etc), which makes it tricky
        // to run them more than once without very carefully clearing everything
        $_GET = $_POST = array();

        foreach (array('query_string', 'id', 'postdata', 'authordata', 'day', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'pagenow') as $v)
            unset($GLOBALS[$v]);

        $parts = parse_url($url);

        if (isset($parts['scheme'])) {
            $req = $parts['path'];
            if (isset($parts['query'])) {
                $req .= '?' . $parts['query'];
                // parse the url query vars into $_GET
                parse_str($parts['query'], $_GET);
            }
        } else {
            $req = $url;
        }

        $_SERVER['REQUEST_URI'] = $req;
        unset($_SERVER['PATH_INFO']);

        wp_cache_flush();
        unset($GLOBALS['wp_query'], $GLOBALS['wp_the_query']);
        $GLOBALS['wp_the_query'] =& new WP_Query();
        $GLOBALS['wp_query'] =& $GLOBALS['wp_the_query'];
        $GLOBALS['wp'] =& new WP();

        // clean out globals to stop them polluting wp and wp_query
        foreach ($GLOBALS['wp']->public_query_vars as $v) {
            unset($GLOBALS[$v]);
        }
        foreach ($GLOBALS['wp']->private_query_vars as $v) {
            unset($GLOBALS[$v]);
        }

        $GLOBALS['wp']->main(isset($parts['query']) ? $parts['query'] : '');
    }

    /**
     * Insert a given number of trivial posts, each with predictable title, content and excerpt
     */
    protected static function insertQuickPosts($num = 1, $type = 'post', $more = array(), $terms = array())
    {
        if(!$num)   $num    = 1;
        if(!$type)  $type = 'post';
        if(!$more)  $more   = array();
        if(!$terms) $terms  = array();

        $ids = array();

        for ($i=0; $i<$num; $i++) {
            $data = array_merge(array(
                'post_author' => 1,
                'post_status' => 'publish',
                'post_title' => "{$type} title {$i}",
                'post_content' => "{$type} content {$i}",
                'post_excerpt' => "{$type} excerpt {$i}",
                'post_type' => $type
            ), $more);

            $id = $ids[$i] = wp_insert_post($data);

            if(!empty($terms)) {
                foreach ($terms as $taxonomy => $terms) {
                    wp_set_object_terms($id, $terms, $taxonomy);
                }
            }
        }

        return $ids;
    }

    /**
     * Insert a given number of trivial pages, each with predictable title, content and excerpt
     */
    protected static function insertQuickPages($num, $more = array())
    {
        static::insertQuickPosts($num, 'page', $more);
    }

    protected static function insertQuickComments($post_id, $num=3)
    {
        for ($i=0; $i<$num; $i++) {
            wp_insert_comment(array(
                'comment_post_ID' => $post_id,
                'comment_author' => "Commenter $i",
                'comment_author_url' => "http://example.com/$i/",
                'comment_approved' => 1,
                ));
        }
    }

    protected static function nukeMainTables()
    {
        // crude but effective: make sure there's no residual data in the main tables
        foreach ( array('posts', 'postmeta', 'comments', 'terms', 'term_taxonomy', 'term_relationships', 'users', 'usermeta') as $table)
            static::$db->query("DELETE FROM ".static::$db->$table);
    }

    /**
     * Import data from SQL
     */
    protected static function loadSqlDump($file)
    {
        $lines = file($file);

        foreach ($lines as $line) {
            $line = trim( $line );

            if ( empty( $line ) || 0 === strpos( $line, '--' ) || 0 === strpos( $line, '/*' ) || 0 === strpos($line, 'LOCK TABLES ') )
                continue;

            static::$db->query($line);
        }
    }


    /**
     * Add a user of the specified type
     */
    protected static function createUser($role = 'administrator', $username = '', $password='', $email='')
    {
        if (!$username)
            $username = static::randomString();

        if (!$password)
            $pass = static::randomString();

        if (!$email)
            $email = static::randomString().'@example.com';

        $id = wp_create_user($username, $password, $email);

        $user = new WP_User($id);
        $user->set_role($role);

        return $id;
    }

    protected static function deleteUser($id)
    {
        return wp_delete_user($id);
    }

    protected static function randomString($len=32)
    {
        return substr(md5(uniqid(rand())), 0, $len);
    }


}