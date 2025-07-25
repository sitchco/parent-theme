<?php

namespace Sitchco\Parent\Modules\ContentPartial;

use Sitchco\Repository\RepositoryBase;
use Timber\Post;

/**
 * class ContentPartialRepositorynamespace SitchcoParentModulesSiteHeader;
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
                ],
            ],
            'meta_query' => [
                [
                    'key' => 'is_default',
                    'value' => '1',
                    'compare' => '=',
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
        $header_partial_id = get_field("{$name}_partial", $page_id);
        return $this->findById($header_partial_id);
    }
}
