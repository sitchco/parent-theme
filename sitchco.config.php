<?php

use Sitchco\Modules\Wordpress\Cleanup;
use Sitchco\Parent\Modules\Animation\AnimationFrameworkModule;
use Sitchco\Parent\Modules\ButtonConfig\ButtonConfigModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartialBlock\ContentPartialBlockModule;
use Sitchco\Parent\Modules\ContentSlider\ContentSlider;
use Sitchco\Parent\Modules\GravityForms\GravityForms;
use Sitchco\Parent\Modules\KadenceBlocks\KadenceBlocks;
use Sitchco\Parent\Modules\Patterns\PatternsModule;
use Sitchco\Parent\Modules\ContentPartialModal\ContentPartialModalModule;
use Sitchco\Parent\Modules\SiteFooter\SiteFooterModule;
use Sitchco\Parent\Modules\SiteHeader\SiteHeaderModule;
use Sitchco\Parent\Modules\InlineSVG\InlineSVGModule;
use Sitchco\Parent\Modules\KadenceImageModal\KadenceImageModal;
use Sitchco\Parent\Modules\Theme\Theme;

return [
    'modules' => [
        Cleanup::class => [
            'disableGutenbergStyles' => false,
        ],
        AnimationFrameworkModule::class,
        ButtonConfigModule::class,
        ContentPartialModule::class,
        ContentPartialBlockModule::class,
        SiteHeaderModule::class,
        SiteFooterModule::class,
        ContentPartialModalModule::class,
        ContentSlider::class,
        InlineSVGModule::class,
        Theme::class,
        KadenceBlocks::class,
        KadenceImageModal::class,
        PatternsModule::class,
        GravityForms::class,
    ],
    /**
     * Which blocks may use which animations.
     *
     * Block name => a list of animation keys, or a map of them when a block needs overrides:
     *
     *     'core/group' => ['parallax', 'fade-up'],
     *
     *     'kadence/rowlayout' => [
     *         'letter' => [
     *             'allowed'  => ['color' => ['purple', 'green']],  // restrict the palette here
     *             'defaults' => ['opacity' => '30'],               // and start at 30%
     *         ],
     *         'parallax' => true,
     *     ],
     *
     * `allowed` and `defaults` are the only reserved sub-keys. An animation named here must also be
     * activated in `modules` above, or it is dropped with a logged warning. Merging is additive, so
     * removal is `=> false` — `'parallax' => false` to drop one animation from a block,
     * `'core/group' => false` to drop the block entirely, or `'animations' => false` to drop every
     * block's animations, an ancestor's included. Removal stops there: an inherited `allowed` list
     * or `defaults` map cannot be unset, only replaced with the one you want.
     *
     * Do not mix the bare-list and keyed forms in one block: a numeric-keyed array is discarded by
     * config normalization before anything reads it. And in a child theme, re-stating an animation
     * an ancestor already configured as `true` — bare or keyed, the two normalize identically —
     * replaces its overrides. To keep them, leave the animation out or write `'parallax' => []`.
     * Both traps are spelled out on AnimationFrameworkModule.
     */
    'animations' => [],
    'disallowedBlocks' => [
        /** TEXT */
        'core/code',
        'core/details',
        'core/footnotes',
        'core/preformatted',
        'core/verse',
        'core/pullquote',
        'core/freeform',
        'core/math',
        /** MEDIA */
        'core/file',
        'core/gallery',
        'core/media-text',
        'core/table',
        /** Design */
        'core/more',
        'core/nextpage',
        'core/separator',
        'core/spacer',
        'core/accordion',
        'core/text-columns',
        /** Layout */
        'core/group',
        'core/cover',
        'core/columns',
        'core/column',
        'core/image',
        /** Widgets */
        'core/legacy-widget',
        'core/widget-group',
        /** Comments */
        'core/post-comments',
        /** Kadence */
        'kadence/icon',
        'kadence/advancedheading',
        'kadence/advancedbtn',
        'kadence/advancedgallery',
        'kadence/form',
        'kadence/lottie',
        'kadence/progress-bar',
        'kadence/countup',
        'kadence/countdown',
        'kadence/infobox',
        'kadence/show-more',
        'kadence/videopopup',
        'kadence/tableofcontents',
        'kadence/iconlist',
        'kadence/posts',
        'kadence/identity',
        'kadence/navigation',
        'kadence/navigation-link',
        'kadence/header',
        'kadence/advanced-form',
        'kadence/vector',

        /** Widgets */
        'core/archives',
        'core/calendar',
        'core/latest-comments',
        'core/latest-posts',
        'core/rss',
        'core/search',
        'core/tag-cloud',
        'core/categories',
        'core/page-list' => [
            'allowPostType' => [ContentPartialPost::POST_TYPE],
            'allowContext' => ['core/edit-site'],
        ],
        'core/social-links' => [
            'allowPostType' => [ContentPartialPost::POST_TYPE],
            'allowContext' => ['core/edit-site'],
        ],
        /** Theme */
        'core/navigation' => [
            'allowPostType' => [ContentPartialPost::POST_TYPE],
            'allowContext' => ['core/edit-site'],
        ],
        'core/site-logo' => [
            'allowPostType' => [ContentPartialPost::POST_TYPE],
            'allowContext' => ['core/edit-site'],
        ],
        'core/site-tagline' => [
            'allowPostType' => [ContentPartialPost::POST_TYPE],
            'allowContext' => ['core/edit-site'],
        ],
        'core/site-title' => [
            'allowPostType' => [ContentPartialPost::POST_TYPE],
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-time-to-read',
        'core/comments-title',
        'core/comment-author-name',
        'core/comments',
        'core/comment-reply-link',
        'core/comment-edit-link',
        'core/comment-date',
        'core/comment-content',
        'core/post-comments-form',
        'core/comments-pagination-next',
        'core/comments-pagination-numbers',
        'core/comments-pagination',
        'core/comments-pagination-previous',
        'core/post-content' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-author' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-author-biography' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-author-name' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/avatar' => [
            'allowContext' => ['core/edit-site'],
        ],

        'core/post-date' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-excerpt' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-featured-image' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/loginout' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/query-pagination-next' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/query-no-results' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/query-pagination-numbers' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/query-pagination' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-navigation-link' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-template' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-terms' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/query-pagination-previous' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/query' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/query-title' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/query-total' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/read-more' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/template-part' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/term-description' => [
            'allowContext' => ['core/edit-site'],
        ],
        'core/post-title' => [
            'allowContext' => ['core/edit-site'],
        ],
        /** Embeds */
        'variation;core/embed;wordpress',
        'variation;core/embed;animoto',
        'variation;core/embed;flickr',
        'variation;core/embed;cloudup',
        'variation;core/embed;collegehumor',
        'variation;core/embed;crowdsignal',
        'variation;core/embed;dailymotion',
        'variation;core/embed;imgur',
        'variation;core/embed;reddit',
        'variation;core/embed;pocket-casts',
        'variation;core/embed;mixcloud',
        'variation;core/embed;kickstarter',
        'variation;core/embed;issuu',
        'variation;core/embed;reverbnation',
        'variation;core/embed;screencast',
        'variation;core/embed;scribd',
        'variation;core/embed;smugmug',
        'variation;core/embed;speaker-deck',
        'variation;core/embed;ted',
        'variation;core/embed;tumblr',
        'variation;core/embed;videopress',
        'variation;core/embed;wordpress-tv',
        'variation;core/embed;bluesky',
        'variation;core/embed;wolfram-cloud',
        'variation;core/embed;pinterest',
        'variation;core/embed;amazon-kindle',
        'variation;core/embed;twitter',
    ],
];
