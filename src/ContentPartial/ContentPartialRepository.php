<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Repository\PageRepository;
use Sitchco\Repository\RepositoryBase;
use Timber\Post;

/**
 * Repository for Content Partials
 */
class ContentPartialRepository extends RepositoryBase
{
    protected string $model_class = ContentPartialPost::class;

    public function findDefaultHeader(): ?Post
    {
        return $this->findDefaultPartial('header');
    }

    public function findDefaultFooter(): ?Post
    {
        return $this->findDefaultPartial('footer');
    }

    public function findHeaderOverrideFromPage(?int $page_id = null): ?Post
    {
        return $this->findPartialOverrideFromPage('header', $page_id);
    }

    public function findFooterOverrideFromPage(?int $page_id = null): ?Post
    {
        return $this->findPartialOverrideFromPage('footer', $page_id);
    }

    public function findDefaultPartial(string $value): ?Post
    {
        return $this->findOne([
            'tax_query' => [
                [
                    'taxonomy' => ContentPartialPost::TAXONOMY,
                    'field' => 'slug',
                    'terms' => $value,
                ]
            ],
            'meta_query' => [
                [
                    'key' => 'is_default',
                    'value' => '1',
                    'compare' => '='
                ],
            ],
        ]);
    }

    public function findPartialOverrideFromPage(string $name, ?int $page_id = null): ?Post
    {
        $page_id = $page_id ?? get_queried_object_id();
        if (!$page_id) {
            return null;
        }

        $property = $name . "_partial";
        $page = (new PageRepository())->findById($page_id);
        if (!$page || empty($header_partial_id = $page->$property)) {
            return null;
        }

        return $this->findById($header_partial_id);
    }
}