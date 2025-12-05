<?php
/**
 * Plugin Name:       Smartphone Price List
 * Description:       Displays a dynamic and filterable price list for smartphones using WooCommerce products.
 * Version:           4.0.1 (Menu Fix)
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
 * Adds the Cache Manager page under the "Tools" menu.
 */
function spl_add_admin_menu_page() {
    add_management_page(
        'Price List Cache', 
        'Price List Cache', 
        'manage_options', 
        'spl-cache-manager', 
        'spl_render_admin_page'
    );
}
add_action('admin_menu', 'spl_add_admin_menu_page');

/**
 * Renders the UI for the batch processing admin page.
 */
function spl_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>Smartphone Price List Cache Manager</h1>
        <p>This tool rebuilds the product data cache in small, safe batches.</p>
        
        <div id="spl-rebuild-controls">
            <button id="spl-start-rebuild" class="button button-primary">Start Rebuild Now</button>
        </div>

        <div id="spl-progress-wrapper" style="display:none; margin-top: 20px;">
            <progress id="spl-progress-bar" value="0" max="100" style="width: 100%; height: 25px;"></progress>
            <p id="spl-status-text" style="text-align: center; font-style: italic; margin-top: 10px;"></p>
        </div>

        <div id="spl-success-message" style="display:none; margin-top: 20px;" class="notice notice-success is-dismissible">
            <p><strong>Success!</strong> The cache has been fully rebuilt.</p>
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
                runBatch(0);
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
                            runBatch(result.data.offset);
                        } else {
                            startButton.disabled = false;
                            successMessage.style.display = 'block';
                        }
                    } else {
                        statusText.innerHTML = '<strong style="color:red;">Error:</strong> ' + result.data.message;
                        startButton.disabled = false;
                    }
                })
                .catch(error => {
                    statusText.textContent = 'A network error occurred.';
                    startButton.disabled = false;
                });
            }
        });
    </script>
    <?php
}

function spl_ajax_run_rebuild_batch() {
    check_ajax_referer('spl_rebuild_nonce');
    $batch_size = 100;
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $transient_key = 'spl_all_phones_data_v4';

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
    
    $existing_data = get_transient($transient_key) ?: [];
    $new_data = array_merge($existing_data, $batch_products);
    set_transient($transient_key, $new_data, 12 * HOUR_IN_SECONDS);

    $new_offset = $offset + $batch_size;

    if ($new_offset >= $total_products) {
        wp_send_json_success(['status' => 'done', 'message' => "Complete! {$total_products} processed.", 'percentage' => 100, 'offset' => $new_offset]);
    } else {
        wp_send_json_success(['status' => 'continue', 'message' => "Processing... {$new_offset} complete.", 'percentage' => round(($new_offset / $total_products) * 100), 'offset' => $new_offset]);
    }
}
add_action('wp_ajax_spl_run_rebuild_batch', 'spl_ajax_run_rebuild_batch');

function spl_clear_product_cache_on_save() {
    delete_transient('spl_all_phones_data_v4');
}
add_action('save_post_product', 'spl_clear_product_cache_on_save');

// ==========================================================
// 📱 BRAND CONTENT MANAGER (ADMIN INTERFACE)
// ==========================================================

/**
 * FIXED: Uses add_management_page to ensure it appears under Tools.
 */
function spl_add_content_manager_menu() {
    add_management_page(
        'Brand Content',          // Page Title
        'Brand Content',          // Menu Title
        'manage_options',         // Capability
        'spl-brand-content',      // Menu Slug
        'spl_render_content_manager_page' // Callback
    );
}
add_action('admin_menu', 'spl_add_content_manager_menu');

/**
 * Renders the Admin Page for editing Brand Content.
 */
function spl_render_content_manager_page() {
    // 1. Handle Save Action
    if ( isset($_POST['spl_save_brand_content']) && check_admin_referer('spl_save_content_nonce') ) {
        $brand = sanitize_text_field($_POST['spl_selected_brand']);
        // Allow HTML for tables and styling
        $content = wp_kses_post($_POST['spl_brand_editor_content']);
        
        update_option("spl_brand_content_{$brand}", $content);
        echo '<div class="notice notice-success is-dismissible"><p>✅ Content saved for <strong>' . ucfirst($brand) . '</strong>!</p></div>';
    }

    // 2. Setup Variables
    $current_brand = isset($_GET['brand']) ? sanitize_text_field($_GET['brand']) : 'samsung';
    $brands = ['samsung', 'apple', 'xiaomi', 'oppo', 'vivo', 'realme', 'huawei', 'honor', 'infinix', 'tecno'];
    
    // 3. Get Saved Content
    $saved_content = get_option("spl_brand_content_{$current_brand}", '');

    // 4. Pre-fill Samsung with the 2026 Template if empty
    if ( empty($saved_content) && 'samsung' === $current_brand ) {
        $saved_content = spl_get_default_samsung_template(); 
    }
    ?>
    <div class="wrap">
        <h1>📱 Brand Price List Content Manager</h1>
        <p>Edit the specific "Rumors," "Price Drops," and "Budget Picks" that appear at the top of brand-specific price lists.</p>
        
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
                <h3 style="margin-top:0;">Editing: <span style="color:#2271b1; font-weight:bold;"><?php echo ucfirst($current_brand); ?></span></h3>
                <p class="description">Note: The preview here may look unstyled. The colors and grids will appear correctly on the frontend.</p>
                <hr>
                <?php 
                wp_editor($saved_content, 'spl_brand_editor_content', [
                    'media_buttons' => true,
                    'textarea_rows' => 20,
                    'tinymce' => true
                ]); 
                ?>
            </div>

            <p class="submit">
                <input type="submit" name="spl_save_brand_content" id="submit" class="button button-primary button-large" value="Save <?php echo ucfirst($current_brand); ?> Content">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Returns the 2026 HTML Template for Samsung.
 */
function spl_get_default_samsung_template() {
    ob_start();
    ?>
    <div class="mb-8 animate-fade-in-down">
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg shadow-sm">
            <h3 class="text-lg leading-6 font-medium text-yellow-800">🚨 Coming Soon: The Galaxy S26 Series (Jan 2026)</h3>
            <div class="mt-2 text-sm text-yellow-700">
                <p><strong>Should you buy now or wait?</strong> Samsung is expected to unveil the Galaxy S26 in late January 2026.</p>
                <ul class="list-disc list-inside mt-1">
                    <li><strong>The Upgrade:</strong> Rumored Snapdragon 8 Elite Gen 5 (Massive performance leap).</li>
                    <li><strong>Camera:</strong> Refined 200MP sensor with wider f/1.4 aperture.</li>
                </ul>
                <p class="mt-2"><strong>💡 Our Advice:</strong> If you want the absolute latest tech, <strong>wait 2 months</strong>. If you want a bargain, buy the Galaxy S25 Ultra now.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-red-500">
                <h3 class="text-xl font-bold text-gray-800 mb-3">📉 Top Price Drops (Nov 2025)</h3>
                <ul class="space-y-4 text-sm">
                    <li class="border-b border-gray-100 pb-2">
                        <div class="font-semibold text-gray-900">Galaxy S25 Ultra (256GB)</div>
                        <div class="text-red-600 font-bold">Now ₱62,990 <span class="text-xs text-gray-400 line-through">₱84,990</span></div>
                    </li>
                    <li class="border-b border-gray-100 pb-2">
                        <div class="font-semibold text-gray-900">Galaxy S24 Ultra (Clearance)</div>
                        <div class="text-red-600 font-bold">As low as ₱55,192</div>
                    </li>
                    <li>
                        <div class="font-semibold text-gray-900">Galaxy A55 5G</div>
                        <div class="text-green-600 font-bold">₱18,290 <span class="text-xs text-gray-500 font-normal">(Permanent Cut)</span></div>
                    </li>
                </ul>
            </div>

            <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-green-500">
                <h3 class="text-xl font-bold text-gray-800 mb-3">🟩 Budget Picks (Under ₱10k)</h3>
                <p class="text-sm text-gray-600 mb-3">The <strong>Galaxy A06 5G</strong> (Early 2025) is the new standard.</p>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Model</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-900">Galaxy A06 5G</td>
                            <td class="px-3 py-2 text-green-600 font-bold">₱7,990</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-900">Galaxy A05s</td>
                            <td class="px-3 py-2 text-gray-800">₱4,890</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
// ==========================================================
// 🧠 SMART CONTENT AUTOMATION ENGINE
// Add this to the bottom of smartphone-price-list.php
// ==========================================================

/**
 * 1. Internal Shortcode to fetch live product data.
 * Usage: [spl_product id="123" field="price"] or [spl_product id="123" field="specs"]
 */
function spl_product_data_shortcode($atts) {
    $atts = shortcode_atts(['id' => 0, 'field' => 'price'], $atts, 'spl_product');
    
    $product_id = intval($atts['id']);
    if (!$product_id) return '<span style="color:red;">(ID Missing)</span>';

    $product = wc_get_product($product_id);
    if (!$product) return '<span style="color:red;">(Product Not Found)</span>';

    switch ($atts['field']) {
        case 'price':
            // Returns price with currency symbol (e.g., ₱62,990)
            return $product->get_price_html();
        
        case 'specs':
            // Tries to get "Key Specs" attribute, or falls back to short description
            $specs = $product->get_attribute('key-specs'); 
            if (!$specs) $specs = wp_trim_words($product->get_short_description(), 10);
            return $specs;
            
        case 'link':
            return get_permalink($product_id);
            
        case 'image':
             return $product->get_image('thumbnail', ['class' => 'h-12 w-auto object-contain']);

        case 'title':
            return $product->get_name();

        default:
            return '';
    }
}
add_shortcode('spl_product', 'spl_product_data_shortcode');

/**
 * 2. Auto-Date Shortcode
 * Usage: [spl_date] -> "November 30, 2025"
 */
function spl_current_date_shortcode() {
    return date_i18n('F j, Y');
}
add_shortcode('spl_date', 'spl_current_date_shortcode');