<?php

namespace Seobrothers\WP\Plugin\Onbali\Shortcode;

final class HotelsBookingShortcode
{
    public readonly array $bookingServices;

    function __construct
    (
        public readonly string $description = ""
    )
    {

    }

    function getBookingServices(array $names = []): array
    {
        return array_reduce(
            array_filter(
                \get_field("booking_services", "option") ?: [],
                fn ($service) =>
                    isset(
                        $service["name"],
                        $service["logo"]
                    )
                    && (
                        empty($names)
                        || in_array($service["name"], $names)
                    )
            ),
            fn ($services, $service) => $services + [
                $service["name"] => [
                    "logo" => $service["logo"]
                ]
            ],
            []
        );
    }

    function render(array $atts, ?string $content, string $tag): string
    {
        $hotels = array_filter(
            \get_field("hotels") ?: [],
            fn ($hotel) =>
                isset(
                    $hotel["name"],
                    $hotel["booking"],
                    $atts["name"]
                )
                && $hotel["name"] === $atts["name"]
                && is_array($hotel["booking"])
                && ! empty($hotel["booking"])
        );

        if (empty($hotels)) return "<!-- empty hotels -->";

        $hotel = array_shift($hotels) ?? [];

        $hotelName = $hotel["name"];
        $hotelBooking = $hotel["booking"];

        if (empty($hotelName)) return "<!-- empty hotel name -->";

        ob_start();

        \get_template_part(
            "partials/shortcode/hotels-booking",
            null,
            [
                "hotelName" => $hotelName,
                "hotelBooking" => $hotelBooking,
                "bookingServices" => $this->getBookingServices()
            ]
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
        
        $hotels = \get_field("hotels") ?: [];

        if ( ! empty($hotels)) return;

        $document = new \DOMDocument();
        $document->loadHTML($post->post_content);

        $xpath = new \DOMXPath($document);
        $textNodes = "//text()";
        $hotelNames = [];

        foreach ([...$xpath->query($textNodes)] as $node)
        {
            $matches = [];

            preg_match_all(
                '#\[hotels_booking name="([^"]+)"\]#',
                trim($node->nodeValue),
                $matches
            );

            if ( ! empty($matches[1]))
            {
                $hotelNames[] = $matches[1][0];
            }
        }

		if (empty($hotelNames)) return;

        $hotels = array_reduce(
            $hotelNames,
            fn ($hotels, $hotelName) => $hotels + [
                count($hotels) => [
                    "name" => $hotelName,
                    "booking" => []
                ]
            ],
            []
        );

        \update_field("hotels", $hotels);
    }

    function action_wp_enqueue_media(string $assetsUrl): void
    {
        if ("post" !== get_current_screen()->id) return;
        if ( ! has_category("best-bali-accommodations", get_the_ID())) return;

        \wp_enqueue_script(
            "hotels-booking-js",
            "{$assetsUrl}/js/admin/hotels-booking.js",
            [],
            filemtime(ABSPATH . "{$assetsUrl}/js/admin/hotels-booking.js"),
            true
        );
    }

    function action_media_buttons(): void
    {        
        if ("post" !== get_current_screen()->id) return;
        if ( ! has_category("best-bali-accommodations", get_the_ID())) return;

        ob_start();

            add_thickbox();
            \get_template_part("admin/post/editor/hotels-booking", null, [
                "description" => $this->description ?? ""
            ]);
        
        echo ob_get_clean();
    }
    
    function filter_acf_load_field_name_services(array $field): array
    {
        $bookingServices = \get_field("booking_services", "option") ?: [];

        if (empty($bookingServices)) return $field;

        $field["choices"] = array_map(
            fn ($service) => $service["name"],
            array_filter(
                $bookingServices,
                fn ($service) => isset($service["name"])
            )
        );
    
        return $field;
    }
}