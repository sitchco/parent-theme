<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Parent\Modules\KadenceImageModal\KadenceImageModal;
use Sitchco\Parent\Modules\KadenceImageModal\KadenceImageRenderer;
use Sitchco\Tests\TestCase;
use WP_HTML_Tag_Processor;

class KadenceImageModalTest extends TestCase
{
    protected KadenceImageRenderer $renderer;
    protected UIModal $uiModal;

    /** @var int[] */
    private array $createdAttachments = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = $this->container->get(KadenceImageRenderer::class);
        $this->uiModal = $this->container->get(UIModal::class);
    }

    protected function tearDown(): void
    {
        ob_start();
        $this->uiModal->unloadModals();
        ob_end_clean();

        foreach ($this->createdAttachments as $id) {
            wp_delete_attachment($id, true);
        }
        $this->createdAttachments = [];
        parent::tearDown();
    }

    private function createImageAttachment(string $alt = '', string $title = ''): int
    {
        $id = $this->factory()->attachment->create_upload_object(SITCHCO_CORE_FIXTURES_DIR . '/sample-image.jpg');
        $this->createdAttachments[] = $id;
        if ($alt !== '') {
            update_post_meta($id, '_wp_attachment_image_alt', $alt);
        }
        if ($title !== '') {
            wp_update_post(['ID' => $id, 'post_title' => $title]);
        }
        return $id;
    }

    private function makeBlock(string $html, array $attrs = []): array
    {
        return [
            'blockName' => 'kadence/image',
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    private function bareImageHtml(int $id, string $alt = '', string $extraImgClasses = ''): string
    {
        $classes = trim('kb-img wp-image-' . $id . ' ' . $extraImgClasses);
        return sprintf(
            '<figure class="wp-block-kadence-image alignnone size-full kb-image123_456"><img src="https://example.com/img.jpg" alt="%s" class="%s" width="800" height="600"></figure>',
            esc_attr($alt),
            esc_attr($classes),
        );
    }

    // --- Passthrough guards -------------------------------------------------

    public function testPassthroughWhenToggleAbsent(): void
    {
        $id = $this->createImageAttachment('Alpha');
        $html = $this->bareImageHtml($id, 'Alpha');
        $block = $this->makeBlock($html, ['id' => $id]); // openInModal absent

        $result = $this->renderer->render($html, $block);

        $this->assertSame($html, $result);
        $this->assertFalse($this->uiModal->isLoaded("img-{$id}"));
    }

    public function testPassthroughWhenToggleFalse(): void
    {
        $id = $this->createImageAttachment('Beta');
        $html = $this->bareImageHtml($id, 'Beta');
        $block = $this->makeBlock($html, ['openInModal' => false, 'id' => $id]);

        $result = $this->renderer->render($html, $block);

        $this->assertSame($html, $result);
        $this->assertFalse($this->uiModal->isLoaded("img-{$id}"));
    }

    public function testPassthroughWhenAttachmentIdZero(): void
    {
        $html = $this->bareImageHtml(0, 'Zero');
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => 0]);

        $result = $this->renderer->render($html, $block);

        $this->assertSame($html, $result);
        $this->assertFalse($this->uiModal->isLoaded('img-0'));
    }

    public function testPassthroughWhenAttachmentMissing(): void
    {
        $id = $this->factory()->attachment->create(['post_mime_type' => 'image/jpeg']);
        wp_delete_attachment($id, true);

        $html = $this->bareImageHtml($id);
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id]);

        $result = $this->renderer->render($html, $block);

        $this->assertSame($html, $result);
        $this->assertFalse($this->uiModal->isLoaded("img-{$id}"));
    }

    public function testPassthroughWhenNoTriggerClassMatches(): void
    {
        $id = $this->createImageAttachment('Alt Text');
        $html = sprintf(
            '<figure class="wp-block-kadence-image">' .
                '<img src="https://example.com/img.jpg" alt="Alt Text" ' .
                'class="wp-image-%d" width="800" height="600">' .
                '</figure>',
            $id,
        );
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => 'Alt Text']);

        $result = $this->renderer->render($html, $block);

        $this->assertSame($html, $result, 'Renderer should not decorate when no trigger class matches');
        $this->assertFalse(
            $this->uiModal->isLoaded("img-{$id}"),
            'Modal should not be queued when no trigger class matches',
        );

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'FIGURE']));
        $this->assertFalse(
            $p->has_class('has-image-modal'),
            'Root <figure> should not carry has-image-modal when no trigger class matches',
        );
    }

    public function testPassthroughForSvgAttachment(): void
    {
        $svg_id = $this->factory()->attachment->create(['post_mime_type' => 'image/svg+xml']);
        $this->createdAttachments[] = $svg_id;

        $html = $this->bareImageHtml($svg_id, 'Vector');
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $svg_id]);

        $result = $this->renderer->render($html, $block);

        $this->assertSame($html, $result);
        $this->assertFalse($this->uiModal->isLoaded("img-{$svg_id}"));
    }

    // --- Decoration paths ---------------------------------------------------

    public function testBareImageGetsRootClassDataTargetAndQueuesModal(): void
    {
        $id = $this->createImageAttachment('Sample Photo');
        $html = $this->bareImageHtml($id, 'Sample Photo');
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => 'Sample Photo']);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'FIGURE']));
        $this->assertTrue($p->has_class('has-image-modal'), 'Root <figure> should carry has-image-modal class');

        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $this->assertSame("#img-{$id}", $p->get_attribute('data-target'));

        $this->assertTrue($this->uiModal->isLoaded("img-{$id}"));

        $expected_src = wp_get_attachment_image_src($id, 'full')[0];
        ob_start();
        $this->uiModal->unloadModals();
        $footer = ob_get_clean();

        $this->assertStringContainsString("id=\"img-{$id}\"", $footer);
        $this->assertStringContainsString('class="sitchco-modal sitchco-modal--image"', $footer);
        $this->assertStringContainsString($expected_src, $footer);
        $this->assertStringNotContainsString('srcset=', $footer);
    }

    public function testKadenceAnchorReceivesDataTargetAndPreservesHref(): void
    {
        $id = $this->createImageAttachment('Linked Photo');
        $href = 'https://example.com/external';
        $html = sprintf(
            '<figure class="wp-block-kadence-image alignnone kb-image123_456">' .
                '<a href="%s" class="kb-advanced-image-link">' .
                '<img src="https://example.com/img.jpg" alt="Linked Photo" class="kb-img wp-image-%d" width="800" height="600">' .
                '</a></figure>',
            esc_url($href),
            $id,
        );
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => 'Linked Photo']);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'A', 'class_name' => 'kb-advanced-image-link']));
        $this->assertSame("#img-{$id}", $p->get_attribute('data-target'));
        $this->assertSame($href, $p->get_attribute('href'));

        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $this->assertNull(
            $p->get_attribute('data-target'),
            'Inner <img> should not carry data-target when anchor exists',
        );

        $this->assertSame(1, substr_count($result, '<a '), 'No nested anchor should be introduced');
        $this->assertTrue($this->uiModal->isLoaded("img-{$id}"));
    }

    public function testExistingAriaLabelOnAnchorIsPreserved(): void
    {
        $id = $this->createImageAttachment(); // no alt => cascade would otherwise set aria-label
        $html = sprintf(
            '<figure class="wp-block-kadence-image alignnone kb-image123_456">' .
                '<a href="https://example.com" aria-label="Custom Label" class="kb-advanced-image-link">' .
                '<img src="https://example.com/img.jpg" alt="" class="kb-img wp-image-%d" width="800" height="600">' .
                '</a></figure>',
            $id,
        );
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => '']);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'A']));
        $this->assertSame('Custom Label', $p->get_attribute('aria-label'));
        $this->assertSame("#img-{$id}", $p->get_attribute('data-target'));
    }

    public function testFalseyAriaLabelOnAnchorIsPreserved(): void
    {
        $id = $this->createImageAttachment('', 'Cascade Title');
        $html = sprintf(
            '<figure class="wp-block-kadence-image alignnone kb-image123_456">' .
                '<a href="https://example.com" aria-label="0" class="kb-advanced-image-link">' .
                '<img src="https://example.com/img.jpg" alt="" class="kb-img wp-image-%d" width="800" height="600">' .
                '</a></figure>',
            $id,
        );
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => '']);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'A']));
        $this->assertSame(
            '0',
            $p->get_attribute('aria-label'),
            'Falsey-but-present aria-label should not be overwritten by cascade',
        );
    }

    public function testKadenceWrapperReceivesDataTargetInsteadOfImg(): void
    {
        $id = $this->createImageAttachment('Wrapped');
        $html = sprintf(
            '<figure class="wp-block-kadence-image alignnone kb-image123_456 kb-image-has-overlay">' .
                '<div class="kb-is-ratio-image kb-image-ratio-land43 kb-image-has-overlay">' .
                '<img src="https://example.com/img.jpg" alt="Wrapped" class="kb-img wp-image-%d" width="800" height="600">' .
                '</div></figure>',
            $id,
        );
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => 'Wrapped']);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'FIGURE']));
        $this->assertTrue($p->has_class('has-image-modal'));

        $this->assertTrue($p->next_tag(['tag_name' => 'DIV', 'class_name' => 'kb-is-ratio-image']));
        $this->assertSame("#img-{$id}", $p->get_attribute('data-target'));

        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $this->assertNull(
            $p->get_attribute('data-target'),
            'Inner <img> should not carry data-target when wrapper present',
        );

        $this->assertTrue($this->uiModal->isLoaded("img-{$id}"));
    }

    public function testDecorativeAltGetsAriaLabelFromCascade(): void
    {
        $id = $this->createImageAttachment('', 'Cascade Title');
        $html = $this->bareImageHtml($id, '');
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => '']);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $this->assertSame('Cascade Title', $p->get_attribute('aria-label'));
        $this->assertSame("#img-{$id}", $p->get_attribute('data-target'));
    }

    public function testTitleCascadeDecodesEntitiesAndStripsTags(): void
    {
        $id = $this->createImageAttachment('', 'Placeholder');
        $filter = function ($title, $post_id) use ($id) {
            return $post_id === $id ? 'Photo &#8220;X&#8221; <em>Y</em>' : $title;
        };
        add_filter('the_title', $filter, 20, 2);

        try {
            $html = $this->bareImageHtml($id, '');
            $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => '']);

            $result = $this->renderer->render($html, $block);

            $p = new WP_HTML_Tag_Processor($result);
            $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
            $label = $p->get_attribute('aria-label');

            $this->assertStringNotContainsString('&#', $label, 'aria-label should not contain entity codes');
            $this->assertStringNotContainsString('<em>', $label, 'aria-label should not contain raw HTML tags');
            $this->assertStringContainsString(
                "\u{201C}X\u{201D}",
                $label,
                'aria-label should contain decoded typographic quotes',
            );
            $this->assertStringContainsString('Y', $label, 'aria-label should retain inner text from stripped tags');
        } finally {
            remove_filter('the_title', $filter, 20);
        }
    }

    public function testRenderedImgAltWinsOverEmptyBlockAttr(): void
    {
        $id = $this->createImageAttachment('', 'Cascade Title');
        $html = sprintf(
            '<figure class="wp-block-kadence-image alignnone kb-image123_456">' .
                '<img src="https://example.com/img.jpg" alt="Runtime Injected" class="kb-img wp-image-%d" width="800" height="600">' .
                '</figure>',
            $id,
        );
        // Simulates Kadence's globalAlt: rendered <img> carries alt that block.attrs.alt does not.
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => '']);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $this->assertNull(
            $p->get_attribute('aria-label'),
            'Trigger should not carry aria-label when rendered <img alt> is non-empty',
        );

        ob_start();
        $this->uiModal->unloadModals();
        $footer = ob_get_clean();

        $this->assertStringContainsString(
            'Runtime Injected',
            $footer,
            'Dialog heading and modal <img alt> should reflect rendered <img alt>, not stale block.attrs.alt',
        );
    }

    public function testCascadeTriggerAdoptsRegisteredHeadingUnderDedup(): void
    {
        $id = $this->createImageAttachment('', 'Title Cascade');

        $first_html = $this->bareImageHtml($id, 'First Author Alt');
        $first_block = $this->makeBlock($first_html, ['openInModal' => true, 'id' => $id, 'alt' => 'First Author Alt']);
        $this->renderer->render($first_html, $first_block);

        $second_html = $this->bareImageHtml($id, '');
        $second_block = $this->makeBlock($second_html, ['openInModal' => true, 'id' => $id, 'alt' => '']);
        $second_result = $this->renderer->render($second_html, $second_block);

        $p = new WP_HTML_Tag_Processor($second_result);
        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $this->assertSame(
            'First Author Alt',
            $p->get_attribute('aria-label'),
            'Cascade-fallback trigger should match first-registered dialog heading, not local cascade',
        );
    }

    public function testWhitespaceOnlyAltFallsThroughToCascade(): void
    {
        $id = $this->createImageAttachment('', 'Cascade Title');
        $html = $this->bareImageHtml($id, '   ');
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => '   ']);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $this->assertSame(
            'Cascade Title',
            $p->get_attribute('aria-label'),
            'Whitespace-only block alt should fall through to title cascade for aria-label',
        );

        $expected_src = wp_get_attachment_image_src($id, 'full')[0];
        ob_start();
        $this->uiModal->unloadModals();
        $footer = ob_get_clean();

        $this->assertStringContainsString('Cascade Title', $footer, 'Dialog heading should carry the cascade name');
        $this->assertStringContainsString($expected_src, $footer);
        $this->assertStringNotContainsString('alt="   "', $footer, 'Modal <img> should not carry whitespace-only alt');
    }

    public function testAlignedRootIsDecoratedAsDiv(): void
    {
        $id = $this->createImageAttachment('Aligned Photo');
        $html = sprintf(
            '<div class="wp-block-kadence-image alignleft size-full kb-image123_456">' .
                '<img src="https://example.com/img.jpg" alt="Aligned Photo" class="kb-img wp-image-%d" width="800" height="600">' .
                '</div>',
            $id,
        );
        $block = $this->makeBlock($html, [
            'openInModal' => true,
            'id' => $id,
            'alt' => 'Aligned Photo',
            'align' => 'left',
        ]);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'DIV']));
        $this->assertTrue($p->has_class('has-image-modal'), 'Aligned root <div> should carry has-image-modal class');
    }

    public function testOverlayOnlyWrapperReceivesDataTarget(): void
    {
        $id = $this->createImageAttachment('Overlay Photo');
        $html = sprintf(
            '<figure class="wp-block-kadence-image alignnone kb-image123_456">' .
                '<div class="kb-image-has-overlay">' .
                '<img src="https://example.com/img.jpg" alt="Overlay Photo" class="kb-img wp-image-%d" width="800" height="600">' .
                '</div></figure>',
            $id,
        );
        $block = $this->makeBlock($html, [
            'openInModal' => true,
            'id' => $id,
            'alt' => 'Overlay Photo',
        ]);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'DIV', 'class_name' => 'kb-image-has-overlay']));
        $this->assertSame("#img-{$id}", $p->get_attribute('data-target'));

        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $this->assertNull(
            $p->get_attribute('data-target'),
            'Inner <img> should not carry data-target when overlay wrapper present',
        );
    }

    public function testFigcaptionLinkIsNotHijackedAsTrigger(): void
    {
        $id = $this->createImageAttachment('Captioned Photo');
        $html = sprintf(
            '<figure class="wp-block-kadence-image alignnone kb-image123_456">' .
                '<img src="https://example.com/img.jpg" alt="Captioned Photo" class="kb-img wp-image-%d" width="800" height="600">' .
                '<figcaption><a href="https://example.com/source">Photo credit</a></figcaption>' .
                '</figure>',
            $id,
        );
        $block = $this->makeBlock($html, [
            'openInModal' => true,
            'id' => $id,
            'alt' => 'Captioned Photo',
        ]);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $this->assertSame("#img-{$id}", $p->get_attribute('data-target'));

        $p2 = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p2->next_tag(['tag_name' => 'A']));
        $this->assertSame('https://example.com/source', $p2->get_attribute('href'));
        $this->assertNull($p2->get_attribute('data-target'), 'Caption <a> must not be decorated as the modal trigger');

        $this->assertStringContainsString('<figcaption>', $result);
        $this->assertStringContainsString('Photo credit', $result);
    }

    public function testKadenceAnchorPreservesTargetAndRel(): void
    {
        $id = $this->createImageAttachment('Linked');
        $html = sprintf(
            '<figure class="wp-block-kadence-image alignnone kb-image123_456">' .
                '<a href="https://example.com/external" target="_blank" rel="noopener noreferrer nofollow" class="kb-advanced-image-link">' .
                '<img src="https://example.com/img.jpg" alt="Linked" class="kb-img wp-image-%d" width="800" height="600">' .
                '</a></figure>',
            $id,
        );
        $block = $this->makeBlock($html, [
            'openInModal' => true,
            'id' => $id,
            'alt' => 'Linked',
        ]);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'A', 'class_name' => 'kb-advanced-image-link']));
        $this->assertSame("#img-{$id}", $p->get_attribute('data-target'));
        $this->assertSame('_blank', $p->get_attribute('target'));
        $this->assertSame('noopener noreferrer nofollow', $p->get_attribute('rel'));
    }

    public function testCascadeFallsThroughToFilenameBasename(): void
    {
        $id = $this->createImageAttachment();
        // Clear the auto-set post_title so the cascade falls through to filename basename.
        wp_update_post(['ID' => $id, 'post_title' => '']);

        $html = $this->bareImageHtml($id, '');
        $block = $this->makeBlock($html, ['openInModal' => true, 'id' => $id, 'alt' => '']);

        $result = $this->renderer->render($html, $block);

        $p = new WP_HTML_Tag_Processor($result);
        $this->assertTrue($p->next_tag(['tag_name' => 'IMG']));
        $label = $p->get_attribute('aria-label');

        // WP's factory may append a numeric suffix to avoid filename collisions
        // (e.g. "sample-image-12.jpg"), so only the prefix and normalization are stable.
        $this->assertNotSame(
            'View full-size image',
            $label,
            'Should derive from filename basename, not fall through to step 4 literal',
        );
        $this->assertStringStartsWith('sample image', $label, 'Label should derive from sample-image.jpg basename');
        $this->assertStringNotContainsString('-', $label, 'Dashes in basename should be normalized to spaces');
        $this->assertStringNotContainsString('_', $label, 'Underscores in basename should be normalized to spaces');
    }

    public function testSameAttachmentEmitsSingleFooterDialog(): void
    {
        $id = $this->createImageAttachment('Shared', 'Shared Title');

        $first_html = $this->bareImageHtml($id, 'Shared');
        $first_block = $this->makeBlock($first_html, [
            'openInModal' => true,
            'id' => $id,
            'alt' => 'Shared',
        ]);
        $this->renderer->render($first_html, $first_block);

        $second_html = $this->bareImageHtml($id, 'Shared');
        $second_block = $this->makeBlock($second_html, [
            'openInModal' => true,
            'id' => $id,
            'alt' => 'Shared',
        ]);
        $this->renderer->render($second_html, $second_block);

        ob_start();
        $this->uiModal->unloadModals();
        $footer = ob_get_clean();

        $this->assertSame(
            1,
            substr_count($footer, '<dialog '),
            'Same attachment rendered twice should yield exactly one footer <dialog>',
        );
        $this->assertSame(1, substr_count($footer, "id=\"img-{$id}\""));
    }

    public function testFooterDialogIncludesLoadingLazyAndHeading(): void
    {
        $id = $this->createImageAttachment('Footer Heading');
        $html = $this->bareImageHtml($id, 'Footer Heading');
        $block = $this->makeBlock($html, [
            'openInModal' => true,
            'id' => $id,
            'alt' => 'Footer Heading',
        ]);

        $this->renderer->render($html, $block);

        ob_start();
        $this->uiModal->unloadModals();
        $footer = ob_get_clean();

        $this->assertStringContainsString('loading="lazy"', $footer);
        $this->assertStringContainsString('Footer Heading', $footer);
    }

    public function testRenderFilterDecoratesKadenceImageBlocks(): void
    {
        // Resolve the module so its init() registers the filter and the 'image' modal type.
        $this->container->get(KadenceImageModal::class);

        $id = $this->createImageAttachment('Filter Wired');
        $html = $this->bareImageHtml($id, 'Filter Wired');
        $block = $this->makeBlock($html, [
            'openInModal' => true,
            'id' => $id,
            'alt' => 'Filter Wired',
        ]);

        $result = apply_filters('render_block_kadence/image', $html, $block);

        $this->assertStringContainsString('has-image-modal', $result);
        $this->assertStringContainsString("data-target=\"#img-{$id}\"", $result);

        ob_start();
        $this->uiModal->unloadModals();
        $footer = ob_get_clean();
        $this->assertStringContainsString("id=\"img-{$id}\"", $footer);
    }
}
