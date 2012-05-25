<?php

class IsEmailTest extends WpTestCase
{
    public function test_is_email_only_letters_with_dot_com_domain()
    {
        $this->assertEquals( 'nb@nikolay.com', is_email( 'nb@nikolay.com' ) );
    }

    public function test_is_email_should_not_allow_missing_tld()
    {
        $this->assertFalse( is_email( 'nb@nikolay' ) );
    }

    public function test_is_email_should_allow_bg_domain()
    {
        $this->assertEquals( 'nb@nikolay.bg', is_email( 'nb@nikolay.bg' ) );
    }

    public function test_is_email_should_not_allow_blah_domain()
    {
        // $this->assertFalse( is_email( 'nb@nikolay.blah' ) );
    }
}
