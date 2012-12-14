<?php

namespace Stamina\Tests\PhpQuery;

use phpQuery;
use PHPUnit_Framework_TestCase;
use Stamina\PhpQuery\Sanitizer;

/**
 */
class SanitizerTest extends PHPUnit_Framework_TestCase {


    protected function setUp() {
    }

    protected function tearDown() {
        
    }

    /**
     * @dataProvider sanitizeProvider
     */
    public function testSanitize(Sanitizer $sanitizer, $html, $expected, $error = 'Different HTML', $innerSelector = false) {
        if (is_string($html)) $html = phpQuery::newDocumentXHTML($html);
        $sanitizer->sanitize($innerSelector ? $html->find($innerSelector) : $html);
//        $this->assertEquals($this->skipWhitespace($expected), $this->skipWhitespace($html->html()), 'Different HTML');
        $actual = $html->html();
        if ($actual && $expected && strpos($actual, '<') !== false && strpos($expected, '<') !== false) $this->assertXmlStringEqualsXmlString($expected, $actual, $error);
        else $this->assertEquals($expected, $actual);
    }
    
    protected function skipWhitespace($html) {
        $html = trim($html);
        $html = preg_replace('/^[ \t]+/m', '', $html);
        $html = preg_replace('/[ \t]+/', ' ', $html);
        $html = preg_replace('/(\r?\n)+/', "\n", $html);
        return $html;
    }

    public function sanitizeProvider() {
        $list = array();
        
        $html = '<html>
            <head>
                 <title>Test</title>
                 <meta name="description" content="" />
            </head>
            <body>
                Some <b>simple</b> <a href="/html" name="link" class="link int">html</a><br />
                <h2>Some <i>section</i><a name="section" /></h2>
                <p style="margin:10px; font-size:20px;" align="left" id="article">
                    <ul style="list-style:none;" class="list important" id="list">
                        <li><font color="red">1</font></li>
                        <li><u>2</u></li>
                        <li><img src="some.png" border="1" /></li>
                    </ul>
                </p>
                <span class="hidden"><h2>Another section</h2></span>
                <a href="//a.com" rel="nofollow"><img src="//a.com/some.png" /> link</a><br />
                <h3>Subsection</h3>
                <script>
                    alert("A little bit of <test>scripting!</test>");
                </script>
                <span class="text">And <b>some <u> deep text</u></b></span>
            </body></html>';
        
        $s = new Sanitizer();
        $s->addRemoveTags('head *:not(title)');
        $s->addRemoveTags('a[name]:not([href]),.hidden,script,style,object');
        $s->addUnwrapTags('a[href*="//"]');
        $s->addUnwrapTags(['body *', ['deny' => '/^(a|b|i|u|p|h\d|span|ul|ol|li|br)$/']]);
        $s->addFilterAttributes(true, ['href', 'class', 'id', 'style', 'rel'], ['important', 'link'], ['list-style'], ['article']);
        
        $list['Sanitized HTML'] = [$s, $html, 
            '<html>
            <head>
                 <title>Test</title>
            </head>
            <body>
                Some <b>simple</b> <a href="/html" class="link">html</a><br />
                <h2>Some <i>section</i></h2>
                <p id="article">
                    <ul style="list-style:none" class="important">
                        <li>1</li>
                        <li><u>2</u></li>
                        <li></li>
                    </ul>
                </p>
                
                 link<br />
                <h3>Subsection</h3>
                
                <span>And <b>some <u> deep text</u></b></span>
            </body></html>'];
        
        $s = new Sanitizer();
        $s->addRemoveTags('head');
        $s->addRemoveTags('a[name]:not([href]),.hidden,script,style,object');
        $s->addUnwrapTags('html');
        $s->addUnwrapTags(['body *', ['deny' => '/^(a|b|i|u|p|h\d|ul|ol|li|br)$/']]);
        $s->addFilterAttributes(true, ['href']);
        $list['Simple article'] = [$s, $html, 
            '<body>
                Some <b>simple</b> <a href="/html">html</a><br />
                <h2>Some <i>section</i></h2>
                <p>
                    <ul>
                        <li>1</li>
                        <li><u>2</u></li>
                        <li></li>
                    </ul>
                </p>
                
                <a href="//a.com"> link</a><br />
                <h3>Subsection</h3>
                
                And <b>some <u> deep text</u></b>
            </body>'];

        
        return $list;
    }
    
    public function testHtmlClone() {
        $doc = phpQuery::newDocument('<body>Some <b>simple</b> <abbr title="HTML">html</abbr></body>');
        
        $s = new Sanitizer();
        $s->addRemoveTags('b');
        $s->addFilterAttributes('*', false);
        $docClone = $doc->clone();
        $s->sanitize($docClone);
        
        $this->assertEquals('Some  <abbr>html</abbr>', trim($docClone->html()));
        $this->assertEquals('Some <b>simple</b> <abbr title="HTML">html</abbr>', trim($doc->html()), 'Should be the original!');
    }
    
    /**
     * @covers Stamina\Sanitizer::eachSelector
     * @todo   Implement testEachSelector().
     */
    public function testEachSelector() {
        $s = new Sanitizer();
        $s->addRemoveTags(true);
        $this->testSanitize($s, '<body>Test <a>1</a> <b>some</b><a>2</a></body>', 
                                '<body>Test  </body>', 'using all', 'body');
        
        $s = new Sanitizer();
        $s->addRemoveTags('a,b>c');
        $this->testSanitize($s, '<body>Test <a>1</a> <b>some<c>3</c></b><a>2</a></body>', 
                                '<body>Test  <b>some</b></body>', 'using selector');
        
        $s = new Sanitizer();
        $s->addRemoveTags(function($pq) { return $pq->find('a'); });
        $this->testSanitize($s, '<body>Test <a>1</a> <b>some</b><a>2</a></body>', 
                                '<body>Test  <b>some</b></body>', 'using callback');
        
        $s = new Sanitizer();
        $s->addRemoveTags(array(true, '/^a$/'));
        $this->testSanitize($s, '<body>Test <a>1</a> <b>some</b><a>2</a></body>', 
                                '<body>Test  <b>some</b></body>', 'using filter', 'body');
        
        $s = new Sanitizer();
        $s->addRemoveTags(array(true, array('deny' => '/^b|body$/')));
        $this->testSanitize($s, '<body>Test <a>1</a> <b>some</b><a>2</a></body>', 
                                '<body>Test  <b>some</b></body>', 'using deny filter', 'body');
        
        
        $s = new Sanitizer();
        $s->addRemoveTags(array(true, function($i, $node) { return $node->tagName == 'a'; }));
        $this->testSanitize($s, '<body>Test <a>1</a> <b>some</b><a>2</a></body>', 
                                '<body>Test  <b>some</b></body>', 'using filter callback', 'body');
        
//        $s = new Sanitizer();
//        $s->addRemoveTags([true]);
//        $this->testSanitize($s, '<body>Test <a>1</a> <b>some</b><a>2</a></body>', 
//                                '<body>Test  <b>some</b></body>');
    }

    /**
     */
    public function testRemoveTags() {
        
        $s = new Sanitizer();
        $s->addRemoveTags('a');
        $this->testSanitize($s, '<body>Test <a>123<b>456</b></a> <b>some</b></body>', '<body>Test  <b>some</b></body>');
        
    }

    /**
     */
    public function testUnwrapTags() {
        $s = new Sanitizer();
        $s->addUnwrapTags('a');
        $this->testSanitize($s, '<body>Test <a>123<b>456</b></a> <b>some</b></body>', '<body>Test 123<b>456</b> <b>some</b></body>');
    }

    /**
     */
    public function testFilterAttributes_attributes() {
        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', false);
        $this->testSanitize($s, '<body><a rel="no" href="#" class="link ext">123<b class="bold" style="font-weight:bold;">456</b></a></body>', 
                                '<body><a>123<b>456</b></a></body>');
        
        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', '/href/');
        $this->testSanitize($s, '<body><a rel="no" href="#" class="link ext">123<b class="bold" style="font-weight:bold;">456</b></a></body>', 
                                '<body><a href="#">123<b>456</b></a></body>');
        
        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', ['class']);
        $this->testSanitize($s, '<body><a rel="no" href="#" class="link ext">123<b class="bold" style="font-weight:bold;">456</b></a></body>', 
                                '<body><a class="link ext">123<b class="bold">456</b></a></body>');
        
        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', ['deny' => ['style','class']]);
        $this->testSanitize($s, '<body><a rel="no" href="#" class="link ext">123<b class="bold" style="font-weight:bold;">456</b></a></body>', 
                                '<body><a rel="no" href="#">123<b>456</b></a></body>');
    }
    
    /**
     */
    public function testFilterAttributes_classes() {
        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, false);
        $this->testSanitize($s, '<body><a rel="no" href="#" class="link ext">123<b class="bold" style="font-weight:bold;">456</b></a></body>', 
                                '<body><a rel="no" href="#">123<b style="font-weight:bold;">456</b></a></body>');

        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, '/link/');
        $this->testSanitize($s, '<body><a rel="no" href="#" class="link  ext ">123<b class="bold  some more" style="font-weight:bold;">456</b></a></body>', 
                                '<body><a rel="no" href="#" class="link">123<b style="font-weight:bold;">456</b></a></body>');
        
        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, ['link', 'bold']);
        $this->testSanitize($s, '<body><a rel="no" href="#" class="link  ext">123<b class=" bold " style="font-weight:bold;">456</b></a></body>', 
                                '<body><a rel="no" href="#" class="link">123<b class="bold" style="font-weight:bold;">456</b></a></body>');
        
        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, ['deny' => '/link/']);
        $this->testSanitize($s, '<body><a rel="no" href="#" class="link ext">123<b class=" bold" style="font-weight:bold;">456</b></a></body>', 
                                '<body><a rel="no" href="#" class="ext">123<b class="bold" style="font-weight:bold;">456</b></a></body>');
    }
    
    /**
     */
    public function testFilterAttributes_styles() {
        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, null, false);
        $this->testSanitize($s, '<body><a class="link ext" style="margin:20px; color:#222">123<b class="bold" style="font-weight:bold; badstyle;">456</b></a></body>', 
                                '<body><a class="link ext">123<b class="bold">456</b></a></body>');

        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, null, '/^color$/');
        $this->testSanitize($s, '<body><a class="link ext" style="color-margin:20px; color:#222 ; badstyle">123<b class="bold" style="font-weight:bold; ;">456</b></a></body>', 
                                '<body><a class="link ext" style="color:#222">123<b class="bold">456</b></a></body>');

        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, null, ['margin', 'color', null]);
        $this->testSanitize($s, '<body><a class="link ext" style="margin:20px; color:#222; badstyle">123<b class="bold" style="font-weight:bold;">456</b></a></body>', 
                                '<body><a class="link ext" style="margin:20px; color:#222; badstyle">123<b class="bold">456</b></a></body>');

        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, null, ['deny' => '/^font-/']);
        $this->testSanitize($s, '<body><a class="link ext" style="margin:20px; font-color:#222; some-font-style:true;">123<b class="bold" style="font-weight:bold;">456</b></a></body>', 
                                '<body><a class="link ext" style="margin:20px; some-font-style:true">123<b class="bold">456</b></a></body>');

        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, null, ['deny' => '/color|active|letter/']);
        $this->testSanitize($s, '<body><a class="link ext" style="{font-weight: bold}               /* {a:b} */
                                                                    :active {color: green; font-weight:bold;}            /* a=1 b=1 c=0 */
                                                                    :first-letter {color: #369}       /* a=1 b=0 c=1 */
                                                                    :first-letter:active {width:auto;color: red} /* a=1 b=1 c=1 */"></a></body>', 
                                '<body><a class="link ext" style="{font-weight: bold}               
                                                                    :active {font-weight:bold}            
                                                                    :first-letter {}       
                                                                    :first-letter:active {width:auto} "></a></body>');

    }

    
    /**
     */
    public function testFilterAttributes_ids() {
        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, null, null, false);
        $this->testSanitize($s, '<body><a rel="no" id="Test1">123<b id="test2">456</b></a></body>', 
                                '<body><a rel="no">123<b>456</b></a></body>');

        $s = new Sanitizer();
        $s->addFilterAttributes('a,b', null, null, null, '/Test/');
        $this->testSanitize($s, '<body><a rel="no" id="Test1">123<b id="test2">456</b></a></body>', 
                                '<body><a rel="no" id="Test1">123<b>456</b></a></body>');

    }    
    
    /** @dataProvider checkFilterProvider */
    public function testCheckFilter($expected, $value, $filter) {
        $s = new Sanitizer();
        $this->assertEquals($expected, $s->checkFilter($value, $filter));
    }
    
    public function checkFilterProvider() {
        return [
            [true, 'a', true],
            [false, 'a', false],
            [true, 'href', '/href/'],
            [true, 'href', '/ref/'],
            [true, 'href', ['href']],
            [false, 'href', ['ref', 'hre']],
            [false, 'href', ['deny' => '/href/']],
            [false, 'href', ['deny' => '/ref/']],
            [false, 'href', ['deny' => ['href']]],
            [true, 'href', ['deny' => ['ref']]],
        ];
    }
    
}
