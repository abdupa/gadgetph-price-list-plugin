<?php
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

        // ** CHANGED: Use a pre-compiled Tailwind CSS file instead of the script. **
        wp_enqueue_style(
            'spl-tailwind-css',
            'https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css' // This is a static CSS file
        );

        // Enqueue our custom stylesheet.
        wp_enqueue_style(
            'spl-main-style',
            SPL_PLUGIN_URL . 'assets/css/style.css',
            ['spl-tailwind-css'], // Dependency: Load this AFTER Tailwind.
            '1.0.1'
        );

        // Enqueue our main JavaScript file.
        wp_enqueue_script(
            'spl-main-js',
            SPL_PLUGIN_URL . 'assets/js/main.js',
            [],
            '1.0.2',
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'spl_enqueue_assets' );