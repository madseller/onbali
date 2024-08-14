<?php

namespace Seobrothers\WP\Plugin\Onbali\Hook;

trait ActionHook
{
    function action_after_setup_theme(): void
    {
        \add_image_size("review_avatar", 55, 55, true);
        \add_image_size("thumbnail-card", 333, 222, true);
    }

    function action_wp_enqueue_scripts(): void
    {
        $this->dequeue_assets();
        $this->enqueue_assets();
    }

    function action_wp_enqueue_media(): void
    {
        \wp_enqueue_style(
            "anchor-menu-css",
            "{$this->config->theme->assets_url}/css/admin/media-buttons.css",
            [],
            filemtime(ABSPATH . "{$this->config->theme->assets_url}/css/admin/media-buttons.css")
        );

        foreach ($this->shortcodes as $shortcode)
        {
            if (method_exists($shortcode, "action_wp_enqueue_media"))
            {
                $shortcode->action_wp_enqueue_media($this->config->theme->assets_url);
            }
        }
    }

    function action_media_buttons(string $editor_id): void
    {
        foreach ($this->shortcodes as $shortcode)
        {
            if (method_exists($shortcode, "action_media_buttons"))
            {
                $shortcode->action_media_buttons();
            }
        }
    }

    function action_save_post(int $postID): void
    {
        foreach ($this->shortcodes as $shortcode)
        {
            if (method_exists($shortcode, "action_save_post"))
            {
                $shortcode?->action_save_post($postID);
            }
        }
    }

    function action_admin_head_users_php(): void
    {
        echo "<style>.column-user_id{width: 5%}</style>";
    }

    function action_manage_users_columns( $columns ): array
    {
        $columns["user_id"] = "ID";

        return $columns;
    }
    
    function action_manage_users_custom_column($value, $column_name, $user_id)
    {
        if ( "user_id" == $column_name )
            return $user_id;

        return $value;
    }

    function action_template_redirect(): void
    {
        
    }

    function action_init(): void
    {
        \register_nav_menus(
            [
                "primary-menu" => __("Header Menu"),
                "footer-menu" => __("Footer Menu"),
                "footer-m-2" => __("Menu 2"),
                "destinations-menu" => __("Destinations")
            ]
        );

        $this->preventUppercaseUrl();

        //\add_rewrite_rule('(.+?)/?$', 'index.php?entity=$matches[1]');
    }

    function action_widgets_init(): void
    {
        \register_sidebar([
            "name" => "Footer sidebar 1",
            "id" => "footer-sidebar-1",
            "description" => "Footer area.",
        ]);

        \register_sidebar([
            "name" => "Search Bar",
            "id" => "search-bar",
            "description" => "Search Bar.",
        ]);
    }

    function action_pre_get_posts(\WP_Query $query): void
    {
        if ( ! \is_admin() && $query->is_search() && $query->is_main_query())
        {
            $query->set("post_type", "post");
        }
    }

    function action_wp_ajax_send_message(): void
    {
        self::ajax_action_send_message();
    }

    function action_wp_ajax_nopriv_send_message(): void
    {
        self::ajax_action_send_message();
    }

    private function enqueue_assets()
    {
        global $template;

        $template_slug = basename($template);

        foreach ($this->getAssetsToEnqueue() as $asset)
        {
            $templates = array_shift($asset);

            if ( ! (empty($templates) || in_array($template_slug, $templates)))
            {
                continue;
            }
            
            $type = array_shift($asset);

            if ("style" === $type)
            {
                $asset[1] = "{$this->config->theme->assets_url}/css/{$asset[1]}";

                \wp_enqueue_style(...$asset);
            }
            else if ("script" === $type)
            {
                $vars = array_shift($asset);

                if ( ! empty($vars))
                {
                    $vars = array_map(
                        fn ($value) => $value instanceof \Closure
                            ? $value()
                            : $value,
                        $vars
                    );

                    $vars += ["ajax_url" => \admin_url()];
                }

                $asset[1] = "{$this->config->theme->assets_url}/js/{$asset[1]}";

                \wp_enqueue_script(...$asset);
                \wp_localize_script($asset[0], $asset[0], $vars);
            }
        }
    }

    private function dequeue_assets()
    {
        foreach ($this->getAssetsToDequeue() as $asset)
        {
            $templates = array_shift($asset);

            $is_template = ! empty($templates)
                ? array_reduce(
                    $templates,
                    fn ($is, $t) => $is = $is || \is_page_template("{$t}.php"),
                    false
                )
                : true;

            if ( ! $is_template)
            {
                continue;
            }
            
            $type = array_shift($asset);

            if ("style" === $type)
            {
                \wp_dequeue_style($asset);
            }
            else if ("script" === $type)
            {
                \wp_enqueue_script($asset);
            }
        }
    }
}
