<?php

namespace Seobrothers\WP\Plugin\Onbali\Service;

trait SearchService
{
    function searchPosts(string $search): array
    {
        global $wpdb;

        $termsRegexp = preg_replace(
            "#\s+#",
            "|",
            preg_quote(strtolower($search))
        );

        $locations = $this->searchLocations($termsRegexp);

        $sql = ! empty($locations)
            ? $this->locationBasedSql($search, $locations)
            : $this->titleBasedSql($termsRegexp);

        $sql .= "ORDER BY post_date DESC";

        return $wpdb->get_results($sql);
    }

    function searchLocations(string $search): array
    {
        global $wpdb;

        $sql = "
            SELECT post_name
            FROM {$wpdb->posts}
            WHERE post_type='location'
            AND post_status='publish'
            AND post_name REGEXP '^{$search}';
        ";
        
        return $wpdb->get_results($sql);
    }

    private function locationBasedSql(string $search, array $locations): string
    {
        global $wpdb;

        $terms = $this->getTerms($search);        
        $locationsRegexp = $this->getLocationsRegexp($locations);
        $termsRegexp = $this->getTermsRegexp($terms, $locations);

        If (empty($termsRegexp))
        {
            $termsRegexp = $locationsRegexp;
        }

        $sql = "
            SELECT DISTINCT p.*
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->term_relationships} AS tr
                ON (tr.object_id = p.ID)
            LEFT JOIN {$wpdb->term_taxonomy} AS tt
                ON (tt.term_taxonomy_id = tr.term_taxonomy_id)
            LEFT JOIN {$wpdb->terms} AS t
                ON (t.term_id = tt.term_id)
            WHERE p.post_type = 'post'
                AND p.post_status = 'publish'
                AND tt.taxonomy = 'category'
                AND (
                    t.slug regexp '{$locationsRegexp}'
                    AND p.post_name REGEXP '{$termsRegexp}'
                )
                OR (
                    t.slug regexp '{$termsRegexp}'
                    AND p.post_name REGEXP '{$locationsRegexp}'
                )
        ";

        return $sql;
    }

    private function titleBasedSql(string $searchRegexp): string
    {
        global $wpdb;

        return "
            SELECT *
            FROM {$wpdb->posts}
            WHERE post_type = 'post'
            AND post_status = 'publish'
            AND post_name REGEXP '{$searchRegexp}'
        ";
    }

    private function getLocationsRegexp(array $locations): string
    {
        return implode(
            "|",
            array_reduce(
                $locations,
                fn ($c, $i) => $c + [
                    count($c) => sprintf(
                        "(%s)",
                        str_replace("All ", "", $i->post_name)
                    )
                ],
                []
            )
        );
    }

    private function getTerms(string $search): array
    {
        return explode(
            " ",
            preg_replace(
                "#\s+#",
                " ",
                strtolower($search)
            )
        );
    }

    private function getTermsRegexp(array $terms, array $locations): string
    {
        $locationsName = array_map(
            fn ($i) => str_replace("All ", "", $i->post_name),
            $locations
        );

        return implode(
            "|",
            array_reduce(
                array_filter(
                    $terms,
                    fn ($t) => array_reduce(
                        $locationsName,
                        fn ($p, $n) => $p && ! preg_match("#(^".$t.")|(".$t."$)#", $n),
                        true
                    )
                ),
                fn ($c, $i) => $c + [
                    count($c) => sprintf(
                        "(%s)",
                        preg_quote(strtolower($i))
                    )
                ],
                []
            )
        );
    }
}