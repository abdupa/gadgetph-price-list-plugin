<?php
/**
 * Plugin Name:       Smartphone Price List
 * Description:       Displays a dynamic and filterable price list for smartphones using WooCommerce products.
 * Version:           5.0.0 (Price Change Tracker)
 * Author:            ABDupa
 * Author URI:        https://gadgetph.com/
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SPL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SPL_PLUGIN_DIR . 'includes/shortcode.php';
require_once SPL_PLUGIN_DIR . 'includes/enqueue-scripts.php';
require_once SPL_PLUGIN_DIR . 'includes/data-mapper.php';

// --- BATCH PROCESSING ENGINE ---

/**
 * Adds the admin page under the "Tools" menu.
 */
function spl_add_admin_menu_page() {
    add_management_page('Price List Cache', 'Price List Cache', 'manage_options', 'spl-cache-manager', 'spl_render_admin_page');
}
add_action('admin_menu', 'spl_add_admin_menu_page');

/**
 * Renders the UI for the batch processing admin page.
 */
function spl_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>Smartphone Price List Cache Manager</h1>
        <p>This tool rebuilds the product data cache in small, safe batches to prevent server timeouts.</p>
        <p>Your workflow: update prices via your Google Sheet parser, then come here and click "Start Rebuild".</p>
        
        <div id="spl-rebuild-controls">
            <button id="spl-start-rebuild" class="button button-primary">Start Rebuild Now</button>
        </div>

        <div id="spl-progress-wrapper" style="display:none; margin-top: 20px;">
            <progress id="spl-progress-bar" value="0" max="100" style="width: 100%; height: 25px;"></progress>
            <p id="spl-status-text" style="text-align: center; font-style: italic; margin-top: 10px;"></p>
        </div>

        <div id="spl-success-message" style="display:none; margin-top: 20px;" class="notice notice-success is-dismissible">
            <p><strong>Success!</strong> The cache has been fully rebuilt with the latest data.</p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startButton = document.getElementById('spl-start-rebuild');
            const progressWrapper = document.getElementById('spl-progress-wrapper');
            const progressBar = document.getElementById('spl-progress-bar');
            const statusText = document.getElementById('spl-status-text');
            const successMessage = document.getElementById('spl-success-message');

            startButton.addEventListener('click', function() {
                startButton.disabled = true;
                progressWrapper.style.display = 'block';
                successMessage.style.display = 'none';
                statusText.textContent = 'Starting process...';
                runBatch(0); // Start with the first batch (offset 0)
            });

            function runBatch(offset) {
                const data = new URLSearchParams();
                data.append('action', 'spl_run_rebuild_batch');
                data.append('offset', offset);
                data.append('_ajax_nonce', '<?php echo wp_create_nonce("spl_rebuild_nonce"); ?>');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        progressBar.value = result.data.percentage;
                        statusText.textContent = result.data.message;

                        if (result.data.status === 'continue') {
                            runBatch(result.data.offset); // Run the next batch
                        } else {
                            // Done!
                            startButton.disabled = false;
                            successMessage.style.display = 'block';
                        }
                    } else {
                        // Handle errors
                        statusText.innerHTML = '<strong style="color:red;">Error:</strong> ' + result.data.message;
                        startButton.disabled = false;
                    }
                })
                .catch(error => {
                    statusText.textContent = 'A network error occurred. Please try again.';
                    startButton.disabled = false;
                });
            }
        });
    </script>
    <?php
}

/**
 * The AJAX handler that processes a single batch. This is called by the JavaScript.
 */
function spl_ajax_run_rebuild_batch() {
    check_ajax_referer('spl_rebuild_nonce');

    $batch_size = 100; // Process 100 products per request.
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $transient_key = 'spl_all_phones_data_v5';

    // On the first batch, clear the old data and count total products.
    if ($offset === 0) {
        $count_query = new WP_Query([
            'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1,
            'tax_query' => [ ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'mobile-phones'] ],
        ]);
        $total_products = $count_query->found_posts;
        update_option('spl_total_products_count', $total_products); 
        set_transient($transient_key, [], 12 * HOUR_IN_SECONDS); 
    } else {
        $total_products = get_option('spl_total_products_count', 1);
    }
    
    // Get the products for the current batch.
    $args = [
        'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => $batch_size, 'offset' => $offset,
        'tax_query' => [ ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'mobile-phones'] ],
        'meta_query' => [ 'relation' => 'AND', ['key' => '_price', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'], ['key' => '_price', 'value' => '', 'compare' => '!='] ]
    ];
    $products_query = new WP_Query($args);

    $batch_products = [];
    if ( $products_query->have_posts() ) {
        while ( $products_query->have_posts() ) {
            $products_query->the_post();

            // Filter out "watch" products
            $product_id = get_the_ID();
            $product_title = get_the_title($product_id);
            if (stripos($product_title, 'watch') !== false) {
                continue; 
            }

            $mapped_product = spl_map_wc_product_to_pricelist_format( get_the_ID() );
            if ($mapped_product) { $batch_products[] = $mapped_product; }
        }
    }
    
    // Add this batch to the main cache.
    $existing_data = get_transient($transient_key) ?: [];
    $new_data = array_merge($existing_data, $batch_products);
    set_transient($transient_key, $new_data, 12 * HOUR_IN_SECONDS);

    $new_offset = $offset + $batch_size;

    if ($new_offset >= $total_products) {
        wp_send_json_success([
            'status' => 'done',
            'message' => "Complete! {$total_products} products processed.",
            'percentage' => 100,
            'offset' => $new_offset
        ]);
    } else {
        wp_send_json_success([
            'status' => 'continue',
            'message' => "Processing... {$new_offset} of {$total_products} products complete.",
            'percentage' => round(($new_offset / $total_products) * 100),
            'offset' => $new_offset
        ]);
    }
}
add_action('wp_ajax_spl_run_rebuild_batch', 'spl_ajax_run_rebuild_batch');


/**
 * (Safety Net) Clears the cache if a product is saved manually.
 */
function spl_clear_product_cache_on_save() {
    delete_transient('spl_all_phones_data_v5');
}
add_action('save_post_product', 'spl_clear_product_cache_on_save');


// --- NEW FEATURE: PRICE CHANGE TRACKER ---

/**
 * Detects if the '_price' meta key is being updated.
 * If yes, it saves the current timestamp to a global option.
 */
function spl_track_price_updates( $meta_id, $object_id, $meta_key, $_meta_value ) {
    // Only run if the meta key is '_price'
    if ( '_price' === $meta_key ) {
        // Save the current time (MySQL format) to a global option.
        // This is extremely fast to read later.
        update_option( 'spl_last_price_change_date', current_time( 'mysql' ) );
    }
}
// Hook into update (editing existing price) and add (new price) events
add_action( 'updated_post_meta', 'spl_track_price_updates', 10, 4 );
add_action( 'added_post_meta', 'spl_track_price_updates', 10, 4 );