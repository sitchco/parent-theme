<?php

use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartial\ContentPartialPost;
use Sitchco\Parent\ContentPartialBlock\ContentPartialBlockModule;
use Sitchco\Parent\PageOrder\PageOrderModule;
use Sitchco\Parent\SiteFooter\SiteFooterModule;
use Sitchco\Parent\SiteHeader\SiteHeaderModule;
use Sitchco\Parent\Theme;

return [
    'modules' => [
        Cleanup::class => [
            'disableGutenbergStyles' => false,
        ],
        ContentPartialModule::class,
        ContentPartialBlockModule::class,
        SiteHeaderModule::class,
        SiteFooterModule::class,
        PageOrderModule::class,
        Theme::class,
    ],
    'disallowedBlocks' => [
        /** TEXT */
        'core/code',
        'core/details',
        'core/footnotes',
        'core/preformatted',
        'core/verse',
        'core/pullquote',
        'core/freeform',
        /** MEDIA */
        'core/file',
        'core/gallery',
        /** Design */
        'core/more',
        'core/nextpage',
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
            'allowPostType' => [
                ContentPartialPost::POST_TYPE
            ]
        ],
        'core/social-links' => [
            'allowPostType' => [
                ContentPartialPost::POST_TYPE
            ]
        ],
        /** Theme */
        'core/navigation' => [
            'allowPostType' => [
                ContentPartialPost::POST_TYPE
            ]
        ],
        'core/site-logo' => [
            'allowPostType' => [
                ContentPartialPost::POST_TYPE
            ]
        ],
        'core/site-tagline' => [
            'allowPostType' => [
                ContentPartialPost::POST_TYPE
            ]
        ],
        'core/site-title' => [
            'allowPostType' => [
                ContentPartialPost::POST_TYPE
            ]
        ],

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
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/post-author' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/post-author-biography' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/post-author-name' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/avatar' => [
            'allowPostType' => [
                'patterns'
            ]
        ],

        'core/post-date' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/post-excerpt' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/post-featured-image' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/loginout' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/query-pagination-next' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/query-no-results' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/query-pagination-numbers' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/query-pagination' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/post-navigation-link' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/post-template' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/post-terms' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/query-pagination-previous' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/query' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/query-title' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/query-total' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/read-more' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/template-part' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/term-description' => [
            'allowPostType' => [
                'patterns'
            ]
        ],
        'core/post-title' => [
            'allowPostType' => [
                'patterns'
            ]
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
        'variation;core/embed;twitter'
    ],
];
