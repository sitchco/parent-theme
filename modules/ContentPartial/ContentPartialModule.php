<?php

namespace Sitchco\Parent\Modules\ContentPartial;

use Sitchco\Framework\Module;
use Sitchco\Modules\TimberModule;

class ContentPartialModule extends Module
{
    public const POST_CLASSES = [ContentPartialPost::class];

    public const DEPENDENCIES = [TimberModule::class];

    protected ContentPartialService $contentService;
    protected ContentPartialRepository $repository;

    public function __construct(ContentPartialService $contentService, ContentPartialRepository $repository)
    {
        $this->contentService = $contentService;
        $this->repository = $repository;
    }

    public function init(): void
    {
        if (is_admin()) {
            add_action('current_screen', [$this->contentService, 'ensureTaxonomyTermExists']);
            add_action('current_screen', [$this->contentService, 'registerBlockPatterns']);
        }
        add_action('wp', [$this->contentService, 'setContext']);

        // TODO: do any TimberUtil methods work here?
        add_filter('sitchco/template-context/partials/site-header', [$this, 'addPageContextToSiteHeader'], 99, 1);
    }


    public function addPageContextToSiteHeader(array $context): array
    {
        $header_overlay = get_field('header_overlay');
        if ($header_overlay === 'overlay') {
            $context['site_header']->is_overlaid = true;
        } elseif ($header_overlay === 'separate') {
            $context['site_header']->is_overlaid = false;
        }
        return $context;
    }
}
