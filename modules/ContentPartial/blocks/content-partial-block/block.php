<?php
/**
 * Expected:
 * @var array $block
 */

use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialRepository;

render_twig_block_with_context($block, function (array $block) {
    $wrapper_attributes = [
        'class' => 'wp-site-blocks',
    ];
    if (!empty($block['anchor'])) {
        $wrapper_attributes['id'] = $block['anchor'];
    }

    $blockPostId = get_field('block');
    $post = (new ContentPartialRepository())->findById($blockPostId);
    if (!$post instanceof ContentPartialPost) {
        return null;
    }

    return compact('post', 'wrapper_attributes');
});
