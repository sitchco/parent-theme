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

    protected ContentPartialRepository $repository;

    public function __construct(ContentPartialRepository $repository)
    {
        $this->repository = $repository;
    }

    public function addTemplateArea(string $templateAreaName, bool $hasContext = true): void
    {
        $this->templateAreas[$templateAreaName] = $hasContext;
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
            $partial =
                $this->repository->findPartialOverrideFromPage($templateArea) ??
                $this->repository->findDefaultPartial($templateArea);
            if (!$partial instanceof ContentPartialPost) {
                continue;
            }
            TimberUtil::addContext("partials/site-{$templateArea}", ["site_{$templateArea}" => $partial]);
        }
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
