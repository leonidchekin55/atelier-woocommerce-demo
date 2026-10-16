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
    $product_images = [];
    foreach (wc_get_products(['limit'=>-1, 'status'=>'publish']) as $product) {
        if (!str_starts_with(get_post_meta($product->get_id(), '_atelier_photo', true), 'product-')) continue;
        $image = $product->get_image_id();
        $file = get_attached_file($image);
        if (!$image || !is_file($file)) throw new RuntimeException('Bundled product image is missing.');
        $product_images[$product->get_id()] = ['image'=>$image, 'hash'=>hash_file('sha256', $file)];
    }
    if (count($product_images) !== 12) throw new RuntimeException('Expected twelve managed product images.');
    file_put_contents('/fixture/records.json', json_encode(['order' => $order->get_id(), 'total' => $order->get_total(), 'message' => $message, 'subscriber' => $subscriber, 'attachment' => $attachment, 'hash' => hash_file('sha256', $path), 'product_images'=>$product_images]));
    echo "Fictional order, message, subscriber and new upload created.\n";
} elseif ($mode === 'unchanged') {
    if (get_option('atelier_recovery_fixture') === 'recovery-roundtrip') throw new RuntimeException('Failed restore unexpectedly imported the database.');
    echo "Failed restore left the database unchanged.\n";
} elseif ($mode === 'baseline') {
    update_option('atelier_recovery_fixture', 'before-invalid-import');
} elseif ($mode === 'baseline-check') {
    if (get_option('atelier_recovery_fixture') !== 'before-invalid-import') throw new RuntimeException('Partial SQL import was not rolled back.');
    echo "Partial SQL import was rolled back.\n";
} elseif ($mode === 'brevo-provider') {
    $sender = 'atelier-sender@example.invalid';
    $connectionKey = md5($sender);
    $settings = fluentMailGetSettings([], false);
    $provider = $settings['connections'][$connectionKey]['provider_settings']['provider'] ?? null;
    if (!is_string($provider) || $provider === '') throw new RuntimeException('Brevo connection did not save its FluentSMTP provider.');
    $factory = new \FluentMail\App\Services\Mailer\Providers\Factory(
        fluentMail(), new \FluentMail\App\Models\Settings()
    );
    $handler = $factory->make($provider);
    if ($provider !== 'sendinblue') throw new RuntimeException('Brevo connection did not save the FluentSMTP provider key.');
    if (!$handler instanceof \FluentMail\App\Services\Mailer\BaseHandler) throw new RuntimeException('FluentSMTP did not resolve the Brevo provider.');
    $newOrderSettings = get_option('woocommerce_new_order_settings', []);
    if (($newOrderSettings['enabled'] ?? '') !== 'yes') throw new RuntimeException('WooCommerce new-order owner notifications are not enabled.');
    if (($newOrderSettings['recipient'] ?? '') !== 'atelier-owner@example.invalid') throw new RuntimeException('WooCommerce new-order owner recipient was not configured.');
    echo "PASS: FluentSMTP provider and WooCommerce owner notification settings are valid.\n";
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
    foreach ($expected['product_images'] as $id => $record) {
        $product = wc_get_product($id);
        $file = get_attached_file($record['image']);
        if (!$product || (int)$product->get_image_id() !== (int)$record['image'] || !is_file($file)
            || hash_file('sha256', $file) !== $record['hash']) throw new RuntimeException('Product image did not recover.');
        foreach (wp_get_attachment_metadata($record['image'])['sizes'] ?? [] as $size) {
            if (!is_file(dirname($file) . '/' . $size['file'])) throw new RuntimeException('Product thumbnail did not recover.');
        }
    }
    echo "PASS: twelve product images and thumbnails restored with matching IDs and hashes.\n";
    echo "PASS: order ID/status/items/total, private message/subscriber, new upload and image sizes restored.\n";
} else throw new RuntimeException('Unknown fixture operation.');
