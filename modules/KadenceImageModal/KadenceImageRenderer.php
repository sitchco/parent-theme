<?php

namespace Sitchco\Parent\Modules\KadenceImageModal;

use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Utils\Str;
use WP_HTML_Tag_Processor;

readonly class KadenceImageRenderer
{
    public function __construct(private UIModal $uiModal, private SvgSprite $svgSprite) {}

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

        $align = (string) ($block['attrs']['align'] ?? '');
        $root_tag = in_array($align, ['left', 'right', 'center'], true) ? 'DIV' : 'FIGURE';

        $p = new WP_HTML_Tag_Processor($content);
        ['root_bm' => $root_bm, 'alt' => $alt, 'trigger_bms' => $trigger_bms] = $this->walkAndBookmark($p, $root_tag);

        if (!$trigger_bms) {
            return $content;
        }

        $modal_id = "img-{$attachment_id}";
        $name = $this->resolveAccessibleName($alt, $attachment_id, $full[0]);
        $modal_data = new ModalData($modal_id, $name, $this->buildImg($full, $alt), 'image');
        // Use the registered heading so an empty-alt trigger's synthesized aria-label
        // matches the (first-write-wins) dialog when the same attachment is reused.
        $registered = $this->uiModal->loadModal($modal_data) ?? $modal_data;

        if ($root_bm) {
            $p->seek('root');
            $p->add_class('has-image-modal');
        }

        ksort($trigger_bms);
        $winning = array_key_first($trigger_bms);
        $p->seek("trig-{$winning}");
        $p->set_attribute('data-target', '#' . $modal_id);
        if ($alt === '' && $p->get_attribute('aria-label') === null) {
            $p->set_attribute('aria-label', $registered->heading());
        }

        $html = $p->get_updated_html();

        $icon_html = $this->svgSprite->renderIcon('image', null, ['sitchco-image-modal__icon-overlay']);
        $icon_html = apply_filters(KadenceImageModal::hookName('icon_svg'), $icon_html, $registered, $block);

        if ($icon_html === '') {
            return $html;
        }

        $close = '</' . strtolower($root_tag) . '>';
        $pos = strrpos($html, $close);
        if ($pos === false) {
            return $html;
        }

        return substr_replace($html, $icon_html, $pos, 0);
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

    private function walkAndBookmark(WP_HTML_Tag_Processor $p, string $root_tag): array
    {
        $root_bm = false;
        $alt = '';
        $alt_seen = false;
        $trigger_bms = [];
        $queries = $this->triggerQueries();

        while ($p->next_tag()) {
            $tag = $p->get_tag();

            if (!$root_bm && $tag === $root_tag) {
                $p->set_bookmark('root');
                $root_bm = true;
            }

            if (!$alt_seen && $tag === 'IMG' && $p->has_class('kb-img')) {
                $alt = trim((string) ($p->get_attribute('alt') ?? ''));
                $alt_seen = true;
            }

            foreach ($queries as $i => $q) {
                if (isset($trigger_bms[$i])) {
                    continue;
                }
                if (isset($q['tag_name']) && $tag !== $q['tag_name']) {
                    continue;
                }
                if (isset($q['class_name']) && !$p->has_class($q['class_name'])) {
                    continue;
                }
                $p->set_bookmark("trig-{$i}");
                $trigger_bms[$i] = true;
            }
        }

        return ['root_bm' => $root_bm, 'alt' => $alt, 'trigger_bms' => $trigger_bms];
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
}
