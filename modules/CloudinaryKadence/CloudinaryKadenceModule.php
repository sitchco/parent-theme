<?php

declare(strict_types=1);

namespace Sitchco\Parent\Modules\CloudinaryKadence;

use Sitchco\Framework\Module;
use Sitchco\Modules\Cloudinary\CloudinaryModule;
use Sitchco\Modules\Cloudinary\CloudinaryUrl;
use Sitchco\Parent\Modules\KadenceBlocks\KadenceBlocks;

class CloudinaryKadenceModule extends Module
{
    public const DEPENDENCIES = [CloudinaryModule::class, KadenceBlocks::class];

    public function __construct(private CloudinaryUrl $cloudinaryUrl) {}

    public function init(): void
    {
        if (!$this->cloudinaryUrl->isConfigured()) {
            return;
        }
        add_filter('kadence_blocks_rowlayout_render_block_attributes', [$this, 'rewriteRowLayoutAttributes']);
        add_filter('kadence_blocks_column_render_block_attributes', [$this, 'rewriteColumnAttributes']);
    }

    public function rewriteRowLayoutAttributes(array $attributes): array
    {
        // Desktop background image
        if (!empty($attributes['bgImg'])) {
            $attributes['bgImg'] = $this->cloudinaryUrl->buildUrl($attributes['bgImg']);
        }

        // Desktop overlay background image
        if (!empty($attributes['overlayBgImg'])) {
            $attributes['overlayBgImg'] = $this->cloudinaryUrl->buildUrl($attributes['overlayBgImg']);
        }

        // Responsive background images
        $this->rewriteNestedBgImg($attributes, 'tabletBackground');
        $this->rewriteNestedBgImg($attributes, 'mobileBackground');

        // Responsive overlay images
        $this->rewriteNestedOverlayImg($attributes, 'tabletOverlay');
        $this->rewriteNestedOverlayImg($attributes, 'mobileOverlay');

        // Background slider images
        if (!empty($attributes['backgroundSlider']) && is_array($attributes['backgroundSlider'])) {
            foreach ($attributes['backgroundSlider'] as &$slide) {
                if (!empty($slide['bgImg'])) {
                    $slide['bgImg'] = $this->cloudinaryUrl->buildUrl($slide['bgImg']);
                }
            }
            unset($slide);
        }

        // Background video (local only)
        $this->rewriteNestedVideo($attributes, 'backgroundVideo');
        $this->rewriteNestedVideo($attributes, 'tabletBackgroundVideo');
        $this->rewriteNestedVideo($attributes, 'mobileBackgroundVideo');

        return $attributes;
    }

    public function rewriteColumnAttributes(array $attributes): array
    {
        $this->rewriteNestedBgImg($attributes, 'backgroundImg');
        $this->rewriteNestedBgImg($attributes, 'backgroundImgHover');

        return $attributes;
    }

    private function rewriteNestedBgImg(array &$attributes, string $key): void
    {
        if (!empty($attributes[$key][0]['bgImg'])) {
            $attributes[$key][0]['bgImg'] = $this->cloudinaryUrl->buildUrl($attributes[$key][0]['bgImg']);
        }
    }

    private function rewriteNestedOverlayImg(array &$attributes, string $key): void
    {
        if (!empty($attributes[$key][0]['overlayBgImg'])) {
            $attributes[$key][0]['overlayBgImg'] = $this->cloudinaryUrl->buildUrl($attributes[$key][0]['overlayBgImg']);
        }
    }

    private function rewriteNestedVideo(array &$attributes, string $key): void
    {
        if (!empty($attributes[$key][0]['local'])) {
            $attributes[$key][0]['local'] = $this->cloudinaryUrl->buildUrl($attributes[$key][0]['local']);
        }
    }
}
