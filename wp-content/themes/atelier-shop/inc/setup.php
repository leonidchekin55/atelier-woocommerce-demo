<?php
if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce', ['thumbnail_image_width' => 700, 'single_image_width' => 1000, 'product_grid' => ['default_rows' => 3, 'min_rows' => 1, 'max_rows' => 8, 'default_columns' => 3, 'min_columns' => 2, 'max_columns' => 4]]);
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    register_nav_menus(['primary' => __('Primary navigation', 'atelier-shop'), 'footer' => __('Footer navigation', 'atelier-shop')]);
});

add_action('after_switch_theme', function () {
    if (get_option('atelier_setup_complete')) return;
    $pages = [
        'home' => ['Home', 'page-home.php'], 'shop' => ['Shop', ''], 'about' => ['Our story', 'page-about.php'],
        'journal' => ['Journal', 'page-journal.php'], 'contact' => ['Contact', 'page-contact.php'], 'faq' => ['FAQ', 'page-faq.php'],
    ];
    foreach ($pages as $slug => [$title, $template]) {
        $page = get_page_by_path($slug);
        if (!$page) $page_id = wp_insert_post(['post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => 'page']);
        else $page_id = $page->ID;
        if ($template && $page_id && !is_wp_error($page_id)) update_post_meta($page_id, '_wp_page_template', $template);
        if ($slug === 'shop' && function_exists('wc_get_page_id')) update_option('woocommerce_shop_page_id', $page_id);
        if ($slug === 'home' && $page_id && !is_wp_error($page_id)) { update_option('show_on_front', 'page'); update_option('page_on_front', $page_id); }
    }
    update_option('atelier_setup_complete', 1);
});

add_action('init', function () {
    if (!class_exists('WooCommerce') || get_option('atelier_woo_demo_configured')) return;
    update_option('woocommerce_coming_soon', 'no');
    update_option('woocommerce_store_pages_only', 'no');
    update_option('woocommerce_cod_settings', array_merge((array)get_option('woocommerce_cod_settings', []), [
        'enabled' => 'yes',
        'title' => 'Cash on delivery',
        'description' => 'Test order only. No online payment is collected.',
        'instructions' => 'Demo order — no fulfilment.',
        'enable_for_methods' => [],
    ]));
    update_option('permalink_structure', '/%postname%/');
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure('/%postname%/');
    foreach (['cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]'] as $page => $content) {
        $page_id = wc_get_page_id($page);
        if ($page_id > 0) {
            $post = get_post($page_id);
            if ($post && strpos($post->post_content, '[woocommerce_') === false) wp_update_post(['ID' => $page_id, 'post_content' => $content]);
        }
    }
    flush_rewrite_rules(false);
    update_option('atelier_woo_demo_configured', 1);
}, 30);

add_action('init', function () {
    if (!class_exists('WooCommerce') || get_option('atelier_demo_shipping_configured')) return;
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name('Atelier demo — United States');
    $zone->set_zone_order(1);
    $zone->add_location('US', 'country');
    $zone->save();

    $flat_rate_id = $zone->add_shipping_method('flat_rate');
    if ($flat_rate_id) update_option('woocommerce_flat_rate_' . $flat_rate_id . '_settings', [
        'title' => 'Standard delivery', 'tax_status' => 'none', 'cost' => '8.00',
    ]);

    $free_shipping_id = $zone->add_shipping_method('free_shipping');
    if ($free_shipping_id) update_option('woocommerce_free_shipping_' . $free_shipping_id . '_settings', [
        'title' => 'Free delivery', 'requires' => 'min_amount', 'min_amount' => '150.00', 'ignore_discounts' => 'no',
    ]);

    update_option('atelier_demo_shipping_configured', 1);
}, 30);

add_filter('woocommerce_enqueue_styles', '__return_empty_array');

// The advertised free-delivery threshold should not leave a paid rate selected.
add_filter('woocommerce_package_rates', function ($rates) {
    $free_rates = array_filter($rates, static function ($rate) {
        return $rate->get_method_id() === 'free_shipping';
    });
    return $free_rates ?: $rates;
}, 100);
