<?php
if (!defined('ABSPATH')) exit;

define('ATELIER_VERSION', '1.6.0');
require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/seed.php';
require_once get_template_directory() . '/inc/media.php';
require_once get_template_directory() . '/inc/filters.php';
require_once get_template_directory() . '/inc/forms.php';

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
