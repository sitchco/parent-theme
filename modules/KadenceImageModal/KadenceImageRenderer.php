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

        $trigger_query = $this->findTriggerQuery($content);
        if ($trigger_query === null) {
            return $content;
        }

        $modal_id = "img-{$attachment_id}";
        $alt = $this->readRenderedAlt($content);
        $name = $this->resolveAccessibleName($alt, $attachment_id, $full[0]);

        $modal_data = new ModalData($modal_id, $name, $this->buildImg($full, $alt), 'image');
        // Use the registered heading so an empty-alt trigger's synthesized aria-label
        // matches the (first-write-wins) dialog when the same attachment is reused.
        $registered = $this->uiModal->loadModal($modal_data) ?? $modal_data;

        return $this->decorate($content, $block, $trigger_query, $modal_id, $alt, $registered->heading());
    }

    private function triggerQueries(): array
    {
        return [
            ['tag_name' => 'A', 'class_name' => 'kb-advanced-image-link'],
            ['class_name' => 'kb-is-ratio-image'],
            ['class_name' => 'kb-image-has-overlay'],
            ['tag_name' => 'IMG', 'class_name' => 'kb-img'],
        ];
    }

    private function findTriggerQuery(string $content): ?array
    {
        foreach ($this->triggerQueries() as $query) {
            $p = new WP_HTML_Tag_Processor($content);
            if ($p->next_tag($query)) {
                return $query;
            }
        }
        return null;
    }

    private function readRenderedAlt(string $content): string
    {
        $p = new WP_HTML_Tag_Processor($content);
        if (!$p->next_tag(['tag_name' => 'IMG', 'class_name' => 'kb-img'])) {
            return '';
        }
        return trim((string) ($p->get_attribute('alt') ?? ''));
    }

    private function resolveAccessibleName(string $alt, int $attachment_id, string $full_url): string
    {
        if ($alt !== '') {
            return $alt;
        }
        $title = sanitize_text_field(html_entity_decode(get_the_title($attachment_id), ENT_QUOTES, 'UTF-8'));
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
        return Str::wrapElement('', 'img', [
            'src' => esc_url($url),
            'alt' => $alt,
            'width' => $width > 0 ? (int) $width : null,
            'height' => $height > 0 ? (int) $height : null,
            'loading' => 'lazy',
            'decoding' => 'async',
            'class' => 'sitchco-image-modal__img',
        ]);
    }

    private function decorate(
        string $content,
        array $block,
        array $trigger_query,
        string $modal_id,
        string $alt,
        string $name,
    ): string {
        $align = (string) ($block['attrs']['align'] ?? '');
        $root_tag = in_array($align, ['left', 'right', 'center'], true) ? 'DIV' : 'FIGURE';

        $p = new WP_HTML_Tag_Processor($content);
        if ($p->next_tag(['tag_name' => $root_tag])) {
            $p->add_class('has-image-modal');
            $content = $p->get_updated_html();
        }

        return $this->decorateTrigger($content, $trigger_query, $modal_id, $alt, $name) ?? $content;
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
        if ($alt === '' && $p->get_attribute('aria-label') === null) {
            $p->set_attribute('aria-label', $name);
        }
        return $p->get_updated_html();
    }
}
