<?php
/**
 * Expected:
 * @var array $block
 */

use Sitchco\Parent\ContentPartial;

$additional_attributes = [];
if (!empty($block['anchor'])) {
    $additional_attributes['id'] = $block['anchor'];
}
$blockPostId = get_field('block');
$blockPost = (new ContentPartial\ContentPartialRepository())->findById($blockPostId);
if (!$blockPost instanceof ContentPartial\ContentPartialPost) {
    return;
}
?>

<div <?= get_block_wrapper_attributes($additional_attributes); ?>>
    <?= $blockPost->content(); ?>
</div>
