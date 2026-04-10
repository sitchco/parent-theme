<?php

namespace Sitchco\Parent\Modules\InlineSVG;

use Sitchco\Framework\Module;

class InlineSVGModule extends Module
{
    public const HOOK_SUFFIX = 'inline-svg';

    protected InlineSVGService $inlineSVGService;

    public function __construct(InlineSVGService $inlineSVGService)
    {
        $this->inlineSVGService = $inlineSVGService;
    }

    public function init(): void
    {
        add_filter('upload_mimes', [$this, 'allowSVGUploads']);

        add_filter(
            'block_type_metadata_settings',
            function ($settings, $metadata) {
                if ($metadata['name'] === 'core/image') {
                    $settings['attributes']['inlineSvg'] = [
                        'type' => 'boolean',
                        'default' => false,
                    ];
                }
                return $settings;
            },
            10,
            2,
        );

        add_filter('render_block_kadence/image', [$this, 'imageBlockInlineSVG'], 20, 2);
    }

    public function allowSVGUploads($mimes)
    {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    public function imageBlockInlineSVG(string $block_content, array $block): string
    {
        $attrs = $block['attrs'] ?? [];

        return $this->inlineSVGService->replaceImageBlock($block_content, $block, [
            'width' => $attrs['width'] ?? null,
            'max_width' => $attrs['imgMaxWidth'] ?? null,
        ]);
    }
}
