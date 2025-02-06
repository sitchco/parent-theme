<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Utils\BlockPattern;

/**
 * class ContentPartialBlockPatterns
 * @package Sitchco\Parent\ContentPartial
 */
class ContentPartialBlockPatterns
{
    public static function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('current_screen', function ($screen) {
            if ($screen && $screen->id === ContentPartialPost::POST_TYPE) {
                self::registerDefaultHeader();
            }
        });
    }

    public static function registerDefaultHeader(): void
    {
        BlockPattern::register(
            'default-header',
            [
                'title' => 'Default Header',
                'description' => 'Header with navigation (left), site logo (center), and call to action (right).',
                'categories' => ['header'],
                'content' => '<!-- wp:cover {"overlayColor":"primary","isUserOverlayColor":true,"minHeight":50,"tagName":"header","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|sm","bottom":"var:preset|spacing|sm","left":"var:preset|spacing|md","right":"var:preset|spacing|md"}}},"layout":{"type":"constrained"}} -->
<header class="wp-block-cover alignfull" style="padding-top:var(--wp--preset--spacing--sm);padding-right:var(--wp--preset--spacing--md);padding-bottom:var(--wp--preset--spacing--sm);padding-left:var(--wp--preset--spacing--md);min-height:50px"><span aria-hidden="true" class="wp-block-cover__background has-primary-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:columns {"isStackedOnMobile":false,"align":"wide","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-columns alignwide is-not-stacked-on-mobile" style="margin-top:0;margin-bottom:0"><!-- wp:column {"verticalAlignment":"center","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:25%"><!-- wp:navigation {"ref":39,"overlayMenu":"always"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%"><!-- wp:site-logo {"width":90,"shouldSyncIcon":true,"align":"center","className":"is-style-default","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:25%"><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"right"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Get Updates</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></header>
<!-- /wp:cover -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->',
            ]);
    }
}