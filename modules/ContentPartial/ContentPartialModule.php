<?php

namespace Sitchco\Parent\Modules\ContentPartial;

use Sitchco\Framework\Module;
use Sitchco\Modules\TimberModule;

class ContentPartialModule extends Module
{
    public const HOOK_SUFFIX = 'content-partial';

    public const PAGE_OPTIONS_GROUP_KEY = 'group_67a3bab78acee';

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
            add_action('current_screen', [$this, 'maybeEnableSlugEditing']);
        }
        add_action('wp', [$this->contentService, 'setContext']);
        add_filter('acf/load_field_group', [$this, 'applyPageOptionsFilter']);
    }

    public function applyPageOptionsFilter(array $group): array
    {
        if ($group['key'] === self::PAGE_OPTIONS_GROUP_KEY) {
            $group = apply_filters(static::hookName('page-options-locations'), $group);
        }
        return $group;
    }

    public function maybeEnableSlugEditing(\WP_Screen $screen): void
    {
        if ($screen->post_type === ContentPartialPost::POST_TYPE) {
            add_filter('is_post_type_viewable', [$this, 'makeSlugEditable'], 10, 2);
        }
    }

    public function makeSlugEditable(bool $is_viewable, \WP_Post_Type $post_type): bool
    {
        if ($post_type->name === ContentPartialPost::POST_TYPE) {
            return true;
        }
        return $is_viewable;
    }
}
