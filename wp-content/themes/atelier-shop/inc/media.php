<?php
if (!defined('ABSPATH')) exit;

// Use real attachments so galleries, cart thumbnails and Store API agree.
add_action('init', function () {
    if (!class_exists('WooCommerce') || get_option('atelier_product_media_seeded')) return;
    $lock_key = 'atelier_product_media_lock';
    if (!add_option($lock_key, time(), '', false)) {
        if (time() - (int)get_option($lock_key) <= 300) return;
        delete_option($lock_key);
        if (!add_option($lock_key, time(), '', false)) return;
    }

    try {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
        $complete = true;
        $demo_count = 0;
        foreach ($products as $product) {
            $photo = get_post_meta($product->get_id(), '_atelier_photo', true);
            if (!$photo) continue;
            $demo_count++;
            if ($product->get_image_id()) {
                $attachment_id = $product->get_image_id();
                $destination = get_attached_file($attachment_id);
                if (is_file($destination)) continue;
                $source = get_template_directory() . '/assets/images/' . sanitize_file_name($photo) . '.webp';
                if (!$destination || !is_file($source) || !wp_mkdir_p(dirname($destination)) || !copy($source, $destination)) {
                    $complete = false;
                    continue;
                }
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $destination));
                continue;
            }

            $attachments = get_posts([
                'post_type' => 'attachment', 'post_status' => 'inherit',
                'meta_key' => '_atelier_photo', 'meta_value' => $photo,
                'numberposts' => 1, 'fields' => 'ids',
            ]);
            $attachment_id = $attachments ? (int)$attachments[0] : 0;
            if (!$attachment_id || !is_file(get_attached_file($attachment_id))) {
                $source = get_template_directory() . '/assets/images/' . sanitize_file_name($photo) . '.webp';
                $uploads = wp_upload_dir();
                if (!is_file($source) || !empty($uploads['error'])) {
                    $complete = false;
                    continue;
                }
                $filename = wp_unique_filename($uploads['path'], basename($source));
                $destination = $uploads['path'] . '/' . $filename;
                if (!copy($source, $destination)) {
                    $complete = false;
                    continue;
                }
                $attachment_id = wp_insert_attachment([
                    'post_mime_type' => 'image/webp', 'post_title' => 'Atelier demo photograph',
                    'post_status' => 'inherit',
                ], $destination, 0, true);
                if (is_wp_error($attachment_id)) {
                    wp_delete_file($destination);
                    $complete = false;
                    continue;
                }
                update_post_meta($attachment_id, '_atelier_photo', $photo);
                update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Atelier homeware collection');
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $destination));
            }
            $product->set_image_id($attachment_id);
            $product->save();
        }
        if ($complete && $demo_count >= 12) update_option('atelier_product_media_seeded', 1);
    } finally {
        delete_option($lock_key);
    }
}, 105);

// Shared demo photos still describe the individual product to assistive tools.
add_filter('woocommerce_gallery_image_html_attachment_image_params', function ($attributes, $attachment_id) {
    if (!is_product() || !get_post_meta($attachment_id, '_atelier_photo', true)) return $attributes;
    $product = wc_get_product(get_queried_object_id());
    if ($product && (int)$product->get_image_id() === (int)$attachment_id) {
        $attributes['alt'] = $product->get_name();
    }
    return $attributes;
}, 10, 2);
