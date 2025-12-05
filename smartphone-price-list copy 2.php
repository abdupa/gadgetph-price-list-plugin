<?php
/**
 * Plugin Name:       Smartphone Price List
 * Description:       Displays a dynamic and filterable price list for smartphones using WooCommerce products.
 * Version:           1.0.0
 * Author:            GadgetPH
 * Author URI:        https://gadgetph.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       spl
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define a constant for the plugin directory path.
 * This makes including files cleaner and more reliable.
 */
define( 'SPL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the plugin's modules.
 * These files contain the core logic for the shortcode, data handling, and script enqueuing.
 */
require_once SPL_PLUGIN_DIR . 'includes/shortcode.php';
require_once SPL_PLUGIN_DIR . 'includes/enqueue-scripts.php';
require_once SPL_PLUGIN_DIR . 'includes/data-mapper.php';

/**
 * Automatically clears our plugin's data cache (transient)
 * whenever a product is saved or its terms (like categories or tags) are changed.
 * This ensures the price list is always up-to-date after an edit.
 */
function spl_clear_product_cache() {
    // This is the same cache key we use in our shortcode file.
    $transient_key = 'spl_all_phones_data_v3';
    delete_transient($transient_key);
}

// Hook into the action that fires when a product post is saved.
add_action('save_post_product', 'spl_clear_product_cache');

// Hook into the action that fires when terms (like tags or categories) are set on a post.
// This is crucial for catching when you add/remove the 'editor-pick' tag.
add_action('set_object_terms', 'spl_clear_product_cache', 10, 4);