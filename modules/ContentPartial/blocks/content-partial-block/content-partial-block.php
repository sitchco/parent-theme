<?php
/**
 * Expected:
 * @var array $block
 */

use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialRepository;

$additional_attributes = [
    'class' => 'wp-site-blocks'
];
if (!empty($block['anchor'])) {
    $additional_attributes['id'] = $block['anchor'];
}
$blockPostId = get_field('block');
$blockPost = (new ContentPartialRepository())->findById($blockPostId);
if (!$blockPost instanceof ContentPartialPost) {
    return;
}
?>

<div <?= get_block_wrapper_attributes($additional_attributes); ?>>
    <?= $blockPost->content(); ?>
</div>
