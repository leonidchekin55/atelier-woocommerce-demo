<?php
if (!defined('ABSPATH')) exit;

define('ATELIER_VERSION', '1.8.1');
require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/seed.php';
require_once get_template_directory() . '/inc/media.php';
require_once get_template_directory() . '/inc/filters.php';
require_once get_template_directory() . '/inc/forms.php';

/** Make WooCommerce's transactional messages clear and consistent for the demo store. */
function atelier_demo_email_additional_content($content) {
    $message = current_filter();
    $email_id = str_replace('woocommerce_email_additional_content_', '', $message);
    $messages = [
        'new_order' => 'Atelier Home demo notice: this order was placed for demonstration. No payment or fulfillment is required.',
        'cancelled_order' => 'Atelier Home demo notice: this order was cancelled in the demonstration store. No payment or fulfillment is required.',
        'customer_cancelled_order' => 'This was a demonstration order. It has been cancelled; no payment was collected and nothing will be shipped.',
        'customer_processing_order' => 'This is a demonstration order. No payment will be collected and no items will be shipped. You can continue exploring the store below.',
        'customer_completed_order' => 'This demonstration order has been marked complete. No payment was collected and no items were shipped. Thank you for trying Atelier Home.',
        'customer_on_hold_order' => 'This is a demonstration order. No payment will be collected and no items will be shipped.',
    ];

    if (!isset($messages[$email_id])) return $content;

    $demo_note = $messages[$email_id] . "\n\nExplore Atelier Home: " . home_url('/');
    return trim((string) $content) === '' ? $demo_note : rtrim((string) $content) . "\n\n" . $demo_note;
}

add_filter('woocommerce_email_footer_text', function ($text) {
    return 'Atelier Home — demo store. Orders are for demonstration only; no payment or fulfillment is processed. {site_url}';
});

add_filter('woocommerce_email_from_name', function ($name) {
    return 'Atelier Home (Demo)';
});

foreach ([
    'new_order' => 'New demo order',
    'cancelled_order' => 'Demo order cancelled',
    'customer_cancelled_order' => 'Your demo order was cancelled',
    'customer_processing_order' => 'Your Atelier Home demo order',
    'customer_completed_order' => 'Your Atelier Home demo order is complete',
    'customer_on_hold_order' => 'Your Atelier Home demo order',
] as $email_id => $heading) {
    add_filter('woocommerce_email_additional_content_' . $email_id, 'atelier_demo_email_additional_content', 10, 3);
    add_filter('woocommerce_email_heading_' . $email_id, static function ($current) use ($heading) { return $heading; });
    add_filter('woocommerce_email_subject_' . $email_id, static function ($current) { return 'Atelier Home demo — ' . $current; });
}

add_filter('woocommerce_email_styles', function ($css) {
    return $css . "\n/* Atelier Home: keep transactional email content readable on narrow screens. */\n"
        . '#template_container{width:100%!important;max-width:600px!important}'
        . '#template_body,#body_content{width:100%!important}'
        . '#body_content_inner{font-size:15px!important;line-height:1.65!important}'
        . '#body_content a{overflow-wrap:anywhere}'
        . '@media only screen and (max-width:600px){#template_container{width:100%!important}#body_content_inner{padding:20px 16px!important;font-size:14px!important}#header_wrapper{padding:22px 16px!important}}';
});

/** Give search and social previews useful, page-specific metadata without a plugin. */
function atelier_seo_description(): string {
    $is_ru = function_exists('atelier_language') && atelier_language() === 'ru';
    $is_product = function_exists('is_product') && is_product();
    $is_product_category = function_exists('is_product_category') && is_product_category();
    $is_product_tag = function_exists('is_product_tag') && is_product_tag();
    $is_shop = function_exists('is_shop') && is_shop();
    $description = '';

    if ($is_product) {
        $product = wc_get_product(get_queried_object_id());
        if ($product) $description = $product->get_description() ?: $product->get_short_description();
    } elseif ($is_product_category || $is_product_tag) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) $description = term_description($term);
    } elseif (is_singular()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) $description = has_excerpt($post) ? get_the_excerpt($post) : $post->post_content;
    } elseif (is_search()) {
        $description = $is_ru ? 'Найдите товары Atelier по названию и описанию.' : 'Find Atelier pieces by product name and description.';
    } elseif ($is_shop || $is_product_category || $is_product_tag) {
        $description = $is_ru
            ? 'Натуральные материалы, спокойные формы и вещи для повседневной жизни. Изучите коллекцию Atelier.'
            : 'Natural materials, enduring forms and thoughtful objects for everyday living. Explore the Atelier collection.';
    } else {
        $description = $is_ru
            ? 'Продуманные вещи для уютного дома: керамика, текстиль и предметы из натуральных материалов.'
            : 'Thoughtful homeware for a slower home: handmade ceramics, natural textiles and considered everyday objects.';
    }

    if ($is_ru && !preg_match('/\p{Cyrillic}/u', (string) $description) && function_exists('atelier_translate_storefront')) {
        $description = atelier_translate_storefront((string) $description);
    }
    $description = trim(wp_strip_all_tags(strip_shortcodes((string) $description)));
    if ($description === '') {
        $description = $is_ru
            ? 'Продуманные вещи для уютного дома: керамика, текстиль и предметы из натуральных материалов.'
            : 'Thoughtful homeware for a slower home: handmade ceramics, natural textiles and considered everyday objects.';
    }
    $description = preg_replace('/\\s+/u', ' ', $description);
    return wp_html_excerpt($description, 160, '…');
}

function atelier_seo_base_url(): string {
    if (is_singular()) return get_permalink(get_queried_object_id());
    if (is_search()) return get_search_link();
    if (function_exists('is_shop') && is_shop()) return wc_get_page_permalink('shop');
    if ((function_exists('is_product_category') && is_product_category()) || (function_exists('is_product_tag') && is_product_tag())) {
        $url = get_term_link(get_queried_object());
        return is_wp_error($url) ? home_url('/') : $url;
    }
    return home_url('/');
}

function atelier_seo_language_url(string $url, string $language): string {
    $url = remove_query_arg('atelier_lang', $url);
    return $language === 'ru' ? add_query_arg('atelier_lang', 'ru', $url) : $url;
}

add_filter('get_canonical_url', function ($url) {
    if (!$url || is_admin() || !function_exists('atelier_language')) return $url;
    return atelier_seo_language_url($url, atelier_language());
});

add_action('wp_head', function () {
    if (is_admin() || is_feed() || is_embed()) return;

    echo '<meta name="google-site-verification" content="8rv9o11ZOkUG51YwiTQ2jvBnVUAd4H8wSRHlqW1_f64">' . "\n";

    $is_product = function_exists('is_product') && is_product();
    $language = function_exists('atelier_language') ? atelier_language() : 'en';
    $title = wp_get_document_title();
    $description = atelier_seo_description();
    $base_url = atelier_seo_base_url();
    $url = atelier_seo_language_url($base_url, $language);
    $english_url = atelier_seo_language_url($base_url, 'en');
    $russian_url = atelier_seo_language_url($base_url, 'ru');
    $image = '';

    if ($is_product) {
        $product = wc_get_product(get_queried_object_id());
        if ($product && $product->get_image_id()) $image = wp_get_attachment_image_url($product->get_image_id(), 'large');
    }
    if (!$image) $image = get_template_directory_uri() . '/assets/images/photo-1616486338812-3dadae4b4ace-wide.webp';

    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    if (!is_singular() || !wp_get_canonical_url()) echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    echo '<link rel="alternate" hreflang="en-US" href="' . esc_url($english_url) . '">' . "\n";
    echo '<link rel="alternate" hreflang="ru-RU" href="' . esc_url($russian_url) . '">' . "\n";
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($english_url) . '">' . "\n";
    echo '<meta property="og:type" content="' . ($is_product ? 'product' : 'website') . '">' . "\n";
    echo '<meta property="og:locale" content="' . ($language === 'ru' ? 'ru_RU' : 'en_US') . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
}, 2);

add_filter('option_blogdescription', function ($description) {
    if (is_admin() || !function_exists('atelier_language')) return $description;
    return atelier_language() === 'ru'
        ? 'Продуманные вещи для уютного дома: керамика, текстиль и предметы из натуральных материалов.'
        : 'Thoughtful homeware for a slower home: handmade ceramics, natural textiles and everyday objects.';
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('atelier-fonts', get_template_directory_uri() . '/assets/css/fonts.css', [], ATELIER_VERSION);
    wp_enqueue_style('atelier-style', get_stylesheet_uri(), [], ATELIER_VERSION);
    wp_enqueue_style('atelier-main', get_template_directory_uri() . '/assets/css/main.css', ['atelier-style'], ATELIER_VERSION);
    wp_enqueue_style('atelier-editorial', get_template_directory_uri() . '/assets/css/editorial.css', ['atelier-main'], ATELIER_VERSION);
    wp_enqueue_style('atelier-forms', get_template_directory_uri() . '/assets/css/forms.css', ['atelier-editorial'], ATELIER_VERSION);
    wp_enqueue_style('atelier-commerce', get_template_directory_uri() . '/assets/css/commerce.css', ['atelier-forms'], ATELIER_VERSION);
    wp_enqueue_script('atelier-main', get_template_directory_uri() . '/assets/js/main.js', [], ATELIER_VERSION, true);
});

add_filter('body_class', function ($classes) {
    if (function_exists('is_woocommerce') && (is_woocommerce() || is_cart() || is_checkout() || is_account_page())) $classes[] = 'atelier-commerce';
    return $classes;
});

add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    ob_start(); ?><span class="cart-count"><?php echo esc_html(WC()->cart ? WC()->cart->get_cart_contents_count() : 0); ?></span><?php
    $fragments['.cart-count'] = ob_get_clean();
    return $fragments;
});
