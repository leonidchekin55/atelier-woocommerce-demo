<?php
/** Plugin Name: Atelier durable demo state */
if (!defined('ABSPATH')) exit;
if (!class_exists('Atelier_State') || !Atelier_State::enabled()) return;

function atelier_persist_now() {
    try {
        if (function_exists('WC') && WC()->session) WC()->session->save_data();
        Atelier_State::save();
        $GLOBALS['atelier_state_dirty'] = false;
        return true;
    } catch (Throwable $error) {
        return new WP_Error('atelier_storage_unavailable', 'We could not save your changes safely. Please try again in a moment.');
    }
}
function atelier_persist_or_throw() {
    $result = atelier_persist_now();
    if (is_wp_error($result)) throw new Exception($result->get_error_message());
}
function atelier_mark_state_dirty() { $GLOBALS['atelier_state_dirty'] = true; }
add_action('woocommerce_after_order_object_save', 'atelier_mark_state_dirty');
add_action('woocommerce_delete_order', 'atelier_mark_state_dirty');
add_action('woocommerce_trash_order', 'atelier_mark_state_dirty');
add_action('woocommerce_untrash_order', 'atelier_mark_state_dirty');
add_action('deleted_post', 'atelier_mark_state_dirty');
foreach (['profile_update', 'user_register', 'deleted_user', 'created_term', 'edited_term', 'delete_term'] as $hook) add_action($hook, 'atelier_mark_state_dirty');
add_action('save_post', function ($id, $post) {
    if (!wp_is_post_revision($id) && in_array($post->post_type, ['atelier_message', 'atelier_subscriber', 'product', 'page', 'post', 'attachment'], true)) $GLOBALS['atelier_state_dirty'] = true;
}, 10, 2);
add_filter('woocommerce_payment_successful_result', function ($result) { atelier_persist_or_throw(); return $result; }, 999);
add_filter('woocommerce_checkout_no_payment_needed_redirect', function ($url) { atelier_persist_or_throw(); return $url; }, 999);
add_filter('wp_redirect', function ($url) {
    if (!empty($GLOBALS['atelier_state_dirty'])) {
        $result = atelier_persist_now();
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()), 'Save unavailable', ['response' => 503]);
    }
    return $url;
}, 999);
add_filter('rest_post_dispatch', function ($response) {
    if (!empty($GLOBALS['atelier_state_dirty'])) {
        $result = atelier_persist_now();
        if (is_wp_error($result)) return new WP_REST_Response(['code' => $result->get_error_code(), 'message' => $result->get_error_message()], 503);
    }
    return $response;
}, 999);
add_action('shutdown', function () {
    if (!empty($GLOBALS['atelier_state_dirty']) && is_wp_error(atelier_persist_now())) $GLOBALS['atelier_state_save_failed'] = true;
}, 0); // Before WordPress flushes output buffers at priority 1.
add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget('atelier_recovery', 'Atelier data recovery', function () {
        $status = Atelier_State::status();
        echo '<p>Orders, inbox messages, subscribers and uploaded files are included in encrypted remote recovery copies.</p><p>Last saved: <strong>' . esc_html($status['saved_at'] ?? 'Restored at startup; awaiting the next save') . '</strong>.</p><p>Recovery is limited to 20 MiB of encrypted media and 64 MiB expanded. Old Git history is compacted after ten snapshots.</p>';
    });
});
