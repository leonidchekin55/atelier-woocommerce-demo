<?php
// Called by WP-CLI only inside the disposable integration-test containers.
if (!defined('WP_CLI') || !WP_CLI) exit(1);
$mode = $args[0] ?? '';
if ($mode === 'seed') {
    $order = wc_create_order();
    $order->add_product(wc_get_products(['limit' => 1])[0], 2);
    $order->set_billing_email('fictional-recovery@example.invalid');
    $order->set_payment_method('cod');
    $order->calculate_totals();
    $order->update_status('processing');
    $message = wp_insert_post(['post_type' => 'atelier_message', 'post_status' => 'private', 'post_title' => 'Fictional recovery message', 'post_content' => 'Offline integration fixture']);
    $subscriber = wp_insert_post(['post_type' => 'atelier_subscriber', 'post_status' => 'private', 'post_title' => 'fictional-recovery@example.invalid']);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $upload = wp_upload_dir();
    $path = $upload['path'] . '/new-recovery-upload.webp';
    copy(get_template_directory() . '/assets/images/photo-1616486338812-3dadae4b4ace.webp', $path);
    $attachment = wp_insert_attachment(['post_mime_type' => 'image/webp', 'post_title' => 'New recovery upload', 'post_status' => 'inherit'], $path);
    wp_update_attachment_metadata($attachment, wp_generate_attachment_metadata($attachment, $path));
    update_option('atelier_recovery_fixture', 'recovery-roundtrip');
    file_put_contents('/fixture/records.json', json_encode(['order' => $order->get_id(), 'total' => $order->get_total(), 'message' => $message, 'subscriber' => $subscriber, 'attachment' => $attachment, 'hash' => hash_file('sha256', $path)]));
    echo "Fictional order, message, subscriber and new upload created.\n";
} elseif ($mode === 'unchanged') {
    if (get_option('atelier_recovery_fixture') === 'recovery-roundtrip') throw new RuntimeException('Failed restore unexpectedly imported the database.');
    echo "Failed restore left the database unchanged.\n";
} elseif ($mode === 'baseline') {
    update_option('atelier_recovery_fixture', 'before-invalid-import');
} elseif ($mode === 'baseline-check') {
    if (get_option('atelier_recovery_fixture') !== 'before-invalid-import') throw new RuntimeException('Partial SQL import was not rolled back.');
    echo "Partial SQL import was rolled back.\n";
} elseif ($mode === 'verify') {
    $expected = json_decode(file_get_contents('/fixture/records.json'), true);
    $order = wc_get_order($expected['order']);
    if (!$order || $order->get_status() !== 'processing' || $order->get_total() !== $expected['total'] || count($order->get_items()) !== 1) throw new RuntimeException('Order did not recover with matching ID, status, items and total.');
    foreach (['message' => 'atelier_message', 'subscriber' => 'atelier_subscriber'] as $key => $type) {
        $post = get_post($expected[$key]);
        if (!$post || $post->post_type !== $type || $post->post_status !== 'private') throw new RuntimeException('Private record did not recover.');
    }
    $path = get_attached_file($expected['attachment']);
    if (!is_file($path) || hash_file('sha256', $path) !== $expected['hash']) throw new RuntimeException('New upload did not recover byte for byte.');
    foreach (wp_get_attachment_metadata($expected['attachment'])['sizes'] ?? [] as $size) {
        if (!is_file(dirname($path) . '/' . $size['file'])) throw new RuntimeException('Generated image size was not restored.');
    }
    echo "PASS: order ID/status/items/total, private message/subscriber, new upload and image sizes restored.\n";
} else throw new RuntimeException('Unknown fixture operation.');
