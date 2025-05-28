<?php

namespace Sitchco\Parent\Modules\ContentPartial;

use Sitchco\Repository\PageRepository;
use Sitchco\Repository\RepositoryBase;
use Timber\Post;

/**
 * class ContentPartialRepository
 * @package Sitchco\Parent\ContentPartial
 */
class ContentPartialRepository extends RepositoryBase
{
    protected string $model_class = ContentPartialPost::class;

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

        $page = (new PageRepository())->findById($page_id);
        $property = $name . "_partial";
        if (!$page || empty($header_partial_id = $page->$property)) {
            return null;
        }

        return $this->findById($header_partial_id);
    }
}