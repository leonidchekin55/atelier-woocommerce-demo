<?php
/** Plugin Name: Atelier clean public sitemaps */
if (!defined('ABSPATH')) exit;

add_filter('wp_sitemaps_posts_query_args', function (array $args, string $post_type): array {
    $excluded_ids = [];

    if ($post_type === 'page') {
        foreach (['cart', 'checkout', 'myaccount'] as $page) {
            if (function_exists('wc_get_page_id')) {
                $page_id = wc_get_page_id($page);
                if ($page_id > 0) $excluded_ids[] = $page_id;
            }
        }

        $sample_page = get_page_by_path('sample-page', OBJECT, 'page');
        if ($sample_page instanceof WP_Post) $excluded_ids[] = $sample_page->ID;
    }

    if ($post_type === 'post') {
        $sample_post = get_page_by_path('hello-world', OBJECT, 'post');
        if ($sample_post instanceof WP_Post) $excluded_ids[] = $sample_post->ID;
    }

    if (!$excluded_ids) return $args;

    $existing_ids = isset($args['post__not_in']) ? (array) $args['post__not_in'] : [];
    $args['post__not_in'] = array_values(array_unique(array_merge($existing_ids, $excluded_ids)));
    return $args;
}, 10, 2);

add_filter('wp_sitemaps_add_provider', function ($provider, string $name) {
    return $name === 'users' ? false : $provider;
}, 10, 2);

add_filter('wp_robots', function (array $robots): array {
    if (is_author()) $robots['noindex'] = true;
    return $robots;
});

add_filter('rest_endpoints', function (array $endpoints): array {
    if (is_user_logged_in()) return $endpoints;

    foreach (array_keys($endpoints) as $route) {
        if (strpos($route, '/wp/v2/users') === 0) unset($endpoints[$route]);
    }

    return $endpoints;
});

add_action('template_redirect', function (): void {
    if (!is_author()) return;

    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    nocache_headers();
}, 0);
