<?php
if (!defined('ABSPATH')) exit;

// Keep one-result searches in the catalog so visitors can refine their filters.
add_filter('woocommerce_redirect_single_search_result', '__return_false');

function atelier_filter_form() {
    if (!function_exists('is_shop') || !(is_shop() || is_product_taxonomy())) return;
    $min = isset($_GET['min_price']) && is_string($_GET['min_price']) ? wc_clean(wp_unslash($_GET['min_price'])) : '';
    $max = isset($_GET['max_price']) && is_string($_GET['max_price']) ? wc_clean(wp_unslash($_GET['max_price'])) : '';
    $color = isset($_GET['atelier_color']) ? array_map('sanitize_title', array_filter((array)wp_unslash($_GET['atelier_color']), 'is_string')) : [];
    $material = isset($_GET['atelier_material']) ? array_map('sanitize_title', array_filter((array)wp_unslash($_GET['atelier_material']), 'is_string')) : [];
    ?>
    <form class="atelier-filters" method="get" action="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
        <input type="hidden" name="post_type" value="product">
        <div class="filter-block"><h3><label for="atelier-product-search">Search products</label></h3><input id="atelier-product-search" type="search" name="s" placeholder="Name or description" value="<?php echo esc_attr(get_search_query(false)); ?>"></div>
        <div class="filter-block"><h3>Price range</h3><div class="price-inputs"><label><span>From</span><input type="number" min="0" name="min_price" placeholder="0" value="<?php echo esc_attr($min); ?>"></label><label><span>To</span><input type="number" min="0" name="max_price" placeholder="500" value="<?php echo esc_attr($max); ?>"></label></div></div>
        <?php foreach (['color' => ['Color', 'pa_color', $color], 'material' => ['Material', 'pa_material', $material]] as $key => [$label, $taxonomy, $selected]): $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]); if (is_wp_error($terms) || !$terms) continue; ?>
            <div class="filter-block"><h3><?php echo esc_html($label); ?></h3><?php foreach ($terms as $term): ?><label class="check-option"><input type="checkbox" name="atelier_<?php echo esc_attr($key); ?>[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selected, true)); ?>><span><?php echo esc_html($term->name); ?></span></label><?php endforeach; ?></div>
        <?php endforeach; ?>
        <button class="button button-dark" type="submit">Apply filters</button>
        <?php if ($min !== '' || $max !== '' || $color || $material): ?><a class="filter-reset" href="<?php echo esc_url(remove_query_arg(['min_price', 'max_price', 'atelier_color', 'atelier_material'])); ?>">Clear filters</a><?php endif; ?>
        <?php if (is_product_taxonomy()): ?><input type="hidden" name="product_cat" value="<?php echo esc_attr(get_queried_object()->slug); ?>"><?php endif; ?>
    </form><?php
}

add_action('woocommerce_product_query', function ($query) {
    $filters = [
        'atelier_color' => 'pa_color',
        'atelier_material' => 'pa_material',
    ];
    $tax_query = (array)$query->get('tax_query');

    foreach ($filters as $parameter => $taxonomy) {
        if (!isset($_GET[$parameter])) continue;
        $terms = array_values(array_unique(array_filter(array_map(
            'sanitize_title',
            array_filter((array)wp_unslash($_GET[$parameter]), 'is_string')
        ))));
        if (!$terms || !taxonomy_exists($taxonomy)) continue;

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field' => 'slug',
            'terms' => $terms,
            'operator' => 'IN',
        ];
    }

    if (count($tax_query) > 0) {
        $tax_query['relation'] = $tax_query['relation'] ?? 'AND';
        $query->set('tax_query', $tax_query);
    }
}, 20);

function atelier_filter_sidebar() {
    echo '<button class="filter-toggle" type="button" aria-controls="atelier-shop-filters" aria-expanded="false">Filters <span aria-hidden="true">＋</span></button><aside id="atelier-shop-filters" class="shop-sidebar">';
    echo '<div class="sidebar-title"><strong>Refine</strong><button class="filter-close" type="button" aria-label="Close filters">×</button></div>';
    atelier_filter_form(); echo '</aside><div class="filter-scrim"></div>';
}
