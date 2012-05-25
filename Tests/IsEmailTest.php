<?php

class IsEmailTest extends WpTestCase
{
    public function testIsEmailOnlyLettersWithDotComDomain()
    {
        $this->assertEquals( 'nb@nikolay.com', is_email( 'nb@nikolay.com' ) );
    }

    public function testIsEmailShouldNotAllowMissingTld()
    {
        $this->assertFalse( is_email( 'nb@nikolay' ) );
    }

    public function testIsEmailShouldAllowBgDomain()
    {
        $this->assertEquals( 'nb@nikolay.bg', is_email( 'nb@nikolay.bg' ) );
    }
}
