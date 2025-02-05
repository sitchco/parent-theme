<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Repository\RepositoryBase;
use Timber\Post;

/**
 * class ContentPartialRepository
 * @package Sitchco\Parent\ContentPartial
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
                ],
                [
                    'key' => 'is_default',
                    'value' => '1',
                ],
            ],
        ]);
    }
}