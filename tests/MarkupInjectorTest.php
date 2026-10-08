<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\Animation\MarkupInjector;
use Sitchco\Tests\TestCase;

/**
 * Where an animation's markup lands. Pure string in, string out; the framework's choice of when to
 * call it is AnimationFrameworkModuleTest's.
 */
class MarkupInjectorTest extends TestCase
{
    private const MARKUP = '<i class="glyph"></i>';

    public function testInsertsAsTheFirstChildOfANestedHost(): void
    {
        $this->assertSame(
            '<div class="outer"><div class="x inner">' . self::MARKUP . 'content</div></div>',
            MarkupInjector::inject('<div class="outer"><div class="x inner">content</div></div>', self::MARKUP, [
                'inner',
            ]),
        );
    }

    /**
     * A row's rendered HTML holds its columns. The row's own wrapper comes first in the document,
     * so it hosts the markup even though a column carries the class listed first.
     */
    public function testTheFirstHostInDocumentOrderWinsOverTheOrderOfTheList(): void
    {
        $row = '<div class="row"><div class="wrap"><div class="col"><div class="inner">a</div></div></div></div>';

        $this->assertSame(
            '<div class="row"><div class="wrap">' .
                self::MARKUP .
                '<div class="col"><div class="inner">a</div></div></div></div>',
            MarkupInjector::inject($row, self::MARKUP, ['inner', 'wrap']),
        );
    }

    public function testWithoutHostsTheOutermostElementHostsIt(): void
    {
        $this->assertSame(
            "<!-- note -->\n<section class=\"x\">" . self::MARKUP . 'content</section>',
            MarkupInjector::inject("<!-- note -->\n<section class=\"x\">content</section>", self::MARKUP),
        );
    }

    /**
     * @dataProvider hostlessProvider
     */
    public function testReturnsNullWhenNothingCanHostIt(string $html, array $hosts): void
    {
        $this->assertNull(MarkupInjector::inject($html, self::MARKUP, $hosts));
    }

    public static function hostlessProvider(): array
    {
        return [
            'no element with the class' => ['<div class="outer">content</div>', ['inner']],
            'class only in text' => ['<div>inner</div>', ['inner']],
            'void outermost element' => ['<img src="x.png">', []],
            'void host' => ['<div><img class="inner" src="x.png"></div>', ['inner']],
            'content opening with text' => ['text <div>content</div>', []],
            'empty' => ['', []],
        ];
    }

    public function testLeavesEverythingElseByteForByte(): void
    {
        $html = "<div  class='inner'  data-x=\"1\">\n  <p>content</p>\n</div>";

        $this->assertSame(
            "<div  class='inner'  data-x=\"1\">" . self::MARKUP . "\n  <p>content</p>\n</div>",
            MarkupInjector::inject($html, self::MARKUP, ['inner']),
        );
    }
}
