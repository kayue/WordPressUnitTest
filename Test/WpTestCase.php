<?php

/*
 * Abstract most of the unit test framework stuff, so we're not too dependent on one particular test library
 */

class WpTestCase extends PHPUnit_Framework_TestCase {
	
	static protected $db;

    public static function setUpBeforeClass()
    {
		global $wpdb;

		static::$db = $wpdb;
		static::$db->suppress_errors = false;
		static::$db->show_errors = true;
		static::$db->db_connect();
    }

	protected function setUp() {
		$this->setupErrorHandler();
		static::cleanUpGlobalScope();

		static::startTransaction();
	}

	protected function tearDown() 
	{
		static::rollback();
	}

	private function setupErrorHandler() 
	{
		$handler = set_error_handler(array(&$this, 'errorHandler'));

		if (is_null($handler)) {
			restore_error_handler();
		}
	}

	/*
	 * Treat any error, which wasn't handled by PHPUnit as a failure
	 */
	function errorHandler($errno, $errstr, $errfile, $errline) 
	{
		// @ in front of statement
		if (error_reporting() == 0) {
			return;
		}
		
		// notices and strict warnings are passed on to the phpunit error handler but don't trigger an exception
		if ($errno | E_USER_ERROR | E_NOTICE | E_STRICT) {
			PHPUnit_Util_ErrorHandler::handleError($errno, $errstr, $errfile, $errline);
		}
		// warnings and errors trigger an exception, which is included in the test results
		else {
			error_log("Testing: $errstr in $errfile on line $errline");

			//TODO: we should raise custom exception here, sth like WP_PHPError
			throw new PHPUnit_Framework_Error(
				$errstr,
				$errno,
				$errfile,
				$errline,
				$trace
			);
		}
	}

	static protected function cleanUpGlobalScope() 
	{
		$_GET = array();
		$_POST = array();
		$_REQUEST = array();

		if(function_exists('wp_cache_flush')) {
			wp_cache_flush();
		}
	}

	static protected function startTransaction() 
	{
		static::$db->query('SET autocommit = 0;');
		static::$db->query('START TRANSACTION;');
	}

	static protected function rollback() 
	{
		static::$db->query('ROLLBACK');
	}

	function assertWPError( $actual, $message = '' ) {
		$this->assertTrue( is_wp_error( $actual ), $message );
	}

	function assertEqualFields( $object, $fields ) {
		foreach( $fields as $field_name => $field_value ) {
			if ( $object->$field_name != $field_value ) {
				$this->fail();
			}
		}
	}

	function assertDiscardWhitespace( $expected, $actual ) {
		$this->assertEquals( preg_replace( '/\s*/', '', $expected ), preg_replace( '/\s*/', '', $actual ) );
	}

	function checkAtLeastPHPVersion( $version ) 
	{
		if ( version_compare( PHP_VERSION, $version, '<' ) ) {
			$this->markTestSkipped();
		}
	}

	/**
	 * Pretend that a given URL has been requested
	 */
	static protected function request($url) 
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
		}
		else {
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
	static protected function insertQuickPosts($num, $type='post', $more = array()) 
	{
		for ($i=0; $i<$num; $i++) {
			wp_insert_post(array_merge(array(
				'post_author' => 1,
				'post_status' => 'publish',
				'post_title' => "{$type} title {$i}",
				'post_content' => "{$type} content {$i}",
				'post_excerpt' => "{$type} excerpt {$i}",
				'post_type' => $type
			), $more));
		}
	}

	/**
	 * Insert a given number of trivial pages, each with predictable title, content and excerpt
	 */
	static protected function insertQuickPages($num, $more = array()) 
	{
		static::insertQuickPosts($num, 'page', $more);
	}

	static protected function insertQuickComments($post_id, $num=3) 
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

	/**
	 *
	 */
	static protected function loadSqlDump($file) 
	{
		$lines = file($file);

		foreach ($lines as $line) {
			$line = trim( $line );
			
			if ( empty( $line ) || 0 === strpos( $line, '--' ) || 0 === strpos( $line, '/*' ) || 0 === strpos($line, 'LOCK TABLES ') )
				continue;

			static::$db->query($line);
		}
	}

}