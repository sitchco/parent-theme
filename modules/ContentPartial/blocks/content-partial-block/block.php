<?php
/**
 * Expected:
 * @var array $context
 */

use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialRepository;

$wrapper_attributes = [
    'class' => 'wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained',
];
if (!empty($block['anchor'])) {
    $wrapper_attributes['id'] = $block['anchor'];
}

$blockPostId = $context['fields']['block'];
$post = (new ContentPartialRepository())->findById($blockPostId);
if ($post instanceof ContentPartialPost) {
    $context['partial_post'] = $post;
    $context['wrapper_attributes'] = $wrapper_attributes;
}

