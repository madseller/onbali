<?php

namespace Seobrothers\WP\Plugin\Onbali\Shortcode;

final class AnchorMenuShortcode
{
    function __construct
    (
        public readonly string $description = ""
    )
    {
        
    }

    function render(): string
    {
        global $post;

        $anchor_menu_items = \get_field("anchor_menu_items") ?? [];

		if (empty($anchor_menu_items))
        {
			return "";
        }

        $anchor_menu_items = array_reduce(
            $anchor_menu_items,
            fn ($items, $item) => $items + [
                $item["target_text"] => $item["menu_text"]
            ],
            []
        );

        $document = new \DOMDocument();
        $document->loadHTML($post->post_content);

        $xpath = new \DOMXPath($document);
        $h2 = "//h2";

        $headings = array_filter(
            [...$xpath->query($h2)],
            fn ($node) => ! empty($node->getAttribute("id"))
                && isset($anchor_menu_items[$node->nodeValue])
        );

		if (empty($headings))
        {
			return "";
        }

        $nodes = array_reduce(
            $headings,
            fn ($nodes, $node) => $nodes + [
                $node->getAttribute("id") => $anchor_menu_items[$node->nodeValue]
            ],
            []
        );

        ob_start();

        \get_template_part(
            "partials/shortcode/anchor-menu",
            null,
            ["anchor_menu_items" => $nodes]
        );

        return ob_get_clean();
    }

    function action_save_post(int $postID): void
    {
        $post = get_post($postID);

        if (empty($post->post_content))
        {
            return;
        }
        
        $anchor_menu_items = \get_field("anchor_menu_items") ?? [];

        if ( ! empty($anchor_menu_items))
        {
            return;
        }

        $document = new \DOMDocument();
        $document->loadHTML($post->post_content);

        $xpath = new \DOMXPath($document);
        $h2 = "//h2";

        $headings = array_filter(
            [...$xpath->query($h2)],
            fn ($node) => ! empty($node->getAttribute("id"))
        );

		if (empty($headings))
        {
			return;
        }

        $items = array_reduce(
            $headings,
            fn ($items, $node) => $items + [
                count($items) => [
                    "target_text" => $node->nodeValue,
                    "menu_text" => $node->nodeValue
                ]
            ],
            []
        );

        \update_field("anchor_menu_items", $items);
    }

    function action_wp_enqueue_media(string $assetsUrl): void
    {        
        if ("post" !== get_current_screen()->id)
        {
            return;
        }

        \wp_enqueue_script(
            "anchor-menu-js",
            "{$assetsUrl}/js/admin/anchor-menu.js",
            [],
            filemtime(ABSPATH . "{$assetsUrl}/js/admin/anchor-menu.js"),
            true
        );
    }

    function action_media_buttons(): void
    {        
        if ("post" !== get_current_screen()->id)
        {
            return;
        }

        ob_start();

            add_thickbox();
            \get_template_part("admin/post/editor/anchor-menu", null, [
                "description" => $this->description ?? ""
            ]);
        
        echo ob_get_clean();
    }
}