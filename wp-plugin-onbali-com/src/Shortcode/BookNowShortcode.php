<?php

namespace Seobrothers\WP\Plugin\Onbali\Shortcode;

final class BookNowShortcode
{
    function __construct
    (
        public readonly string $description = "",
        public readonly array $categories = []
    )
    {

    }

    function render(array $atts): string
    {
        ob_start();

        \get_template_part(
            "partials/shortcode/book-now",
            null,
            \wp_parse_args(
                $atts,
                [
                    "url" => ""
                ]
            )
        );

        return ob_get_clean();
    }

    function action_wp_enqueue_media(string $assetsUrl): void
    {        
        global $post;

        if ("post" !== \get_current_screen()->id)
        {
            return;
        }

        if ( ! $this->hasCategories($post->ID))
        {
            return;
        }

        \wp_enqueue_script(
            "book-now-js",
            "{$assetsUrl}/js/admin/book-now.js",
            [],
            filemtime(ABSPATH . "{$assetsUrl}/js/admin/book-now.js"),
            true
        );
    }

    function action_media_buttons(): void
    {
        global $post;

        if ("post" !== get_current_screen()->id)
        {
            return;
        }

        if ( ! $this->hasCategories($post->ID))
        {
            return;
        }

        ob_start();

            add_thickbox();
            \get_template_part(
                "admin/post/editor/book-now",
                null,
                [
                    "description" => $this->description ?? ""
                ]
            );
        
        echo ob_get_clean();
    }

    private function hasCategories(int $postID): bool
    {        
        return array_reduce(
            $this->categories,
            fn ($has, $slug) => $has || \has_category($slug, $postID),
            false
        );
    }
}
