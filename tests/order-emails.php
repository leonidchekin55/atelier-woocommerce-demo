<?php
// Run with WP-CLI against a disposable local WordPress + WooCommerce installation.
// Every wp_mail call is intercepted here; this test cannot send email externally.
$GLOBALS['atelier_test_mail'] = [];
add_filter('pre_wp_mail', function ($return, $atts) {
    $GLOBALS['atelier_test_mail'][] = [
        'to' => $atts['to'] ?? '',
        'subject' => $atts['subject'] ?? '',
        'message' => $atts['message'] ?? '',
    ];
    return true;
}, 9999, 2);

foreach (['new_order', 'cancelled_order', 'customer_cancelled_order', 'customer_processing_order', 'customer_completed_order'] as $id) {
    add_filter('woocommerce_email_enabled_' . $id, '__return_true', 9999);
}
add_filter('woocommerce_email_recipient_new_order', function () { return 'owner@example.invalid'; }, 9999);

$created_orders = [];
try {
    $product = wc_get_products(['status' => 'publish', 'limit' => 1])[0] ?? null;
    if (!$product) throw new Exception('No published product is available.');

    foreach (['cancelled', 'completed'] as $final_status) {
        $order = wc_create_order();
        if (is_wp_error($order)) throw new Exception($order->get_error_message());
        $created_orders[] = $order->get_id();
        $order->set_billing_first_name('Fictional');
        $order->set_billing_last_name('Mail Check');
        $order->set_billing_email('fictional-order-check@example.invalid');
        $order->set_billing_address_1('1 Test Street');
        $order->set_billing_city('Test City');
        $order->set_billing_postcode('00000');
        $order->set_billing_country('US');
        $order->set_payment_method('cod');
        $order->add_product($product, 1);
        $order->calculate_totals();
        $order->save();
        $order->update_status('processing', 'Fictional email lifecycle check');
        $order->update_status($final_status, 'Fictional email lifecycle check');
    }

    $seen = [];
    foreach ($GLOBALS['atelier_test_mail'] as $mail) {
        $subject = (string) $mail['subject'];
        $body = wp_strip_all_tags((string) $mail['message']);
        if (strpos($subject, 'New order #') !== false && strpos($body, 'order was placed for demonstration') !== false) $seen['new_order'] = true;
        if (strpos($subject, 'order has been received') !== false && strpos($body, 'No payment will be collected') !== false) $seen['customer_processing_order'] = true;
        if (strpos($subject, 'has been cancelled') !== false && strpos($body, 'cancelled in the demonstration store') !== false) $seen['cancelled_order'] = true;
        if (strpos($subject, 'has been cancelled') !== false && strpos($body, 'It has been cancelled; no payment was collected') !== false) $seen['customer_cancelled_order'] = true;
        if (strpos($subject, 'is now complete') !== false && strpos($body, 'marked complete. No payment was collected') !== false) $seen['customer_completed_order'] = true;
    }
    $expected = ['new_order', 'customer_processing_order', 'cancelled_order', 'customer_cancelled_order', 'customer_completed_order'];
    $missing = array_values(array_diff($expected, array_keys($seen)));
    if ($missing) throw new Exception('Missing intercepted notices: ' . implode(', ', $missing));

    echo "PASS: owner and customer notices captured for processing, cancellation and completion. No external emails were sent.\n";
} finally {
    foreach ($created_orders as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) $order->delete(true);
        if (wc_get_order($order_id)) throw new Exception('Test order ' . $order_id . ' was not deleted.');
    }
}
