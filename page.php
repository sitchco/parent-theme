<?php

use Timber\Timber;

$context = Timber::context();

Timber::render('templates/singular.twig', $context);
