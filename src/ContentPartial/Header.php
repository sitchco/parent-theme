<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Framework\Core\Module;

/**
 * Class Header
 * Handles setting the header content within the Timber context.
 */
class Header extends Module
{
    public function init(): void
    {
        add_filter('timber/context', [$this, 'setHeaderContext']);
    }

    public function setHeaderContext(array $context): array
    {
        $repository = new ContentPartialRepository();
        $header = $repository->findHeaderFromPage() ?? $repository->findDefaultHeader();
        $context['header_content'] = $header?->post_content;
        return $context;
    }
}
