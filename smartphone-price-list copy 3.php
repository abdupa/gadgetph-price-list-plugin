<?php
/**
 * Plugin Name:       Smartphone Price List
 * Description:       Displays a dynamic and filterable price list for smartphones using WooCommerce products.
 * Version:           4.0.0 (Live Optimized - Batch Processing)
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

    $batch_size = 100; // Process 200 products per request.
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $transient_key = 'spl_all_phones_data_v4';

    // On the first batch, clear the old data and count total products.
    if ($offset === 0) {
        $count_query = new WP_Query([
            'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1,
            'tax_query' => [ ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'mobile-phones'] ],
        ]);
        $total_products = $count_query->found_posts;
        update_option('spl_total_products_count', $total_products); // Save total for progress calculation
        set_transient($transient_key, [], 12 * HOUR_IN_SECONDS); // Start with an empty cache
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
        // We are done.
        wp_send_json_success([
            'status' => 'done',
            'message' => "Complete! {$total_products} products processed.",
            'percentage' => 100,
            'offset' => $new_offset
        ]);
    } else {
        // Continue to the next batch.
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
    delete_transient('spl_all_phones_data_v4');
}
add_action('save_post_product', 'spl_clear_product_cache_on_save');

// --- BRAND CONTENT MANAGER (ADMIN UI) ---

/**
 * Adds the "Brand Content" submenu.
 */
function spl_add_content_manager_menu() {
    // Add under the existing "Price List Cache" or create a main menu
    add_submenu_page(
        'spl-cache-manager', // Parent slug (matches your existing cache page)
        'Brand Editorial Content',
        'Brand Content',
        'manage_options',
        'spl-brand-content',
        'spl_render_content_manager_page'
    );
}
add_action('admin_menu', 'spl_add_content_manager_menu');

/**
 * Renders the Admin Interface for editing Brand Content.
 */
function spl_render_content_manager_page() {
    // 1. Handle Save
    if ( isset($_POST['spl_save_brand_content']) && check_admin_referer('spl_save_content_nonce') ) {
        $brand = sanitize_text_field($_POST['spl_selected_brand']);
        // Allow HTML tags for the editorial content
        $content = wp_kses_post($_POST['spl_brand_editor_content']);
        
        update_option("spl_brand_content_{$brand}", $content);
        echo '<div class="notice notice-success is-dismissible"><p>Content saved for <strong>' . ucfirst($brand) . '</strong>!</p></div>';
    }

    // 2. Determine Current Brand (Default to Samsung)
    $current_brand = isset($_GET['brand']) ? sanitize_text_field($_GET['brand']) : 'samsung';
    $brands = ['samsung', 'apple', 'xiaomi', 'oppo', 'vivo', 'realme', 'huawei', 'honor', 'infinix', 'tecno'];
    
    // 3. Retrieve Saved Content
    $saved_content = get_option("spl_brand_content_{$current_brand}", '');

    // 4. Default Template (If empty, give them the Samsung template to start)
    if ( empty($saved_content) && 'samsung' === $current_brand ) {
        $saved_content = spl_get_default_samsung_template(); 
    }
    ?>
    <div class="wrap">
        <h1>📱 Brand Price List Content Manager</h1>
        <p>Edit the "Top Section" (Rumors, Alerts, Price Drops) that appears above the price list for specific brands.</p>
        
        <h2 class="nav-tab-wrapper">
            <?php foreach ($brands as $brand) : ?>
                <a href="?page=spl-brand-content&brand=<?php echo $brand; ?>" class="nav-tab <?php echo ($current_brand === $brand) ? 'nav-tab-active' : ''; ?>">
                    <?php echo ucfirst($brand); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <form method="post" action="" style="margin-top: 20px;">
            <?php wp_nonce_field('spl_save_content_nonce'); ?>
            <input type="hidden" name="spl_selected_brand" value="<?php echo esc_attr($current_brand); ?>">
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin-top:0;">Editing: <span style="color:#2271b1;"><?php echo ucfirst($current_brand); ?></span></h3>
                
                <?php 
                // Render the WordPress WYSIWYG Editor
                wp_editor($saved_content, 'spl_brand_editor_content', [
                    'media_buttons' => true,
                    'textarea_rows' => 15,
                    'tinymce' => true
                ]); 
                ?>
                
                <p class="description" style="margin-top:10px;">
                    <strong>Tip:</strong> You can use HTML/Tailwind classes in the "Text" tab if you want specific styling.
                </p>
            </div>

            <p class="submit">
                <input type="submit" name="spl_save_brand_content" id="submit" class="button button-primary" value="Save <?php echo ucfirst($current_brand); ?> Content">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Returns the default HTML template for Samsung (so you don't have to type it from scratch).
 */
function spl_get_default_samsung_template() {
    ob_start();
    ?>
    <div class="mb-8 animate-fade-in-down">
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg shadow-sm">
            <h3 class="text-lg leading-6 font-medium text-yellow-800">🚨 Coming Soon: The Galaxy S26 Series (Jan 2026)</h3>
            <div class="mt-2 text-sm text-yellow-700">
                <p><strong>Should you buy now or wait?</strong> Samsung is expected to unveil the Galaxy S26 in late January 2026.</p>
                <ul class="list-disc list-inside">
                    <li><strong>The Upgrade:</strong> Rumored Snapdragon 8 Elite Gen 5.</li>
                    <li><strong>Advice:</strong> Wait 2 months for the latest tech, or buy the S25 Ultra now for a bargain.</li>
                </ul>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-red-500">
                <h3 class="text-xl font-bold text-gray-800 mb-3">📉 Top Price Drops</h3>
                <ul class="space-y-4">
                    <li><strong>Galaxy S25 Ultra:</strong> Now ₱62,990 (Down from ₱84k)</li>
                    <li><strong>Galaxy A55 5G:</strong> ₱18,290 (Permanent Cut)</li>
                </ul>
            </div>
            <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-green-500">
                <h3 class="text-xl font-bold text-gray-800 mb-3">🟩 Budget Picks</h3>
                <ul class="space-y-4">
                    <li><strong>Galaxy A06 5G:</strong> ₱7,990 (Best Budget 5G)</li>
                    <li><strong>Galaxy A05s:</strong> ₱4,890 (Basic 4G)</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}