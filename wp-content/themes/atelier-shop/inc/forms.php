<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('atelier_message', [
        'labels' => ['name' => 'Contact inbox', 'singular_name' => 'Contact message', 'menu_name' => 'Contact inbox'],
        'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
        'show_ui' => true, 'show_in_menu' => true, 'supports' => ['title', 'editor'],
        'capability_type' => 'post', 'map_meta_cap' => true, 'has_archive' => false, 'rewrite' => false,
    ]);
    register_post_type('atelier_subscriber', [
        'labels' => ['name' => 'Newsletter list', 'singular_name' => 'Subscriber', 'menu_name' => 'Newsletter list'],
        'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
        'show_ui' => true, 'show_in_menu' => true, 'supports' => ['title'],
        'capability_type' => 'post', 'map_meta_cap' => true, 'has_archive' => false, 'rewrite' => false,
    ]);
});

add_action('admin_post_nopriv_atelier_contact', 'atelier_handle_contact');
add_action('admin_post_atelier_contact', 'atelier_handle_contact');
function atelier_post_string($key, $multiline = false) {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) return '';
    $value = wp_unslash($value);
    return $multiline ? sanitize_textarea_field($value) : sanitize_text_field($value);
}
function atelier_form_status($key) {
    $value = $_GET[$key] ?? '';
    return is_string($value) ? sanitize_key(wp_unslash($value)) : '';
}

function atelier_handle_contact() {
    check_admin_referer('atelier_contact', 'atelier_contact_nonce');
    $name = atelier_post_string('name');
    $email = sanitize_email(atelier_post_string('email'));
    $message = atelier_post_string('message', true);
    $consent = isset($_POST['consent']) && is_string($_POST['consent']) && '1' === $_POST['consent'];
    $trap = atelier_post_string('company');
    if ($trap !== '') atelier_form_redirect('atelier_contact', 'sent', '/contact/');
    if ($name === '' || strlen($name) > 120 || !is_email($email) || strlen($email) > 254 || $message === '' || !$consent || strlen($message) > 5000) {
        atelier_form_redirect('atelier_contact', 'error', '/contact/');
    }
    $id = wp_insert_post([
        'post_type' => 'atelier_message', 'post_status' => 'private',
        'post_title' => sprintf('%s — %s', $name, $email),
        'post_content' => "Email: {$email}\n\n{$message}",
    ], true);
    if (is_wp_error($id)) atelier_form_redirect('atelier_contact', 'error', '/contact/');
    update_post_meta($id, '_atelier_email', $email);
    if (function_exists('atelier_persist_now') && is_wp_error(atelier_persist_now())) {
        wp_delete_post($id, true);
        atelier_form_redirect('atelier_contact', 'error', '/contact/');
    }
    atelier_form_redirect('atelier_contact', 'sent', '/contact/');
}

add_action('admin_post_nopriv_atelier_newsletter', 'atelier_handle_newsletter');
add_action('admin_post_atelier_newsletter', 'atelier_handle_newsletter');
function atelier_handle_newsletter() {
    check_admin_referer('atelier_newsletter', 'atelier_newsletter_nonce');
    $email = sanitize_email(atelier_post_string('email'));
    $consent = isset($_POST['consent']) && is_string($_POST['consent']) && '1' === $_POST['consent'];
    $trap = atelier_post_string('company');
    if ($trap !== '') atelier_form_redirect('atelier_newsletter', 'stored', '/');
    if (!is_email($email) || strlen($email) > 254 || !$consent) atelier_form_redirect('atelier_newsletter', 'error', '/');
    $existing = get_posts([
        'post_type' => 'atelier_subscriber', 'post_status' => 'private', 'numberposts' => 1,
        'fields' => 'ids', 'meta_key' => '_atelier_email', 'meta_value' => $email,
    ]);
    if (!$existing) {
        $id = wp_insert_post([
            'post_type' => 'atelier_subscriber', 'post_status' => 'private',
            'post_title' => $email, 'post_content' => '',
        ], true);
        if (is_wp_error($id)) atelier_form_redirect('atelier_newsletter', 'error', '/');
        update_post_meta($id, '_atelier_email', $email);
    }
    if (function_exists('atelier_persist_now') && is_wp_error(atelier_persist_now())) atelier_form_redirect('atelier_newsletter', 'error', '/');
    atelier_form_redirect('atelier_newsletter', 'stored', '/');
}

function atelier_form_redirect($key, $status, $path) {
    wp_safe_redirect(add_query_arg($key, $status, home_url($path)));
    exit;
}
