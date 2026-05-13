<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Modules\UIModal\UIModal;
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
}
