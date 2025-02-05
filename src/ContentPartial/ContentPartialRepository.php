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
    protected string $model_class = ContentPartial::class;

    public function findDefaultHeader(): ?Post
    {
        return $this->findOne([
            'meta_query' => [
                [
                    'key' => 'template_area',
                    'value' => 'header',
                    'compare' => '='
                ],
                [
                    'key' => 'is_default',
                    'value' => '1',
                    'compare' => '='
                ],
            ],
        ]);
    }

    public function findHeaderFromPage(?int $page_id = null): ?Post
    {
        $page_id = $page_id ?? get_queried_object_id();
        if (!$page_id) {
            return null;
        }

        $page = (new PageRepository())->findById($page_id);
        if (!$page || empty($header_partial_id = $page->header_partial)) {
            return null;
        }

        return $this->findById($header_partial_id);
    }
}