<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!class_exists('WooCommerce') || (get_option('atelier_catalog_seeded') && get_option('atelier_catalog_attributes_seeded'))) return;
    $lock_key = 'atelier_catalog_seed_lock';
    if (!add_option($lock_key, time(), '', false)) {
        $lock_time = (int)get_option($lock_key, 0);
        if (!$lock_time || time() - $lock_time <= 300) return;
        delete_option($lock_key);
        if (!add_option($lock_key, time(), '', false)) return;
    }

    try {
    $attributes = ['color' => ['Color', ['Sand', 'Ivory', 'Olive', 'Terracotta']], 'material' => ['Material', ['Stoneware', 'Linen', 'Oak', 'Glass']]];
    foreach ($attributes as $slug => [$label, $terms]) {
        if (!taxonomy_exists('pa_' . $slug)) {
            wc_create_attribute(['name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
        }
    }
    delete_transient('wc_attribute_taxonomies');
    foreach ($attributes as $slug => [$label, $terms]) {
        $taxonomy = 'pa_' . $slug;
        if (!taxonomy_exists($taxonomy)) register_taxonomy($taxonomy, ['product'], ['hierarchical' => false, 'label' => $label, 'query_var' => true, 'rewrite' => false]);
        foreach ($terms as $term) if (!term_exists($term, $taxonomy)) wp_insert_term($term, $taxonomy, ['slug' => sanitize_title($term)]);
    }
    $categories = [];
    foreach (['Ceramics', 'Textiles', 'Objects'] as $name) {
        $found = term_exists($name, 'product_cat');
        $categories[$name] = $found ? (int)(is_array($found) ? $found['term_id'] : $found) : (int)wp_insert_term($name, 'product_cat')['term_id'];
    }
    $products = [
        ['Sunday stoneware bowl', 'A softly rounded everyday bowl, thrown and glazed by hand. The natural variation in each surface makes every piece its own.', 38, 'Ceramics', 'Stoneware', 'Sand', 'photo-1578749556568-bc2c40e68b61'],
        ['Ripple serving platter', 'A generous platter with a gently waved edge. Made for shared lunches and long evenings around the table.', 86, 'Ceramics', 'Stoneware', 'Ivory', 'photo-1493106641515-6b5631de4bb9'],
        ['Everyday espresso cup', 'A small, balanced cup with a thumb-friendly handle and satin glaze. Holds approximately 90 ml.', 24, 'Ceramics', 'Stoneware', 'Terracotta', 'photo-1572119865084-43c285814d63'],
        ['Gathering pitcher', 'A sculptural pitcher that pours cleanly and looks at home on the table between uses.', 72, 'Ceramics', 'Stoneware', 'Olive', 'photo-1578749556568-bc2c40e68b61'],
        ['Washed linen napkin set', 'Set of two relaxed linen napkins, pre-washed for a soft hand. Woven from European flax.', 42, 'Textiles', 'Linen', 'Ivory', 'photo-1600210492486-724fe5c67fb0'],
        ['Linen table runner', 'A long, softly draping runner with a fine hem. Naturally textured and easy to care for.', 68, 'Textiles', 'Linen', 'Sand', 'photo-1616486029423-aaa4789e8c9a'],
        ['Quiet hour cushion cover', 'A tactile cover in heavyweight washed linen, finished with a discreet hidden fastening.', 74, 'Textiles', 'Linen', 'Olive', 'photo-1616486338812-3dadae4b4ace'],
        ['Dawn throw', 'A light layer for cool mornings, woven from a breathable natural blend with a simple selvedge edge.', 118, 'Textiles', 'Linen', 'Terracotta', 'photo-1494438639946-1ebd1d20bf85'],
        ['Low oak candleholder', 'Turned from solid oak with a considered, low profile. Designed for standard taper candles.', 52, 'Objects', 'Oak', 'Sand', 'photo-1493106641515-6b5631de4bb9'],
        ['Handblown bud vase', 'A small handblown glass vessel with a softly weighted base. Each one carries subtle bubbles and variation.', 46, 'Objects', 'Glass', 'Olive', 'photo-1578500494198-246f612d3b3d'],
        ['Arc oak tray', 'A useful catch-all with a shallow carved edge. Finished by hand with a food-safe oil.', 94, 'Objects', 'Oak', 'Sand', 'photo-1494438639946-1ebd1d20bf85'],
        ['Evening glass pair', 'Two light, durable tumblers made for water, wine, or a small something after dinner.', 58, 'Objects', 'Glass', 'Ivory', 'photo-1572119865084-43c285814d63'],
    ];
    foreach ($products as [$name, $description, $price, $category, $material, $color, $photo]) {
        $sku = 'AT-' . strtoupper(substr(md5($name), 0, 6));
        $id = wc_get_product_id_by_sku($sku);
        if (!$id) {
            $product = new WC_Product_Simple();
            $product->set_name($name); $product->set_status('publish'); $product->set_catalog_visibility('visible');
            $product->set_description($description); $product->set_short_description('Thoughtfully made, ready for everyday.');
            $product->set_regular_price((string)$price); $product->set_sku($sku);
            $product->set_manage_stock(false); $product->set_stock_status('instock');
            $id = $product->save();
        }
        if (!$id) continue;

        wp_set_object_terms($id, [$categories[$category]], 'product_cat');
        wp_set_object_terms($id, [sanitize_title($material)], 'pa_material');
        wp_set_object_terms($id, [sanitize_title($color)], 'pa_color');
        update_post_meta($id, '_atelier_photo', $photo);

        $product = wc_get_product($id);
        $product_attributes = [];
        foreach ([
            ['pa_material', $material],
            ['pa_color', $color],
        ] as $position => [$taxonomy, $term_name]) {
            $term = get_term_by('slug', sanitize_title($term_name), $taxonomy);
            $attribute_id = wc_attribute_taxonomy_id_by_name($taxonomy);
            if (!$term || !$attribute_id) continue;

            $attribute = new WC_Product_Attribute();
            $attribute->set_id($attribute_id);
            $attribute->set_name($taxonomy);
            $attribute->set_options([(int)$term->term_id]);
            $attribute->set_position($position);
            $attribute->set_visible(true);
            $attribute->set_variation(false);
            $product_attributes[] = $attribute;
        }
        if ($product && count($product_attributes) === 2) {
            $product->set_attributes($product_attributes);
            $product->save();
        }
    }
    // Mark the catalog complete only when every expected SKU exists. This also
    // repairs a partially seeded catalog after interrupted deploys or imports.
    $expected_skus = array_map(static function ($product) {
        return 'AT-' . strtoupper(substr(md5($product[0]), 0, 6));
    }, $products);
    $catalog_complete = true;
    $attributes_complete = true;
    foreach ($expected_skus as $expected_sku) {
        $product_id = wc_get_product_id_by_sku($expected_sku);
        if (!$product_id) {
            $catalog_complete = false;
            $attributes_complete = false;
            continue;
        }
        $product = wc_get_product($product_id);
        if (!$product || !isset($product->get_attributes()['pa_color'], $product->get_attributes()['pa_material'])) $attributes_complete = false;
    }
    update_option('atelier_catalog_seeded', $catalog_complete ? 1 : 0);
    update_option('atelier_catalog_attributes_seeded', $attributes_complete ? 1 : 0);
    } finally {
        delete_option($lock_key);
    }
}, 99);

add_filter('woocommerce_product_get_image', function ($html, $product, $size, $attr, $placeholder, $image) {
    $photo = get_post_meta($product->get_id(), '_atelier_photo', true);
    if (!$photo || $product->get_image_id()) return $html;
    $url = get_template_directory_uri() . '/assets/images/' . rawurlencode($photo) . '.webp';
    $alt = esc_attr($product->get_name());
    return '<img src="' . esc_url($url) . '" alt="' . $alt . '" loading="lazy" decoding="async">';
}, 10, 6);

add_filter('woocommerce_get_catalog_ordering_args', function ($args) {
    if (isset($_GET['orderby']) && $_GET['orderby'] === 'menu_order') $args['orderby'] = 'menu_order title';
    return $args;
});
