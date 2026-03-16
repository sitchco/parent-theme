<?php

namespace Sitchco\Parent\Modules\ContentPartial;

use Sitchco\Support\FilePath;
use Sitchco\Utils\BlockPattern;
use Sitchco\Utils\TimberUtil;

/**
 * class ContentPartialServicenamespace SitchcoParentModulesSiteHeader;
 */
class ContentPartialService
{
    /**
     * @var array<string, bool>
     */
    protected array $templateAreas = [];

    /**
     * @var array<string, FilePath>
     */
    protected array $blockPatternPaths = [];

    /**
     * @var array<string, ContentPartialPost>
     */
    private array $resolvedPartials = [];

    /**
     * @var array<string, ?int>
     */
    private array $termIds = [];

    protected ContentPartialRepository $repository;

    public function __construct(ContentPartialRepository $repository)
    {
        $this->repository = $repository;
    }

    public function addTemplateArea(string $templateAreaName, bool $hasContext = true, array $fieldKeys = []): void
    {
        $this->templateAreas[$templateAreaName] = $hasContext;
        foreach ($fieldKeys as $fieldKey) {
            $this->registerAcfFieldFilter($templateAreaName, $fieldKey);
        }
    }

    public function addBlockPatterns(string $templateAreaName, FilePath $path): void
    {
        $this->blockPatternPaths[$templateAreaName] = $path->append('block-patterns');
    }

    public function setContext(): void
    {
        foreach ($this->templateAreas as $templateArea => $hasContext) {
            if (!$hasContext) {
                continue;
            }
            $termId = $this->getTermId($templateArea);
            $partial =
                $this->repository->findPartialOverrideFromPage($templateArea) ??
                ($termId ? $this->repository->findDefaultPartial($termId) : null);
            if (!$partial instanceof ContentPartialPost) {
                continue;
            }
            TimberUtil::addContext("partials/site-{$templateArea}", ["site_{$templateArea}" => $partial]);
            $this->resolvedPartials[$templateArea] = $partial;
        }
    }

    public function getTermId(string $slug): ?int
    {
        if (!array_key_exists($slug, $this->termIds)) {
            $term = get_term_by('slug', $slug, ContentPartialPost::TAXONOMY);
            $this->termIds[$slug] = $term ? $term->term_id : null;
        }
        return $this->termIds[$slug];
    }

    public function getPartial(string $area): ?ContentPartialPost
    {
        return $this->resolvedPartials[$area] ?? null;
    }

    private function registerAcfFieldFilter(string $templateAreaName, string $fieldKey): void
    {
        add_filter("acf/fields/post_object/query/key={$fieldKey}", function (array $args) use ($templateAreaName) {
            $args['post_type'] = ContentPartialPost::POST_TYPE;
            $termId = $this->getTermId($templateAreaName);
            $args['tax_query'] = [
                [
                    'taxonomy' => ContentPartialPost::TAXONOMY,
                    'field' => 'term_id',
                    'terms' => $termId ?? 0,
                ],
            ];
            return $args;
        });
    }

    public function ensureTaxonomyTermExists(\WP_Screen $currentScreen): void
    {
        if ($currentScreen->post_type !== ContentPartialPost::POST_TYPE) {
            return;
        }
        $taxonomy = ContentPartialPost::TAXONOMY;
        foreach (array_keys($this->templateAreas) as $templateArea) {
            if (taxonomy_exists($taxonomy) && !term_exists($templateArea, $taxonomy)) {
                wp_insert_term(ucfirst($templateArea), $taxonomy, ['slug' => $templateArea]);
            }
        }
    }

    public function registerBlockPatterns(\WP_Screen $currentScreen): void
    {
        if ($currentScreen->id !== ContentPartialPost::POST_TYPE) {
            return;
        }
        foreach ($this->blockPatternPaths as $filePath) {
            $filesToRegister = $filePath->glob('*');
            array_walk($filesToRegister, [BlockPattern::class, 'registerFile']);
        }
    }
}
