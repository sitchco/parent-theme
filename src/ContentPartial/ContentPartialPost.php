<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Model\PostBase;

/**
 * class ContentPartial
 * @package Sitcho\Parent\ContentPartial
 *
 * @property string $template_area
 * @property bool $is_default
 * @property bool $is_overlaid
 * @property bool $is_sticky
 */
class ContentPartialPost extends PostBase
{
    const POST_TYPE = 'content-partial';
    const TAXONOMY = 'template-area';
}