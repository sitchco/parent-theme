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
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/post-author' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/post-author-biography' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/post-author-name' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/avatar' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],

        'core/post-date' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/post-excerpt' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/post-featured-image' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/loginout' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/query-pagination-next' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/query-no-results' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/query-pagination-numbers' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/query-pagination' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/post-navigation-link' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/post-template' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/post-terms' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/query-pagination-previous' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/query' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/query-title' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/query-total' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/read-more' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/template-part' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/term-description' => [
            'allowContext' => [
                'core/edit-site'
            ]
        ],
        'core/post-title' => [
            'allowContext' => [
                'core/edit-site'
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
