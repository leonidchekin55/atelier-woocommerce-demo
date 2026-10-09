<?php
if (!defined('ABSPATH')) exit;

function atelier_filter_form() {
    if (!function_exists('is_shop') || !(is_shop() || is_product_taxonomy())) return;
    $min = isset($_GET['min_price']) ? wc_clean(wp_unslash($_GET['min_price'])) : '';
    $max = isset($_GET['max_price']) ? wc_clean(wp_unslash($_GET['max_price'])) : '';
    $color = isset($_GET['filter_color']) ? wc_clean(wp_unslash($_GET['filter_color'])) : '';
    $material = isset($_GET['filter_material']) ? wc_clean(wp_unslash($_GET['filter_material'])) : '';
    ?>
    <form class="atelier-filters" method="get" action="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
        <div class="filter-block"><h3>Price range</h3><div class="price-inputs"><label><span>From</span><input type="number" min="0" name="min_price" placeholder="0" value="<?php echo esc_attr($min); ?>"></label><label><span>To</span><input type="number" min="0" name="max_price" placeholder="500" value="<?php echo esc_attr($max); ?>"></label></div></div>
        <?php foreach (['color' => ['Color', 'pa_color'], 'material' => ['Material', 'pa_material']] as $key => [$label, $taxonomy]): $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]); if (is_wp_error($terms) || !$terms) continue; ?>
            <div class="filter-block"><h3><?php echo esc_html($label); ?></h3><?php foreach ($terms as $term): ?><label class="check-option"><input type="checkbox" name="filter_<?php echo esc_attr($key); ?>[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, (array)$color, true) && $key === 'color'); checked(in_array($term->slug, (array)$material, true) && $key === 'material'); ?>><span><?php echo esc_html($term->name); ?></span></label><?php endforeach; ?></div>
        <?php endforeach; ?>
        <button class="button button-dark" type="submit">Apply filters</button>
        <?php if (is_product_taxonomy()): ?><input type="hidden" name="product_cat" value="<?php echo esc_attr(get_queried_object()->slug); ?>"><?php endif; ?>
    </form><?php
}

add_action('woocommerce_before_shop_loop', function () {
    if (!is_shop() && !is_product_taxonomy()) return;
    echo '<button class="filter-toggle" type="button" aria-expanded="false">Filters <span>＋</span></button><aside class="shop-sidebar">';
    echo '<div class="sidebar-title"><strong>Refine</strong><button class="filter-close" type="button" aria-label="Close filters">×</button></div>';
    atelier_filter_form(); echo '</aside><div class="filter-scrim"></div>';
}, 5);
