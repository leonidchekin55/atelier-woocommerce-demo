<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#content">Skip to content</a>
<div class="announcement">Thoughtful objects for a slower home <span>— Free delivery on orders of $150 or more</span></div>
<header class="site-header"><div class="header-inner">
    <button class="menu-toggle" type="button" aria-label="Open menu" aria-controls="primary-navigation" aria-expanded="false"><i></i><i></i></button>
    <a class="wordmark" href="<?php echo esc_url(home_url('/')); ?>">ATELIER<span> &nbsp; / &nbsp; HOME</span></a>
    <nav id="primary-navigation" class="primary-nav"><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Shop</a><a href="<?php echo esc_url(home_url('/about/')); ?>">Our story</a><a href="<?php echo esc_url(home_url('/journal/')); ?>">Journal</a></nav>
    <div class="header-actions"><a class="account-link" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">Account</a><a class="cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>">Bag <span class="cart-count"><?php echo function_exists('WC') && WC()->cart ? esc_html(WC()->cart->get_cart_contents_count()) : '0'; ?></span></a></div>
</div></header>
<main id="content" class="site-content" tabindex="-1">
