<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Framework\Module;

/**
 * class ContentPartialService
 * @package Sitchco\Parent\ContentPartial
 */
class ContentPartialService
{
    protected array $registeredModules = [];

    public function __construct()
    {
        $this->init();
    }

    public function init(): void
    {
        if (is_admin()) {
            add_action('current_screen', [$this, 'ensureTaxonomyTermExists']);
        }
        add_filter('timber/context', [$this, 'setContext']);
    }

    public function addModule(string $templateAreaName, Module $module): void
    {
        $this->registeredModules[$templateAreaName] = $module;
    }

    public function setContext(array $context): array
    {
        foreach($this->registeredModules as $templateArea => $module) {
            if (!method_exists($module, 'getContext')) {
                continue;
            }
            $context["site_{$templateArea}"] = $module->getContext($templateArea);
        }
        return $context;
    }

    public function ensureTaxonomyTermExists(\WP_Screen $currentScreen): void
    {
        if ($currentScreen->post_type !== ContentPartialPost::POST_TYPE) {
            return;
        }
        $taxonomy = ContentPartialPost::TAXONOMY;
        foreach(array_keys($this->registeredModules) as $templateArea) {
            if (taxonomy_exists($taxonomy) && !term_exists($templateArea, $taxonomy)) {
                wp_insert_term(ucfirst($templateArea), $taxonomy, ['slug' => $templateArea]);
            }
        }
    }
}
