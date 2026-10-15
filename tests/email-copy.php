<?php
// Run with WP-CLI against a disposable WordPress + WooCommerce installation.
if (!function_exists('apply_filters') || !function_exists('home_url')) {
    fwrite(STDERR, "WordPress must be loaded.\n");
    exit(1);
}

$expected = [
    'new_order' => 'No payment or fulfillment is required.',
    'cancelled_order' => 'cancelled in the demonstration store',
    'customer_cancelled_order' => 'It has been cancelled; no payment was collected',
    'customer_processing_order' => 'No payment will be collected and no items will be shipped.',
    'customer_completed_order' => 'marked complete. No payment was collected',
    'customer_on_hold_order' => 'No payment will be collected and no items will be shipped.',
];

foreach ($expected as $email_id => $phrase) {
    $email = (object) ['id' => $email_id];
    $content = apply_filters('woocommerce_email_additional_content_' . $email_id, '', null, $email);
    if (strpos($content, $phrase) === false || strpos($content, home_url('/')) === false) {
        fwrite(STDERR, "Missing demo instructions or store link for {$email_id}.\n");
        exit(1);
    }
    $heading = apply_filters('woocommerce_email_heading_' . $email_id, 'Original heading');
    if (stripos($heading, 'demo') === false) {
        fwrite(STDERR, "Missing demo label in heading for {$email_id}.\n");
        exit(1);
    }
    $subject = apply_filters('woocommerce_email_subject_' . $email_id, 'Order update');
    if (strpos($subject, 'Atelier Home demo') !== 0) {
        fwrite(STDERR, "Missing Atelier Home brand in subject for {$email_id}.\n");
        exit(1);
    }
}

$footer = apply_filters('woocommerce_email_footer_text', 'Original footer');
if (strpos($footer, 'Atelier Home') === false || strpos($footer, (string) wp_parse_url(home_url('/'), PHP_URL_HOST)) === false) {
    fwrite(STDERR, "Missing branded footer or resolved site link.\n");
    exit(1);
}
$from_name = apply_filters('woocommerce_email_from_name', 'Original sender');
if ($from_name !== 'Atelier Home (Demo)') {
    fwrite(STDERR, "Missing Atelier Home demo sender name.\n");
    exit(1);
}
$styles = apply_filters('woocommerce_email_styles', '');
if (strpos($styles, '@media only screen and (max-width:600px)') === false || strpos($styles, 'max-width:600px') === false) {
    fwrite(STDERR, "Missing narrow-screen email styles.\n");
    exit(1);
}
$other_email = (object) ['id' => 'customer_new_account'];
if (apply_filters('woocommerce_email_additional_content_customer_new_account', '', null, $other_email) !== '') {
    fwrite(STDERR, "Unrelated account email was changed.\n");
    exit(1);
}

echo "PASS: transactional demo copy, branding, links, mobile styles and unrelated email scope.\n";
