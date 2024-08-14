<?php

/**
 * Plugin Name: onbali-com
 * Version: dev-release
 */

namespace Seobrothers\WP\Plugin\Onbali;

// if ( ! \did_action("plugins_loaded"))
// {
//     return;
// }

// if (defined("DOING_AJAX") || defined("DOING_CRON"))
// {
//     return;
// }

global $onbali;

$onbali = new OnbaliPlugin(
    __FILE__,
    /* config */ [
        "hooks" => [
            "add" => [
                "action" => [
                    //"init" => [],
                    "after_setup_theme" => [],
                    "admin_head_users_php" => [],
                    "manage_users_columns" => [1],
                    "manage_users_custom_column" => [3],
                    //"template_redirect" => [],
                    "widgets_init" => [],
                    "wp_enqueue_scripts" => [],
                    "wp_ajax_send_message" => [],
                    "wp_ajax_nopriv_send_message" => [],
                    //"pre_get_posts" => [1],
                    "media_buttons" => [1],
                    "wp_enqueue_media" => [],
                    "save_post" => [1]
                ],
                "filter" => [
                    //"acf/settings/remove_wp_meta_box" => [],
                    "embed_oembed_html" => [1],
                    "rank_math/frontend/breadcrumb/html" => [3],
                    "acf/load_field/name=services" => [1],
                    "posts_results" => [2],
                    "post_type_link" => [2],
                    "request" => [1],
                    "wp_handle_upload_prefilter" => [1],
                    "upload_size_limit" => [1],
                    "wp_handle_upload" => [1]
                ]
            ],
            "remove" => [
                "action" => [
                    "load-update-core.php" => [
                        "wp_update_plugins"
                    ],
                    "wp_head" => [
                        "print_emoji_detection_script"
                    ],
                    "admin_print_scripts" => [
                        "print_emoji_detection_script"
                    ],
                    "wp_print_styles" => [
                        "print_emoji_styles"
                    ],
                    "admin_print_styles" => [
                        "print_emoji_styles"
                    ]
                ],
                "filter" => [
                    "the_content_feed" => [
                        "wp_staticize_emoji"
                    ],
                    "comment_text_rss" => [
                        "wp_staticize_emoji"
                    ],
                    "wp_mail" => [
                        "wp_staticize_emoji_for_email"
                    ]
                ]
            ]
        ],
        "theme" => [
            "id" => "onbali-com",
            "assets_url" => "/wp-content/themes/onbali-com/assets",
            "ajax_vars" => [
                "ajaxurl" => \admin_url("admin-ajax.php")
            ],
            "features" => [
                // "admin-bar",
                // "align-wide",
                // "automatic-feed-links",
                // "core-block-patterns",
                "custom-fields",
                // "custom-background",
                // "custom-header",
                // "custom-line-height",
                // "custom-logo",
                // "customize-selective-refresh-widgets",
                // "custom-spacing",
                // "custom-units",
                // "dark-editor-style",
                "disable-custom-colors",
                "disable-custom-font-sizes",
                // "editor-color-palette",
                // "editor-gradient-presets",
                // "editor-font-sizes",
                // "editor-styles",
                // "featured-content",
                // "html5",
                "menus",
                // "post-formats",
                "post-thumbnails",
                // "responsive-embeds",
                // "starter-content",
                "title-tag",
                // "wp-block-styles",
                "widgets",
                // "widgets-block-editor"
            ]
        ],
        "assets" => [
            "enqueue" => [
                "style" => [
                    [
                        "templates" => ["front-page.php"],
                        "handle" => "swiper-bundle",
                        "src" => "swiper-bundle.min.css",
                    ],
                    [
                        "handle" => "main-css",
                        "src" => "main.css",
                    ]
                ],
                "script" => [
                    
                ]
            ],
            "dequeue" => [
                "style" => [
                    [
                        "handle" => "wp-block-library"
                    ],
                    [
                        "handle" => "wp-block-library-theme"
                    ],
                    [
                        "handle" => "classic-theme-styles"
                    ],
                    [
                        "handle" => "ajax-search-pro-stylesheet-handle"
                    ]
                ]
            ]
        ],
        "shortcodes" => [
            "book_now" => [
                "description" => "",
                "categories" => [
                    "where-to-go-in-bali",
                    "best-bali-accommodations",
                    "best-bali-spa",
                    "best-bali-activities",
                    "news"
                ]
            ],
            "anchor_menu" => [],
            "hotels_booking" => []
        ]
    ]
);
