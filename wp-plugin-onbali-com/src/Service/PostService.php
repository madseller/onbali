<?php

namespace Seobrothers\WP\Plugin\Onbali\Service;

trait PostService
{
    function getOtherArticles($limit, $image_size = [300, 300]): \Generator
    {
        global $post;
        
        $args = [
            "post__not_in" => [$post->ID],
            "orderby" => "rand",
            "order" => "DESC",
            "posts_per_page" => $limit
        ]
            + (
                ($category_name = get_query_var("category_name"))
                    ? ["category_name" => $category_name]
                    : []
            );

        $query = new \WP_Query($args);

        foreach ($query->posts as $post)
        {
            $post->{"thumbnail"} = \has_post_thumbnail()
                    ? \wp_get_attachment_image_src(
                        \get_post_thumbnail_id($post->ID),
                        $image_size
                    )
                    : false;
            
            $post->post_date_without_time = date("d.m.Y", strtotime($post->post_date));

            \setup_postdata($post);

            yield $post;
        }

        \wp_reset_postdata();
    }

    function getLocation(string $name): ?\WP_Post
    {
        $args = [
            "post_type" => "location",
            "post_status" => "publish",
            "post_name__in" => [$name]
        ];

        return \get_posts($args)[0] ?? null;
    }

    function getLocations(): \Generator
    {
        $args = [
            "post_type" => "location",
            "post_status" => "publish",
            "orderby" => [
                "menu_order" => "ASC",
                "post_date" => "DESC"
            ],
            "posts_per_page" => -1
        ];

        $query = new \WP_Query( $args );

        foreach ($query->posts as $post)
        {
            $post->featured_image = \get_the_post_thumbnail_url(
                $post->ID,
                "full"
            );

            \setup_postdata($post);

            yield $post;
        }

        \wp_reset_postdata();
    }

    function getCategory(string $slug): ?\WP_Term
    {
        return \get_category_by_slug($slug) ?: null;
    }

    function getQueryArgs(string $id = "last_published"): \Closure
    {
        return  match ($id)
        {
            "last_published" => fn(?string $type, int $limit = -1) => [
                "post_type" => $type ?? "post",
                "posts_per_page" => $limit,
                "post_status" => "publish"
            ],
            "has_category" => fn(?string $name) => [
                "category_name" => $name ?? \get_query_var("category_name")
            ],
            "has_meta" => fn(string $key, mixed $value) =>  [
                "meta_key" => $key,
                "meta_value" => $value
            ],
            default => []
        };
    }

    function getPosts(?string $category_name = null, ?int $locationID = null, int $limit = -1): \Generator
    {
        $args = $this->getQueryArgs("last_published")("post", $limit);

        if ($category_name)
        {
            $args += $this->getQueryArgs("has_category")($category_name);
        }

        if ( ! is_null($locationID) && 0 <= $locationID)
        {
            $args += $this->getQueryArgs("has_meta")("location", $locationID);
        }

        return $this->queryPosts($args);
    }

    function getNews($location = null, int $limit = 8): \Generator
    {
        return $this->getPosts("news", $location, $limit);
    }

    function getRestaurants($location = null, int $limit = 8): \Generator
    {
        return $this->getPosts("where-to-go-in-bali", $location, $limit);
    }

    function getSpa($location = null, int $limit = 9): \Generator
    {
        return $this->getPosts("best-bali-spa", $location, $limit);
    }

    function getAccommodations($location = null, int $limit = 8): \Generator
    {
        return $this->getPosts("best-bali-accommodations", $location);
    }

    function getActivities($location = null, int $limit = 8): \Generator
    {
        return $this->getPosts("best-bali-activities", $location, $limit);
    }

    private function queryPosts(array $args): \Generator
    {
        $query = new \WP_Query($args);

        foreach ($query->posts as $post)
        {
            \setup_postdata($post);
            
            yield $this->setUpPost($post);
        }

        \wp_reset_postdata();
    }

    private function setUpPost(\WP_Post $post): \WP_Post
    {
        $post->post_date = \date_i18n(
            "d.m.Y",
            strtotime($post->post_date)
        );

        $post->{"author_id"} = \get_the_author_meta("ID");

        $post->{"author_name"} = \get_the_author_meta(
            "display_name",
            $post->post_author
        );

        $post->{"formatted_date"} = \wp_date(
            \get_option("date_format"),
            \get_post_timestamp($post->ID)
        );

        $post->{"featured_image"} = self::arrayToObject([
            "src" => [
                "full" => \get_the_post_thumbnail_url($post->ID, "full"),
                "thumbnail" => \get_the_post_thumbnail_url(
                    $post->ID,
                    [300, 300]
                )
            ],
            "alt" => \get_post_meta(
                \get_post_thumbnail_id($post->ID),
                "_wp_attachment_image_alt",
                true
            )
        ]);

        $post->{"location"} = \get_field("location", $post->ID);
        $post->{"rating"} = \get_field("rating", $post->ID);

        return $post;
    }
}
