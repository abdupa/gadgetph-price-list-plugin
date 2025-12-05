<?php
// includes/enqueue-scripts.php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueues scripts and styles for the frontend.
 */
function spl_enqueue_assets() {
    // Only load these scripts if the shortcode is on the page.
    if ( is_singular() && has_shortcode( get_the_content(), 'smartphone_price_list' ) ) {

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
            time() // Changed to time() to force CSS refresh
        );

        // 3. Main JavaScript
        // We use time() here to force the browser to download the new main.js 
        // immediately, fixing the "Search not working" issue.
        wp_enqueue_script(
            'spl-main-js',
            SPL_PLUGIN_URL . 'assets/js/main.js',
            [], 
            time(), // <--- This is the key fix (Cache Buster)
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'spl_enqueue_assets' );

/**
 * Add data-cfasync="false" to our specific script tag.
 * This tells Cloudflare Rocket Loader to not interfere with our main.js file.
 */
function spl_add_rocket_loader_ignore_attribute( $tag, $handle ) {
    if ( 'spl-main-js' === $handle ) {
        return str_replace( ' src', ' data-cfasync="false" src', $tag );
    }
    return $tag;
}
add_filter( 'script_loader_tag', 'spl_add_rocket_loader_ignore_attribute', 10, 2 );