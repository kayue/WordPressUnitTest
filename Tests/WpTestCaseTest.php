<?php

class WordPressTestCaseTest extends WordPressTestCase
{
    public function testInsertPost()
    {
        $query = new WP_Query(array('posts_per_page' => -1));
        $originalCount = count($query->posts);

        static::insertQuickPosts(10);

        $query = new WP_Query(array('posts_per_page' => -1));
        $newCount = count($query->posts);

        $this->assertEquals($originalCount + 10, $newCount, 'There should be 10+n posts.');
    }

    public function testLoadSqlDump()
    {
        static::nukeMainTables();
        $this->loadSqlDump(dirname(__FILE__).'/../Resources/Data/asdftestblog1.2007-11-23.fixed.sql');

        $query = new WP_Query(array('posts_per_page' => -1));
        $count = count($query->posts);

        $this->assertEquals(39, $count, 'There should be 39 posts in database.');
    }

    public function testRollback()
    {
        $query = new WP_Query(array('posts_per_page' => -1));
        $count = count($query->posts);

        $this->assertEquals(1, $count, 'There should be only one post in database.');
    }
}
