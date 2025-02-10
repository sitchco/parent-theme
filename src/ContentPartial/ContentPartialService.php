<?php

namespace Sitchco\Parent\ContentPartial;

use Timber\Post;

/**
 * class SiteContentPartialService
 * @package Sitchco\Parent\ContentPartial
 */
class ContentPartialService
{
    protected ContentPartialRepository $repository;

    public function __construct(ContentPartialRepository $repository)
    {
        $this->repository = $repository;
    }

    public function findOverrideFromPage(string $area): ?Post
    {
        return match ($area) {
            'header' => $this->repository->findHeaderOverrideFromPage(),
            'footer' => $this->repository->findFooterOverrideFromPage(),
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
}
