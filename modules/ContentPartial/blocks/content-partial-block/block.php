<?php
/**
 * Expected:
 * @var array $context
 * @var ContainerInterface $container
 */

use Psr\Container\ContainerInterface;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialRepository;

$blockPostId = $context['fields']['block'];
$Repository = $container->get(ContentPartialRepository::class);
$post = $Repository->findById($blockPostId);
if ($post instanceof ContentPartialPost) {
    $context['partial_post'] = $post;
}
