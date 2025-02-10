<?php

namespace Sitchco\Parent\SiteHeader;

use Sitchco\Framework\Core\Module;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartial\ContentPartialService;

class SiteHeaderModule extends Module
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
        add_filter('acf/fields/post_object/query/name=header_partial', [$this, 'filterHeaderPartialPostObject'], 10, 3);
    }

    public function setContext(array $context): array
    {
        $header = $this->contentService->findOverrideFromPage('header') ?? $this->contentService->findDefault('header');
        $context['site_header'] = $header?->post_name ? ['name' => $header->post_name, 'content' => $header?->content()] : null;
        return $context;
    }

    public function filterHeaderPartialPostObject($args, $field, $post_id): array
    {
        return $this->contentService->filterPartialPostObject('header', $args);
    }

    public function registerBlockPatterns(): void
    {
        add_action('init', [HeaderBlockPatterns::class, 'init'], 11);
    }
}
