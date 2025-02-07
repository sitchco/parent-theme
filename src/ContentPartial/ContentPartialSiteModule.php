<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Framework\Core\Module;

/**
 * abstract class ContentPartialSiteModule
 * @package Sitchco\Parent\ContentPartial
 */
abstract class ContentPartialSiteModule extends Module
{
    const DEPENDENCIES = [
        ContentPartialModule::class
    ];

    public const FEATURES = [
        'registerBlockPatterns'
    ];

    protected ContentPartialRepository $repository;

    abstract protected function getTemplateArea(): string;
    abstract protected function getContextKey(): string;
    abstract protected function findOverrideFromPage();
    abstract protected function findDefault();

    public function __construct(ContentPartialRepository $repository)
    {
        $this->repository = $repository;
    }

    public function init(): void
    {
        add_filter('timber/context', [$this, 'setContext']);
        add_filter("acf/fields/post_object/query/name={$this->getTemplateArea()}_partial", [$this, 'filterPartialPostObject'], 10, 3);
    }

    public function setContext(array $context): array
    {
        $content = $this->findOverrideFromPage() ?? $this->findDefault();
        $context[$this->getContextKey()] = $content->post_name
            ? ['name' => $content->post_name, 'content' => $content?->content()]
            : null;

        return $context;
    }

    public function filterPartialPostObject($args, $field, $post_id)
    {
        $args['meta_query'] = [
            [
                'key' => 'template_area',
                'value' => $this->getTemplateArea(),
                'compare' => '='
            ]
        ];
        return $args;
    }

    abstract public function registerBlockPatterns(): void;
}
