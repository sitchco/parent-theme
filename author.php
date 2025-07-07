<?php
/**
 * The template for displaying Author Archive pages
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 */

namespace App;

use Timber\Timber;

$context = Timber::context();
Timber::render('templates/author.twig', $context);
