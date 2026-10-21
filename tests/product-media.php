<?php
// Run only in a disposable local WordPress installation.
if (!in_array(parse_url(home_url(), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Use a disposable local store.');
}
$sku = 'AT-' . strtoupper(substr(md5('Arc oak tray'), 0, 6));
$product = wc_get_product(wc_get_product_id_by_sku($sku));
$legacy = get_posts(['post_type'=>'attachment', 'post_status'=>'inherit', 'meta_key'=>'_atelier_photo', 'meta_value'=>'photo-1494438639946-1ebd1d20bf85', 'fields'=>'ids', 'numberposts'=>1]);
if ($legacy) {
    $product->set_image_id((int)$legacy[0]);
    $product->save();
    update_post_meta($product->get_id(), '_atelier_photo', 'photo-1494438639946-1ebd1d20bf85');
}
$old_photo = get_post_meta($product->get_id(), '_atelier_photo', true);
$old_image = $product->get_image_id();
if ($legacy) {
    $source = get_template_directory() . '/assets/images/product-arc-tray.webp';
    rename($source, $source . '.fixture');
    try {
        delete_option('atelier_product_media_seeded');
        atelier_seed_product_media();
        if ((int)wc_get_product($product->get_id())->get_image_id() !== (int)$old_image
            || get_post_meta($product->get_id(), '_atelier_photo', true) !== $old_photo) {
            throw new Exception('Missing source changed the existing image.');
        }
        echo "PASS: missing source preserves existing product image\n";
    } finally {
        rename($source . '.fixture', $source);
    }
}
delete_option('atelier_product_media_seeded');
atelier_seed_product_media();
$product = wc_get_product($product->get_id());
$image = $product->get_image_id();
$photo = get_post_meta($product->get_id(), '_atelier_photo', true);
if ($photo !== 'product-arc-tray' || !$image || !is_file(get_attached_file($image))) throw new Exception('Migration failed.');
if ($old_photo !== $photo && $old_image && $old_image === $image) throw new Exception('Legacy image was reused.');
if ($old_image && !is_file(get_attached_file($old_image))) throw new Exception('Legacy file was removed.');
if (get_post_meta($image, '_wp_attachment_image_alt', true) !== $product->get_name()) throw new Exception('Wrong alt text.');
$count = wp_count_posts('attachment')->inherit;
delete_option('atelier_product_media_seeded');
atelier_seed_product_media();
if (wp_count_posts('attachment')->inherit !== $count) throw new Exception('Repeated migration duplicated attachments.');
echo "PASS: managed migration, old files, alt text and idempotency\n";

$uploads = wp_upload_dir();
$path = $uploads['path'] . '/atelier-custom-photo-check.webp';
copy(get_attached_file($image), $path);
$custom = wp_insert_attachment(['post_mime_type'=>'image/webp', 'post_title'=>'Fictional custom image', 'post_status'=>'inherit'], $path);
try {
    $product->set_image_id($custom);
    $product->save();
    update_post_meta($product->get_id(), '_atelier_photo', 'photo-1494438639946-1ebd1d20bf85');
    delete_option('atelier_product_media_seeded');
    atelier_seed_product_media();
    if ((int)wc_get_product($product->get_id())->get_image_id() !== (int)$custom || !is_file($path)) throw new Exception('Custom upload replaced.');
    echo "PASS: user image preserved during migration\n";
} finally {
    $product->set_image_id($image);
    $product->save();
    update_post_meta($product->get_id(), '_atelier_photo', $photo);
    wp_delete_attachment($custom, true);
}
