<?php

namespace Seobrothers\WP\Plugin\Onbali\Hook;

trait FilterHook
{
    function filter_acf_settings_remove_wp_meta_box(): mixed
    {
        return false;
    }

    function filter_auto_update_plugin(): mixed
    {
        return false;
    }

    function filter_pre_site_transient_update_plugins(): mixed
    {
        return null;
    }
    
    function filter_rank_math_frontend_breadcrumb_html($html, $crumbs, $class): string
    {        
        $new_html = "<nav aria-label=\"breadcrumbs\" class=\"ob-crumbs\">";
        $last_index = count($crumbs) - 1;

        foreach ($crumbs as $index => $crumb)
        {
            if ($index < $last_index && isset($crumb[1]) && strpos($crumb[1], "/category/") !== false)
            {
                $crumb[1] = str_replace("category/", "", $crumb[1]);
            }

            if ($index === $last_index && $index > 0)
            {
                $new_html .= "<p class=\"last\">" . esc_html($crumb[0]) . "</p>";
            }
            else
            {
                $new_html .= "<a href=\"" . esc_url($crumb[1]) . "\">" . esc_html($crumb[0]) . "</a>";
            }
        }

        $new_html .= "</nav>";

        return $new_html;
    }

    function filter_embed_oembed_html(string $html): string
    {
        return $html
            ? "<div class=\"ob-content-yt\">{$html}</div>"
            : "";
    }

    function filter_acf_load_field_name_services(array $field): array
    {
        foreach ($this->shortcodes as $shortcode)
        {
            if (method_exists($shortcode, $method = __FUNCTION__))
            {
                $field = $shortcode->$method($field) + $field;
            }
        }

        return $field;
    }

    function filter_posts_results(array $posts, \WP_Query $query): array
    {
        if (\is_admin() || ! ($query->is_search() && $query->is_main_query()))
        {
            return $posts;
        }

        \remove_filter("posts_results", [$this, __FUNCTION__], PHP_INT_MAX);

        $posts = $this->searchPosts($query->get("s"));

        \add_filter("posts_results", [$this, __FUNCTION__], PHP_INT_MAX, 2);

        return $posts;
    }

    function filter_post_type_link(string $permalink, \WP_Post $post): string
    {
        if (
            "entity" === $post->post_type
            && "publish" === $post->post_status
        )
        {
            $permalink = home_url($post->post_name);
        }
    
        return $permalink;
    }

    function filter_request($query_vars)
    {
        if(
            is_admin()
            || isset($query_vars["post_type"])
            && ! (
                (
                    isset($query_vars["error"])
                    && $query_vars["error"] == 404
                )
                || isset($query_vars["pagename"])
                || isset($query_vars["attachment"])
                || isset($query_vars["name"])
            )
        )
        {
            return $query_vars;
        }

        $web_root = \home_url();

        // get clean current URL path
        $path = $this->get_current_url();
        $path = str_replace( $web_root, '', $path );
        $path = trim( $path, '/' );

        // clean custom rewrite endpoints
        $path = explode( '/', $path );

        foreach( $path as $i => $path_part ){
            if( isset( $query_vars[ $path_part ] ) ){
                $path = array_slice( $path, 0, $i );
                break;
            }
        }

        $path = implode( '/', $path );

        // test for posts
        $post_data = \get_page_by_path( $path, OBJECT, 'post' );

        if($post_data instanceof \WP_Post) return $query_vars;

        // echo '#1<br>';
        // test for pages
        $post_data = \get_page_by_path( $path );

        if(is_object( $post_data ) )  return $query_vars;

        // echo '#2<br>';
        // test for selected CPTs
        $post_data = \get_page_by_path( $path, OBJECT, "entity" );

        if( is_object( $post_data ) )
        {
            unset( $query_vars['error'] );
            unset( $query_vars['pagename'] );
            unset( $query_vars['attachment'] );
            unset( $query_vars['category_name'] );

            $query_vars['page'] = '';
            $query_vars['name'] = $path;
            $query_vars['post_type'] = $post_data->post_type;
            $query_vars[ $post_data->post_type ] = $path;
        }
        else
        {
            // echo '#5<br>';
            // deeper matching
            global $wp_rewrite;
            // test all selected CPTs
            $post_type = "entity";

            // get CPT slug and its length
            $query_var = \get_post_type_object( $post_type )->query_var;

            // test all rewrite rules
            foreach( $wp_rewrite->rules as $pattern => $rewrite )
            {
                // test only rules for this CPT
                if( strpos( $pattern, $query_var ) === false ) continue;

                    // echo '#6<br>';
                if( strpos( $pattern, '(' . $query_var . ')' ) === false )
                {
                    // echo '#7<br>';
                    preg_match_all( '#' . $pattern . '#', '/' . $query_var . '/' . $path, $matches, PREG_SET_ORDER );
                }
                else
                {
                    // echo '#8<br>';
                    preg_match_all( '#' . $pattern . '#', $query_var . '/' . $path, $matches, PREG_SET_ORDER );
                }

                if( count( $matches ) === 0 || ! isset( $matches[0] ) ) continue;

                
                // echo '#9<br>';
                // build URL query array
                $rewrite = str_replace( 'index.php?', '', $rewrite );

                parse_str( $rewrite, $url_query );

                foreach( $url_query as $key => $value )
                {
                    $value = (int)str_replace( array( '$matches[', ']' ), '', $value );

                    if( isset( $matches[0][ $value ] ) )
                    {
                        $value = $matches[0][ $value ];
                        $url_query[ $key ] = $value;
                    }
                }

                // test new path for selected CPTs
                if( ! isset( $url_query[ $query_var ] ) ) continue;
                
                // echo '#10<br>';
                $post_data = \get_page_by_path( '/' . $url_query[ $query_var ], OBJECT, "entity" );
                
                if( ! is_object( $post_data ) ) continue;
                
                // echo '#11<br>';
                unset( $query_vars['error'] );
                unset( $query_vars['pagename'] );
                unset( $query_vars['attachment'] );
                unset( $query_vars['category_name'] );

                $query_vars['page'] = '';
                $query_vars['name'] = $path;
                $query_vars['post_type'] = $post_data->post_type;
                $query_vars[ $post_data->post_type ] = $path;

                // solve custom rewrites, pagination, etc.
                foreach( $url_query as $key => $value )
                {
                    if( $key != 'post_type' && substr( $value, 0, 8 ) != '$matches' ){
                        $query_vars[ $key ] = $value;
                    }
                }
            }
        }
        
        return $query_vars;
    }

    function get_current_url()
    {
		$REQUEST_URI = strtok( $_SERVER['REQUEST_URI'], '?' );
		$real_url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) ? 'https://' : 'http://';
		$real_url .= $_SERVER['SERVER_NAME'] . $REQUEST_URI;
		return $real_url;
	}

    function filter_wp_handle_upload_prefilter(array $file): array
    {
        $limit = 150;
        $size = ceil($file["size"] / 1024);

        if ($size > $limit)
        {
            $file["error"] = "File size limit is {$limit} KB.";
        }
    
        return $file;
    }

    function filter_upload_size_limit(int $bytes): int
    {
        return 153600;
    }

    function filter_wp_handle_upload(array $file): array
    {
        if (
            preg_match('#^image\/#', $file["type"])
            && ! preg_match('#^(jpeg|png)$#', $file["type"])
        )
        {
            $output = [];
            $cmd = sprintf(
                'ffmpeg -i "%s" -y "%s" -nostats -loglevel 0',
                $file["file"],
                $file_jpg = preg_replace('#\.[^.]+$#', '.jpg', $file["file"])
            );

            exec($cmd, $output);

            if (empty($output))
            {
                $file["file"] = $file_jpg;
            }
            else
            {
                $file["error"] = implode("\n", $output);
            }
        }

        return $file;
    }
}
