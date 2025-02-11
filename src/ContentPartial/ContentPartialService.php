<?php

namespace Sitchco\Parent\ContentPartial;

use Timber\Post;

/**
 * class ContentPartialService
 * @package Sitchco\Parent\ContentPartial
 */
class ContentPartialService
{
    protected ContentPartialRepository $repository;

    public function __construct(ContentPartialRepository $repository)
    {
        $this->repository = $repository;
    }

    public function findOverrideFromPage(string $area, ?int $page_id = null): ?Post
    {
        return match ($area) {
            'header' => $this->repository->findHeaderOverrideFromPage($page_id),
            'footer' => $this->repository->findFooterOverrideFromPage($page_id),
            default => null,
        };
    }

    public function findDefault(string $area): ?Post
    {
        return match ($area) {
            'header' => $this->repository->findDefaultHeader(),
            'footer' => $this->repository->findDefaultFooter(),
            default => null,
        };
    }

    public function filterPartialPostObject(string $area, array $args): array
    {
        $args['meta_query'] = [
            [
                'key' => 'template_area',
                'value' => $area,
                'compare' => '='
            ]
        ];
        return $args;
    }

    public function setContext(array $context, string $area): array
    {
        $content = $this->findOverrideFromPage($area) ?? $this->findDefault($area);
        $context["site_{$area}"] = $content?->post_name ? ['name' => $content->post_name, 'content' => $content?->content()] : null;
        return $context;
    }

    public function registerContentFilters(string $area, string $acfFieldName = ''): void
    {
        add_filter('timber/context', function ($context) use ($area) {
            return $this->setContext($context, $area);
        });

        $acfFieldName = $acfFieldName ?? $area . '_partial';
        add_filter("acf/fields/post_object/query/name={$acfFieldName}", function ($args, $field, $post_id) use ($area) {
            return $this->filterPartialPostObject($area, $args);
        }, 10, 3);
    }

    public function ensureTaxonomyTermExists(string $termSlug, string $termName = ''): void
    {
        add_action('acf/init', function () use ($termSlug, $termName) {
            $this->insertTaxonomyTerm($termSlug, $termName);
        });
    }

    public function insertTaxonomyTerm(string $termSlug, ?string $termName = null): void
    {
        $taxonomy = ContentPartialPost::TAXONOMY;
        if (taxonomy_exists($taxonomy) && !term_exists($termSlug, $taxonomy)) {
            $termName = !empty($termName) ? $termName : ucfirst($termSlug);
            wp_insert_term($termName, $taxonomy, ['slug' => $termSlug]);
        }
    }
}
