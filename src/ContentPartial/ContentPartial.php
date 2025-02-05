<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Model\PostBase;

/**
 * class ContentPartial
 * @package Sitcho\Parent\ContentPartial
 *
 * @property string $template_area
 * @property string $is_default
 */
class ContentPartial extends PostBase
{
    const POST_TYPE = 'content-partial';
}