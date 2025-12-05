<?php
// includes/enqueue-scripts.php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueues scripts and styles for the frontend.
 * Updated to check for ALL SPL shortcodes, not just the main one.
 */
function spl_enqueue_assets() {
    global $post;

    if ( ! is_a( $post, 'WP_Post' ) ) {
        return;
    }

    // List of ALL shortcodes provided by this plugin
    $spl_shortcodes = [
        'smartphone_price_list',
        'spl_editors_picks',
        'spl_price_segments',
        'spl_brand_list',
        'spl_price_drops',
        'spl_price',
        'spl_regular_price',
        'spl_last_updated'
    ];

    // Check if ANY of these shortcodes exist in the content
    $found_shortcode = false;
    foreach ( $spl_shortcodes as $shortcode ) {
        if ( has_shortcode( $post->post_content, $shortcode ) ) {
            $found_shortcode = true;
            break; // Stop checking once we find one
        }
    }

    // Only load assets if a relevant shortcode is found
    if ( is_singular() && $found_shortcode ) {

        // 1. Tailwind CSS (CDN)
        wp_enqueue_style(
            'spl-tailwind-css',
            'https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css'
        );

        // 2. Custom Styles
        wp_enqueue_style(
            'spl-main-style',
            SPL_PLUGIN_URL . 'assets/css/style.css',
            ['spl-tailwind-css'],
            time() // Cache buster
        );

        // 3. Main JavaScript
        wp_enqueue_script(
            'spl-main-js',
            SPL_PLUGIN_URL . 'assets/js/main.js',
            [], 
            time(), 
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'spl_enqueue_assets' );

/**
 * Cloudflare Rocket Loader Compatibility
 */
function spl_add_rocket_loader_ignore_attribute( $tag, $handle ) {
    if ( 'spl-main-js' === $handle ) {
        return str_replace( ' src', ' data-cfasync="false" src', $tag );
    }
    return $tag;
}
add_filter( 'script_loader_tag', 'spl_add_rocket_loader_ignore_attribute', 10, 2 );