<?php

namespace Sitchco\Parent\Modules\KadenceImageModal;

use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Utils\Str;
use WP_HTML_Tag_Processor;

readonly class KadenceImageRenderer
{
    public function __construct(private UIModal $uiModal) {}

    public function render(string $content, array $block): string
    {
        if (empty($block['attrs']['openInModal'])) {
            return $content;
        }

        $attachment_id = (int) ($block['attrs']['id'] ?? 0);
        if (!$attachment_id) {
            return $content;
        }

        if (get_post_mime_type($attachment_id) === 'image/svg+xml') {
            return $content;
        }

        $full = wp_get_attachment_image_src($attachment_id, 'full');
        if (!$full) {
            return $content;
        }

        $modal_id = "img-{$attachment_id}";
        $alt = (string) ($block['attrs']['alt'] ?? '');
        $name = $this->resolveAccessibleName($alt, $attachment_id, $full[0]);

        $modal_data = new ModalData($modal_id, $name, $this->buildImg($full, $alt), 'image');
        $this->uiModal->loadModal($modal_data);

        return $this->decorate($content, $block, $modal_id, $alt, $name);
    }

    private function resolveAccessibleName(string $alt, int $attachment_id, string $full_url): string
    {
        if ($alt !== '') {
            return $alt;
        }
        $title = get_the_title($attachment_id);
        if ($title !== '') {
            return $title;
        }
        $bare = pathinfo(wp_basename($full_url), PATHINFO_FILENAME);
        if ($bare !== '') {
            return sanitize_text_field(str_replace(['-', '_'], ' ', $bare));
        }
        return 'View full-size image';
    }

    private function buildImg(array $full, string $alt): string
    {
        [$url, $width, $height] = $full;
        return Str::wrapElement(
            '',
            'img',
            array_filter(
                [
                    'src' => esc_url($url),
                    'alt' => esc_attr($alt),
                    'width' => $width > 0 ? (int) $width : null,
                    'height' => $height > 0 ? (int) $height : null,
                    'loading' => 'lazy',
                    'decoding' => 'async',
                    'class' => 'sitchco-image-modal__img',
                ],
                fn($v) => $v !== null,
            ),
        );
    }

    private function decorate(string $content, array $block, string $modal_id, string $alt, string $name): string
    {
        $align = (string) ($block['attrs']['align'] ?? '');
        $root_tag = in_array($align, ['left', 'right', 'center'], true) ? 'DIV' : 'FIGURE';

        $p = new WP_HTML_Tag_Processor($content);
        if ($p->next_tag(['tag_name' => $root_tag])) {
            $p->add_class('has-image-modal');
            $content = $p->get_updated_html();
        }

        $triggerQueries = [
            ['tag_name' => 'A', 'class_name' => 'kb-advanced-image-link'],
            ['class_name' => 'kb-is-ratio-image'],
            ['class_name' => 'kb-image-has-overlay'],
            ['tag_name' => 'IMG', 'class_name' => 'kb-img'],
        ];
        foreach ($triggerQueries as $query) {
            $decorated = $this->decorateTrigger($content, $query, $modal_id, $alt, $name);
            if ($decorated !== null) {
                return $decorated;
            }
        }
        return $content;
    }

    private function decorateTrigger(
        string $content,
        array $query,
        string $modal_id,
        string $alt,
        string $name,
    ): ?string {
        $p = new WP_HTML_Tag_Processor($content);
        if (!$p->next_tag($query)) {
            return null;
        }
        $p->set_attribute('data-target', '#' . $modal_id);
        if ($alt === '' && !$p->get_attribute('aria-label')) {
            $p->set_attribute('aria-label', $name);
        }
        return $p->get_updated_html();
    }
}
