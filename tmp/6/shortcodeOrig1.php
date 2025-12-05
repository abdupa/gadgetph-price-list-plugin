<?php
// includes/shortcode.php

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the Price List.
 */
function spl_render_price_list_shortcode( $atts ) {

    // 1. EXTRACT ATTRIBUTES
    $attributes = shortcode_atts( array( 'brand' => '' ), $atts );
    $requested_brand = trim( strtolower( $attributes['brand'] ) );
    
    // 2. CACHE & QUERY LOGIC
    $transient_key = 'spl_all_phones_data_v5';
    $cached_products = get_transient($transient_key);

    if ( false !== $cached_products ) {
        $all_mapped_products = $cached_products;
    } else {
        $args = [
            'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 
            'tax_query' => [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'mobile-phones' ] ],
            'meta_query' => [ 'relation' => 'AND', [ 'key' => '_price', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ], [ 'key' => '_price', 'value' => '', 'compare' => '!=' ] ]
        ];
        $products_query = new WP_Query( $args );
        $all_mapped_products = [];
        if ( $products_query->have_posts() ) {
            while ( $products_query->have_posts() ) {
                $products_query->the_post();
                if ( stripos( get_the_title(), 'watch' ) !== false ) { continue; }
                $mapped_product = spl_map_wc_product_to_pricelist_format( get_the_ID() );
                if ($mapped_product) { $all_mapped_products[] = $mapped_product; }
            }
        }
        wp_reset_postdata();
        set_transient($transient_key, $all_mapped_products, 2 * HOUR_IN_SECONDS);
    }

    // 3. FILTER BY BRAND
    if ( ! empty( $requested_brand ) ) {
        $all_mapped_products = array_filter( $all_mapped_products, function( $product ) use ( $requested_brand ) {
            return strtolower( $product['brand'] ) === $requested_brand;
        });
        $all_mapped_products = array_values( $all_mapped_products );
    }

    // 4. DATE LOGIC (MANUAL TRIGGER ONLY)
    // We get the date saved by the "Price List Cache" tool in Step 1.
    $manual_date = get_option('spl_manual_rebuild_date');
    
    if ( $manual_date ) {
        // Shows the date you last clicked "Start Rebuild"
        $formatted_date = date_i18n( 'F j, Y', strtotime( $manual_date ) );
    } else {
        // Fallback: If you haven't run the tool yet, show today's date.
        $formatted_date = date_i18n( 'F j, Y' );
    }

    wp_localize_script( 'spl-main-js', 'priceListData', [
        'phones' => $all_mapped_products, 'comparisons' => [], 'preSelectedBrand' => $requested_brand, 'lastUpdated' => $formatted_date 
    ]);
    
    ob_start();
    ?>
    
    <div class="bg-gray-50 p-2 md:p-6 rounded-xl">
        
        <?php if ( empty( $requested_brand ) ) : ?>
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Comprehensive Smartphone Price List (2025)</h2>
                <p class="text-gray-600 text-lg leading-relaxed">
                    Use the <strong>Search</strong> bar to quickly find any smartphone model, or filter by
                    <strong>Brand</strong> and <strong>Price</strong> range. The list updates instantly as you type.
                </p>
            </div>
        <?php endif; ?>
        
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm mb-10 sticky top-4 z-20">
             <div class="mb-4 flex items-center text-xs text-gray-500 font-medium">
                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full flex items-center gap-1">
                    <span role="img" aria-label="check">✅</span> 
                    Prices Updated: <span id="last-updated-date"><?php echo esc_html($formatted_date); ?></span>
                </span>
             </div>
             <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                 <div class="col-span-2">
                     <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                     <input type="text" id="search-input" placeholder="e.g. Samsung A55, 5000mAh..." class="w-full p-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                 </div>
                 
                 <div class="<?php echo !empty($requested_brand) ? 'hidden' : ''; ?>">
                     <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Brand</label>
                     <select id="brand-filter" class="w-full p-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                         <option value="all">All Brands</option>
                     </select>
                 </div>

                 <div class="<?php echo !empty($requested_brand) ? 'col-span-2' : ''; ?>">
                     <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Price Range</label>
                     <select id="price-filter" class="w-full p-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                         <option value="all">All Prices</option>
                         <option value="10000">Under ₱10,000 (Budget)</option>
                         <option value="25000">₱10,001 - ₱25,000 (Mid-Range)</option>
                         <option value="50000">₱25,001 - ₱50,000 (Premium) </option>
                         <option value="150000">Over ₱50,000 (Flagship)</option>
                     </select>
                 </div>
             </div>
         </div>
 
         <section id="phone-list-section" class="mb-12">
             <div id="phone-list-container" class="space-y-4"></div>
             <div id="load-more-container" class="mt-8 text-center"></div>
             <div id="no-results" class="hidden text-center p-12 bg-white border border-gray-200 rounded-xl shadow-sm mt-6">
                 <p class="text-xl text-gray-500 font-medium">No phones match your filters.</p>
                 <button onclick="window.location.reload()" class="mt-4 text-blue-600 hover:underline">Reset Filters</button>
             </div>
         </section>
 
         <section class="mb-12 bg-white p-6 md:p-8 rounded-xl border border-gray-200 shadow-sm">
             <div class="flex items-center gap-2 mb-6">
                <span class="text-2xl">⭐</span>
                <h2 class="text-2xl font-bold text-gray-900">Editor's Picks</h2>
             </div>
             <!-- <div id="popular-picks-container" class="flex gap-6 pb-4 overflow-x-auto horizontal-scroll"></div> -->
              <div id="popular-picks-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"></div>
         </section>
        <section class="mb-12 bg-white p-6 md:p-8 rounded-xl border border-gray-200 shadow-sm">
             <div class="flex items-center gap-2 mb-6">
                <span class="text-2xl">🏷️</span>
                <h2 class="text-2xl font-bold text-gray-900">Find by Price Segment</h2>
             </div>
             
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div class="bg-gray-50 p-5 rounded-xl border-l-4 border-green-500 hover:bg-green-50 transition-colors">
                     <h3 class="text-lg font-bold mb-2 text-green-700 flex justify-between items-center">
                        Budget <span class="text-xs bg-green-200 text-green-800 px-2 py-1 rounded">Under ₱10k</span>
                     </h3>
                     <ul id="budget-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/best-phones-under-10k-philippines/" target="_blank" class="text-xs font-bold text-green-600 hover:text-green-800 mt-3 inline-block tracking-wide uppercase">Discover Top Picks Under ₱10k &rarr;</a>
                 </div>
                 
                 <div class="bg-gray-50 p-5 rounded-xl border-l-4 border-blue-500 hover:bg-blue-50 transition-colors">
                     <h3 class="text-lg font-bold mb-2 text-blue-700 flex justify-between items-center">
                        Mid-Range <span class="text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded">₱10k - ₱25k</span>
                     </h3>
                     <ul id="mid-range-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/best-mid-range-phones-2025/" target="_blank" class="text-xs font-bold text-blue-600 hover:text-blue-800 mt-3 inline-block tracking-wide uppercase">Explore Best Mid-Range 2025 &rarr;</a>
                 </div>
                 
                 <div class="bg-gray-50 p-5 rounded-xl border-l-4 border-purple-500 hover:bg-purple-50 transition-colors">
                     <h3 class="text-lg font-bold mb-2 text-purple-700 flex justify-between items-center">
                        Premium <span class="text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded">₱25k - ₱50k</span>
                     </h3>
                     <ul id="premium-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/top-10-premium-smartphones-in-the-philippines-for-2023/" target="_blank" class="text-xs font-bold text-purple-600 hover:text-purple-800 mt-3 inline-block tracking-wide uppercase">See Top Rated Premium Phones &rarr;</a>
                 </div>
                 
                 <div class="bg-gray-50 p-5 rounded-xl border-l-4 border-red-500 hover:bg-red-50 transition-colors">
                     <h3 class="text-lg font-bold mb-2 text-red-700 flex justify-between items-center">
                        Flagship <span class="text-xs bg-red-200 text-red-800 px-2 py-1 rounded">Over ₱50k</span>
                     </h3>
                     <ul id="flagship-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/best-flagship-phones-2025/" target="_blank" class="text-xs font-bold text-red-600 hover:text-red-800 mt-3 inline-block tracking-wide uppercase">Compare All Flagship Models &rarr;</a>
                 </div>
             </div>
         </section>

        <section class="mb-12">
             <div class="bg-white p-6 md:p-8 rounded-xl card-shadow">
                 <h2 class="text-3xl font-bold text-gray-800 mb-2">Updated Smartphone Brand Price Lists</h2>
                 <p class="text-gray-600 mb-8">Compare the latest prices and specs from top smartphone brands in the Philippines.</p>
                 
                 <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6">
                    <?php
                    // DEFINE THE BRANDS ARRAY
                    $brands_list = [
                        ['name' => 'Apple',   'slug' => 'iphone',  'img' => 'apple.png',   'h' => 'h-12'],
                        ['name' => 'Samsung', 'slug' => 'samsung', 'img' => 'samsung.png', 'h' => 'h-12'],
                        ['name' => 'Xiaomi',  'slug' => 'xiaomi',  'img' => 'xiaomi.png',  'h' => 'h-12'],
                        ['name' => 'Huawei',  'slug' => 'huawei',  'img' => 'huawei.png',  'h' => 'h-12'],
                        ['name' => 'vivo',    'slug' => 'vivo',    'img' => 'vivo.png',    'h' => 'h-10'],
                        ['name' => 'Oppo',    'slug' => 'oppo',    'img' => 'oppo.png',    'h' => 'h-8'],
                        ['name' => 'realme',  'slug' => 'realme',  'img' => 'realme.png',  'h' => 'h-12'],
                        ['name' => 'Infinix', 'slug' => 'infinix', 'img' => 'infinix.png', 'h' => 'h-12'],
                        ['name' => 'Tecno',   'slug' => 'tecno',   'img' => 'tecno.png',   'h' => 'h-10'],
                        ['name' => 'Honor',   'slug' => 'honor',   'img' => 'honor.png',   'h' => 'h-12'],
                    ];

                    foreach ( $brands_list as $brand ) {
                        // EXCLUSION LOGIC:
                        // 1. Exact match (e.g., samsung == samsung)
                        // 2. Slug match (e.g., iphone == iphone)
                        // 3. Name/Slug cross match (e.g., Apple == iphone)
                        $brand_name_lower = strtolower( $brand['name'] );
                        $brand_slug_lower = strtolower( $brand['slug'] );
                        
                        if ( 
                             $requested_brand === $brand_name_lower || 
                             $requested_brand === $brand_slug_lower
                        ) {
                            continue; // Skip this brand if we are already on its page
                        }
                        ?>
                        <a href="https://gadgetph.com/smartphones/<?php echo esc_attr($brand['slug']); ?>/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                             <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                                 <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/' . $brand['img']; ?>" alt="<?php echo esc_attr($brand['name']); ?> Logo" class="<?php echo $brand['h']; ?> w-auto object-contain">
                             </div>
                             <h3 class="text-lg font-bold text-gray-800"><?php echo esc_html($brand['name']); ?></h3>
                         </a>
                        <?php
                    }
                    ?>
                 </div>
             </div>
         </section>
         <footer class="text-center py-8 border-t border-gray-200 mt-8 bg-white rounded-xl card-shadow">
            <p class="text-2xl font-bold text-gray-800 mb-3">Ready to find your best deal?</p>
            <a href="#phone-list-section" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition transform hover:scale-105">
                Start Comparing Prices Now &uarr;
            </a>
         </footer>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode( 'smartphone_price_list', 'spl_render_price_list_shortcode' );

/**
 * 2. SHORTCODE: [spl_price_drops brand="samsung" limit="3"]
 * Powers the "📉 Latest Price Drops" section.
 * UPDATED: Includes a FAIL-SAFE. If the admin cache is empty, it falls back 
 * to the main product list so the section is never empty.
 */

/**
 * 2. SHORTCODE: [spl_price_drops brand="samsung" limit="3"]
 * Powers the "📉 Latest Price Drops" section.
 * UPDATED: Reduced font sizes (text-lg for Price, text-sm for SRP).
 */
function spl_render_price_drops_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'brand' => '',
        'limit' => 3, 
    ), $atts );

    $brand_slug = trim( strtolower( $atts['brand'] ) );
    $limit = intval( $atts['limit'] );

    // 1. FAIL-SAFE DATA FETCH
    $all_drops = get_option('spl_cached_price_drops');
    if ( empty($all_drops) || !is_array($all_drops) ) {
        $transient_key = 'spl_all_phones_data_v5';
        $main_cache = get_transient($transient_key);
        if ( is_array($main_cache) ) {
            $all_drops = [];
            foreach ( $main_cache as $p ) {
                if ( $p['regular_price'] > $p['price'] ) {
                    $all_drops[] = $p;
                }
            }
            usort( $all_drops, function($a, $b) {
                return ($b['regular_price'] - $b['price']) - ($a['regular_price'] - $a['price']);
            });
        }
    }

    if ( empty($all_drops) ) return '';

    // 2. Filter & Slice
    $filtered_drops = array_filter( $all_drops, function($p) use ($brand_slug) {
        if ( ! empty($brand_slug) && strtolower($p['brand']) !== $brand_slug ) {
            return false;
        }
        return true;
    });

    $top_drops = array_slice( $filtered_drops, 0, $limit );

    if ( empty( $top_drops ) ) return '';

    ob_start();
    ?>
    <div class="h-full">
        
        <?php 
            $display_brand = ucfirst($brand_slug);
            if ( strtolower($brand_slug) === 'samsung' ) {
                $display_brand = 'Samsung Galaxy';
            }
        ?>

        <h2 class="flex items-center text-xl font-extrabold text-gray-900 mb-4">
            <span class="bg-red-100 text-red-600 p-1.5 rounded-lg mr-2 text-sm">📉</span> 
            Latest <?php echo $display_brand; ?> Price Drops
        </h2>

        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-300 h-auto">
            <ul class="block">
                <?php foreach ( $top_drops as $index => $phone ) : ?>
                    <?php 
                        $savings = $phone['regular_price'] - $phone['price'];
                        $percent = round( ($savings / $phone['regular_price']) * 100 );
                        
                        $is_last = ($index === count($top_drops) - 1);
                        $style_attr = $is_last ? '' : 'style="border-bottom: 1px solid #e5e7eb;"';
                        $margin_class = $is_last ? '' : 'pb-4 mb-4';
                    ?>
                    
                    <li class="relative <?php echo $margin_class; ?>" <?php echo $style_attr; ?>>
                        <div class="flex justify-between items-start">
                            <div>
                                <a href="<?php echo esc_url($phone['productUrl']); ?>" class="text-lg font-bold text-gray-900 hover:text-blue-600 transition-colors block leading-tight">
                                    <?php echo esc_html($phone['name']); ?>
                                </a>
                                <span class="inline-flex items-center mt-2 px-2.5 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700">
                                    🔥 Save ₱<?php echo number_format($savings); ?> (<?php echo $percent; ?>%)
                                </span>
                            </div>
                            
                            <div class="text-right ml-4">
                                <span class="block text-lg font-extrabold text-gray-900 tracking-tight">
                                    ₱<?php echo number_format($phone['price']); ?>
                                </span>
                                <span class="text-sm text-gray-400 line-through block mt-0 mb-1">
                                    SRP: ₱<?php echo number_format($phone['regular_price']); ?>
                                </span>
                                
                                <a href="<?php echo esc_url($phone['dealUrl']); ?>" target="_blank" rel="nofollow sponsored" class="inline-block mt-1 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 px-3 py-1.5 rounded-full transition-transform transform hover:scale-105">
                                    See Deal &rarr;
                                </a>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'spl_price_drops', 'spl_render_price_drops_shortcode' );

/**
 * 3. HELPER: [spl_price id="123"]
 * Output: ₱62,990
 */
function spl_get_single_price_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'id' => 0 ), $atts );
    if ( empty( $atts['id'] ) ) return '';
    if ( ! function_exists( 'wc_get_product' ) ) return ''; // Safety check

    $product = wc_get_product( $atts['id'] );
    if ( ! $product ) return '';

    return strip_tags( wc_price( $product->get_price() ) );
}
add_shortcode( 'spl_price', 'spl_get_single_price_shortcode' );

/**
 * 4. HELPER: [spl_regular_price id="123"]
 * Output: ₱84,990
 */
function spl_get_single_regular_price_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'id' => 0 ), $atts );
    if ( empty( $atts['id'] ) ) return '';
    if ( ! function_exists( 'wc_get_product' ) ) return ''; // Safety check

    $product = wc_get_product( $atts['id'] );
    if ( ! $product ) return '';

    return strip_tags( wc_price( $product->get_regular_price() ) );
}
add_shortcode( 'spl_regular_price', 'spl_get_single_regular_price_shortcode' );