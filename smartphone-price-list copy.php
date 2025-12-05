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