<?php
if (!defined('ABSPATH')) exit;

// WooCommerce treats a submitted empty upper price bound as zero.
// Empty form fields must behave like omitted bounds; an explicit zero is valid.
function atelier_normalize_price_filters() {
    foreach (['min_price', 'max_price'] as $parameter) {
        if (isset($_GET[$parameter]) && is_string($_GET[$parameter]) && trim($_GET[$parameter]) === '') {
            unset($_GET[$parameter]);
        }
    }
}
add_action('init', 'atelier_normalize_price_filters', 1);

// Keep one-result searches in the catalog so visitors can refine their filters.
add_filter('woocommerce_redirect_single_search_result', '__return_false');

add_action('woocommerce_no_products_found', function () {
    echo '<p class="empty-catalog-action"><a class="button button-dark" href="' . esc_url(wc_get_page_permalink('shop')) . '">Browse all pieces</a></p>';
}, 20);

function atelier_selected_filter_terms(string $parameter): array {
    $values = isset($_GET[$parameter]) ? (array) wp_unslash($_GET[$parameter]) : [];
    return array_values(array_unique(array_filter(array_map('sanitize_title', array_filter($values, 'is_string')))));
}

// Show the applied choices even when the mobile filter panel is closed.
function atelier_active_filters() {
    $russian = function_exists('atelier_language') && atelier_language() === 'ru';
    $url = remove_query_arg(['paged', 'product-page'], get_pagenum_link(1, false));
    if (function_exists('atelier_language')) $url = add_query_arg('atelier_lang', atelier_language(), $url);
    $chips = [];
    $search = get_search_query(false);
    if ($search !== '') $chips[] = [($russian ? 'Поиск: ' : 'Search: ') . $search, remove_query_arg('s', $url)];
    foreach (['min_price' => ['From', 'От'], 'max_price' => ['To', 'До']] as $parameter => $labels) {
        $value = $_GET[$parameter] ?? null;
        if (!is_string($value) || !is_numeric($value) || (float) $value < 0) continue;
        $price = html_entity_decode(wp_strip_all_tags(wc_price((float) $value)), ENT_QUOTES, 'UTF-8');
        $chips[] = [$labels[$russian ? 1 : 0] . ': ' . $price, remove_query_arg($parameter, $url)];
    }
    foreach (['atelier_color' => ['pa_color', 'Color', 'Цвет'], 'atelier_material' => ['pa_material', 'Material', 'Материал']] as $parameter => $details) {
        $selected = atelier_selected_filter_terms($parameter);
        if (!$selected) continue;
        $terms = get_terms(['taxonomy' => $details[0], 'slug' => $selected, 'hide_empty' => false]);
        if (is_wp_error($terms)) continue;
        $by_slug = [];
        foreach ($terms as $term) $by_slug[$term->slug] = $term;
        foreach ($selected as $slug) {
            $term = $by_slug[$slug] ?? null;
            if (!$term) continue;
            $remaining = array_values(array_diff($selected, [$slug]));
            $link = remove_query_arg($parameter, $url);
            if ($remaining) $link = add_query_arg($parameter, $remaining, $link);
            $chips[] = [$details[$russian ? 2 : 1] . ': ' . $term->name, $link];
        }
    }
    if (!$chips) return;
    echo '<nav class="active-filters" aria-label="' . esc_attr($russian ? 'Выбранные фильтры' : 'Active filters') . '"><ul>';
    foreach ($chips as [$label, $link]) {
        echo '<li><a class="active-filter" href="' . esc_url($link) . '" aria-label="' . esc_attr(($russian ? 'Снять фильтр: ' : 'Remove filter: ') . $label) . '">' . esc_html($label) . ' <span aria-hidden="true">×</span></a></li>';
    }
    $reset = remove_query_arg(['s', 'min_price', 'max_price', 'atelier_color', 'atelier_material'], $url);
    echo '</ul><a class="active-filters-reset" href="' . esc_url($reset) . '">' . esc_html($russian ? 'Сбросить фильтры' : 'Clear filters') . '</a></nav>';
}
add_action('woocommerce_before_shop_loop', 'atelier_active_filters', 5);
add_action('woocommerce_no_products_found', 'atelier_active_filters', 5);

function atelier_filter_form() {
    if (!function_exists('is_shop') || !(is_shop() || is_product_taxonomy())) return;
    $min = isset($_GET['min_price']) && is_string($_GET['min_price']) ? wc_clean(wp_unslash($_GET['min_price'])) : '';
    $max = isset($_GET['max_price']) && is_string($_GET['max_price']) ? wc_clean(wp_unslash($_GET['max_price'])) : '';
    $color = atelier_selected_filter_terms('atelier_color');
    $material = atelier_selected_filter_terms('atelier_material');
    ?>
    <form class="atelier-filters" method="get" action="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
        <input type="hidden" name="post_type" value="product">
        <?php if (function_exists('atelier_language')): ?><input type="hidden" name="atelier_lang" value="<?php echo esc_attr(atelier_language()); ?>"><?php endif; ?>
        <?php if (isset($_GET['orderby']) && is_string($_GET['orderby'])): ?><input type="hidden" name="orderby" value="<?php echo esc_attr(sanitize_key(wp_unslash($_GET['orderby']))); ?>"><?php endif; ?>
        <div class="filter-block"><h3><label for="atelier-product-search">Search products</label></h3><input id="atelier-product-search" type="search" name="s" placeholder="Name or description" value="<?php echo esc_attr(get_search_query(false)); ?>"></div>
        <div class="filter-block"><h3>Price range</h3><div class="price-inputs"><label><span>From</span><input type="number" min="0" step="any" name="min_price" placeholder="0" value="<?php echo esc_attr($min); ?>"></label><label><span>To</span><input type="number" min="0" step="any" name="max_price" placeholder="500" value="<?php echo esc_attr($max); ?>"></label></div></div>
        <?php foreach (['color' => ['Color', 'pa_color', $color], 'material' => ['Material', 'pa_material', $material]] as $key => [$label, $taxonomy, $selected]): $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]); if (is_wp_error($terms) || !$terms) continue; ?>
            <div class="filter-block"><h3><?php echo esc_html($label); ?></h3><?php foreach ($terms as $term): ?><label class="check-option"><input type="checkbox" name="atelier_<?php echo esc_attr($key); ?>[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selected, true)); ?>><span><?php echo esc_html($term->name); ?></span></label><?php endforeach; ?></div>
        <?php endforeach; ?>
        <button class="button button-dark" type="submit">Apply filters</button>
        <?php if ($min !== '' || $max !== '' || $color || $material || get_search_query(false) !== ''): ?><a class="filter-reset" href="<?php echo esc_url(remove_query_arg(['min_price', 'max_price', 'atelier_color', 'atelier_material', 's', 'paged', 'product-page'])); ?>">Clear filters</a><?php endif; ?>
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
        $terms = atelier_selected_filter_terms($parameter);
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
