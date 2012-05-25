<?php

class TexturizeTest extends WpTestCase
{
    public function testSontTexturizeDashesInCode()
    {
        $this->assertEquals( '<code>---</code>', wptexturize( '<code>---</code>' ) );
    }

    public function testSontTexturizeDashesInPre()
    {
        $this->assertEquals( '<pre>---</pre>', wptexturize( '<pre>---</pre>' ) );
    }

    public function testSontTexturizeCodeInsideAPre()
    {
        $double_nest = '<pre>"baba"<code>"baba"<pre></pre></code>"baba"</pre>';
        $this->assertEquals( $double_nest, wptexturize( $double_nest ) );
    }

    public function testSontTexturizePreInsideACode()
    {
        $double_nest = '<code>"baba"<pre>"baba"<code></code></pre>"baba"</code>';
        $this->assertEquals( $double_nest, wptexturize( $double_nest ) );
    }

}
