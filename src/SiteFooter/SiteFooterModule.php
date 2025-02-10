<?php

namespace Sitchco\Parent\SiteFooter;

use Sitchco\Framework\Core\Module;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartial\ContentPartialService;

class SiteFooterModule extends Module
{
    const DEPENDENCIES = [
        ContentPartialModule::class
    ];

    public const FEATURES = [
        'registerBlockPatterns'
    ];

    protected ContentPartialService $contentService;

    public function __construct(ContentPartialService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function init(): void
    {
        add_filter('timber/context', [$this, 'setContext']);
        add_filter('acf/fields/post_object/query/name=footer_partial', [$this, 'filterFooterPartialPostObject'], 10, 3);
    }

    public function setContext(array $context): array
    {
        $footer = $this->contentService->findOverrideFromPage('footer') ?? $this->contentService->findDefault('footer');
        $context['site_footer'] = $footer?->post_name ? ['name' => $footer->post_name, 'content' => $footer?->content()] : null;
        return $context;
    }

    public function filterFooterPartialPostObject($args, $field, $post_id): array
    {
        return $this->contentService->filterPartialPostObject('footer', $args);
    }

    public function registerBlockPatterns(): void
    {
        add_action('init', [FooterBlockPatterns::class, 'init'], 11);
    }
}
