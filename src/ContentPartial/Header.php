<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Framework\Core\Module;

/**
 * class Header
 * @package Sitchco\Parent\ContentPartial
 */
class Header extends Module
{
    public function init(): void
    {
        add_filter('timber/context', [$this, 'getDefaultHeader']);
    }

    public function getDefaultHeader($context)
    {
        $DefaultHeader = (new ContentPartialRepository())->findDefaultHeader();
        $context['header'] = $DefaultHeader?->post_content;
        return $context;
    }
}