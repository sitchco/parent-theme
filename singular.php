<?php

use Timber\Timber;

$context = Timber::context();
$post = $context['post'];
$templates = ['templates/single-' . $post->post_type . '.twig', 'templates/singular.twig'];

if (post_password_required($post->ID)) {
    $templates = 'templates/single-password.twig';
}

Timber::render($templates, $context);
