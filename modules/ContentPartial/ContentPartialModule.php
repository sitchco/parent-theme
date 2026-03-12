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
            add_filter('is_post_type_viewable', [$this, 'makeSlugEditable'], 10, 2);
        }
        add_action('wp', [$this->contentService, 'setContext']);
    }

    public function makeSlugEditable(bool $is_viewable, \WP_Post_Type $post_type): bool
    {
        if ($post_type->name === ContentPartialPost::POST_TYPE) {
            return true;
        }
        return $is_viewable;
    }
}
