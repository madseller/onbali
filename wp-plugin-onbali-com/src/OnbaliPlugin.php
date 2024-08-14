<?php

namespace Seobrothers\WP\Plugin\Onbali;

use Seobrothers\WP\Plugin\PluginAbstract;
use Seobrothers\WP\Plugin\Onbali\Hook\{ActionHook, FilterHook};
use Seobrothers\WP\Plugin\Onbali\Service\{PostService, SearchService};


final class OnbaliPlugin
extends PluginAbstract
{
    use ActionHook, FilterHook;
    use PostService, SearchService;

    public readonly string $assets_url;

    private array $shortcodes = [];

    function __construct(string $file, array $config = [])
    {
        parent::__construct($file, $config);

        $this->assets_url = filter_var(\home_url() . $this->config?->theme?->assets_url, FILTER_VALIDATE_URL)
            ? $this->config->theme->assets_url
            : \get_template_directory_uri();

        $this->register();
        $this->removeHooks();
        $this->addHooks();
        $this->addShortcodes();
    }
    
    private function addShortcodes(): void
    {
        foreach ($this->config->shortcodes as $tag => $args)
        {
            $className = self::tagToClassName($tag, "Shortcode");

            if (class_exists($className))
            {
                $args = match (true)
                {
                    is_object($args) => get_object_vars($args),
                    ! is_array($args) => [],
                    default => $args
                };

                $this->shortcodes[$tag] = new $className(... $args);
                \add_shortcode($tag, [$this->shortcodes[$tag], "render"]);
            }
        }
    }


    private function addHooks(): void
    {
        foreach ($this->getHooksToAdd() as $hook)
        {
            $fn = "add_" . array_shift($hook);
            $fn(...$hook);
        }
    }

    private function removeHooks(): void
    {
        foreach ($this->getHooksToRemove() as $hook)
        {
            $fn = "remove_" . array_shift($hook);
            $fn(...$hook);
        }
    }

    function getHooksToAdd(): \Generator
    {
        foreach ($this->config->hooks->add ?? [] as $type => $hooks)
        {
            if ( ! isset(self::HOOK_TYPES[$type]))
            {
                continue;
            }

            foreach ($hooks as $tag => $hook)
            {
                if ( ! is_array($hook))
                {
                    continue;
                }

                $method = self::tagToMethodName("{$type}_{$tag}");

                if ( ! method_exists($this, $method))
                {
                    continue;
                }

                yield [
                    $type,
                    $tag,
                    [$this, $method],
                    $hook[1] ?? PHP_INT_MAX,
                    $hook[0] ?? 0
                ];
            }
        }
    }

    function getHooksToRemove(): \Generator
    {
        foreach ($this->config->hooks->remove ?? [] as $type => $hooks)
        {
            if ( ! isset(self::HOOK_TYPES[$type]))
            {
                continue;
            }

            foreach ($hooks as $tag => $hook)
            {
                if ( ! is_array($hook))
                {
                    continue;
                }

                if (empty($hook))
                {
                    $method = self::tagToMethodName("{$type}_{$tag}");

                    if (method_exists($this, $method))
                    {
                        yield [$type, $tag, [$this, $method]];
                    }
                }
                else
                {
                    $fns = array_filter(
                        $hook,
                        fn ($fn) => function_exists($fn)
                    );

                    foreach ($fns as $fn)
                    {
                        yield [$type, $tag, $fn];
                    }
                }
            }        
        }
    }

    function getAssetsToEnqueue(): \Generator
    {
        foreach ($this->config->assets->enqueue ?? [] as $type => $assets)
        {
            foreach ($assets as $asset)
            {
                unset($templates, $vars, $handle, $src, $deps, $ver, $media);
                extract($asset);

                if (empty($handle) || empty($src))
                {
                    continue;
                }

                if ("style" === $type)
                {
                    yield [
                        $templates ?? [],
                        $type,
                        $handle,
                        $src,
                        $deps ?? [],
                        $ver ?? false,
                        $media ?? "all"
                    ];
                }
                else if ("script" === $type)
                {
                    $vars = is_array($vars ?? [])
                        ? $vars ?? []
                        : [];

                    yield [
                        $templates ?? [],
                        $type,
                        $vars,
                        $handle,
                        $src,
                        $deps ?? [],
                        $ver ?? false,
                        $in_footer ?? true
                    ];
                }
            }
        }
    }

    function getAssetsToDequeue(): \Generator
    {
        foreach ($this->config->assets->dequeue ?? [] as $type => $assets)
        {
            foreach ($assets as $asset)
            {
                unset($templates, $handle);
                extract($asset);

                if (empty($handle))
                {
                    continue;
                }
                
                yield [$templates ?? [], $type, $handle];
            }
        }
    }

    static function ajax_action_send_message(): void
    {
        $email = \sanitize_email($_POST["email"]);
        $message = \sanitize_textarea_field($_POST["message"]);

        if (empty($email) || empty($message) || !is_email($email))
        {
            \wp_send_json_error("Invalid input data");
        }

        $to = "support@onbali.com";
        $subject = "Support Form Submission";

        $headers = [
            "Content-Type: text/html; charset=UTF-8",
            "From: support@onbali.com"
        ];

        $message_body = "<b>Email:</b> $email<br /><br />";
        $message_body .= "<b>Message:</b> $message<br />";

        $sent = \wp_mail($to, $subject, $message_body, $headers);

        if ($sent)
        {
            \wp_send_json_success("Message sent successfully");
        }
        else
        {
            \wp_send_json_error("Error sending message");
        }

        \wp_die();
    }

    function get_attachment_id_by_url( $url ): int
    {
        $parsed_url  = explode( parse_url( WP_CONTENT_URL, PHP_URL_PATH ), $url );
        $this_host = str_ireplace( "www.", "", parse_url( home_url(), PHP_URL_HOST ) );
        $file_host = str_ireplace( "www.", "", parse_url( $url, PHP_URL_HOST ) );

        if ( ! isset( $parsed_url[1] ) || empty( $parsed_url[1] ) || ( $this_host != $file_host ) ) {
            return 0;
        }

        global $wpdb;

        $attachment = $wpdb->get_col( $wpdb->prepare(
            /* SQL */"
                SELECT ID
                FROM {$wpdb->prefix}posts
                WHERE guid RLIKE %s;
            ",
            $parsed_url[1]
        ) );

        return (int) $attachment[0];
    }

    function onbali_get_menu_array($current_menu): array
    {
        $locations = \get_nav_menu_locations();

        if (empty($locations[$current_menu]))
        {
            return [];
        }

        $array_menu = \wp_get_nav_menu_items($locations[$current_menu]);

        $menu = [];

        if ( ! empty($array_menu))
        {
            foreach ($array_menu as $m)
            {
                if (empty($m->menu_item_parent))
                {
                    $menu[ $m->ID ]             = [];
                    $menu[ $m->ID ]["ID"]       = $m->ID;
                    $menu[ $m->ID ]["title"]    = $m->title;
                    $menu[ $m->ID ]["url"]      = $m->url;
                    $menu[ $m->ID ]["children"] = [];
                }
            }

            $submenu = [];

            foreach ( $array_menu as $m )
            {
                if ( $m->menu_item_parent )
                {
                    $submenu[ $m->ID ]                                  = [];
                    $submenu[ $m->ID ]["ID"]                            = $m->ID;
                    $submenu[ $m->ID ]["title"]                         = $m->title;
                    $submenu[ $m->ID ]["url"]                           = $m->url;
                    $menu[ $m->menu_item_parent ]["children"][ $m->ID ] = $submenu[ $m->ID ];
                }
            }
        }

        return $menu;
    }

    static function tagToMethodName(string $tag): string
    {
        return preg_replace("#[^\w\d_]+#", "_", $tag);
    }

    static function tagToClassName(string $tag, string $namespace): string
    {
        $parts = explode("_", $tag);
        $parts[] = $namespace;
        $parts = array_map("ucfirst", $parts);
        $className = implode("", $parts);

        return sprintf(
            "%s\\%s\\%s",
            __NAMESPACE__,
            $namespace,
            $className
        );
    }

    private function preventUppercaseUrl()
    {
        $url = $_SERVER['REQUEST_URI'];
        $params = (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');
        
        if ( preg_match('/[\.]/', $url) ) {
            return;
        }

        // If URL contains a capital letter
        if ( preg_match('/[A-Z]/', $url) ) {

            // Convert URL to lowercase
            $lc_url = empty($params)
                ? strtolower($url)
                : strtolower(substr($url, 0, strrpos($url, '?'))).'?'.$params;

            // if url was modified, re-direct
            if ($lc_url !== $url) {

            // 301 redirect to new lowercase URL
            header('Location: '.$lc_url, TRUE, 301);
            exit();

            }

        }
    }
}
