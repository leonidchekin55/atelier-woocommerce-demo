<?php
if (!defined('ABSPATH')) exit;

define('ATELIER_VERSION', '1.3.0');
require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/seed.php';
require_once get_template_directory() . '/inc/media.php';
require_once get_template_directory() . '/inc/filters.php';
require_once get_template_directory() . '/inc/forms.php';

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
