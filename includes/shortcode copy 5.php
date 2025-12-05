<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function spl_render_price_list_shortcode() {

    $transient_key = 'spl_all_phones_data_v2';
    $cached_products = get_transient($transient_key);

    if ( false !== $cached_products ) {
        // FAST PATH: Data is from the cache.
        $all_mapped_products = $cached_products;
    } else {
        // SLOW PATH: Cache is empty, so we query the database.
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => 'mobile-phones',
                ],
            ],
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'     => '_price',
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'NUMERIC'
                ],
                [
                    'key'     => '_price',
                    'value'   => '',
                    'compare' => '!='
                ]
            ]
        ];
        $products_query = new WP_Query( $args );

        $all_mapped_products = [];
        if ( $products_query->have_posts() ) {
            while ( $products_query->have_posts() ) {
                $products_query->the_post();
                $mapped_product = spl_map_wc_product_to_pricelist_format( get_the_ID() );
                if ($mapped_product) {
                    $all_mapped_products[] = $mapped_product;
                }
            }
        }
        wp_reset_postdata();

        set_transient($transient_key, $all_mapped_products, 2 * HOUR_IN_SECONDS);
    }

    wp_localize_script( 'spl-main-js', 'priceListData', [
        'phones' => $all_mapped_products,
    ]);
    
    ob_start();
    ?>
    
    <div class="bg-gray-100 p-2 md:p-4 rounded-lg">
        <div class="bg-white p-4 md:p-6 rounded-xl card-shadow mb-8 sticky top-4 z-10 border-b border-gray-200">
             <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                 <div class="col-span-2">
                     <input type="text" id="search-input" placeholder="Search by Model, Brand, or Spec..." class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                 </div>
                 <div>
                     <select id="brand-filter" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                         <option value="all">All Brands</option>
                     </select>
                 </div>
                 <div>
                     <select id="price-filter" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                         <option value="all">All Prices</option>
                         <option value="10000">Under ₱10,000</option>
                         <option value="25000">Under ₱25,000</option>
                         <option value="50000">Under ₱50,000</option>
                         <option value="150000">Over ₱50,000</option>
                     </select>
                 </div>
             </div>
         </div>
 
         <section id="phone-list-section" class="mb-12">
             <h2 class="text-3xl font-bold text-gray-800 mb-6">Comprehensive Smartphone Price List</h2>
             <div id="phone-list-container" class="space-y-3"></div>
             <div id="load-more-container" class="mt-8 text-center"></div>
             <div id="no-results" class="hidden text-center p-8 bg-yellow-50 rounded-lg mt-6">
                 <p class="text-xl text-yellow-800 font-semibold">No phones match your filters.</p>
             </div>
         </section>
 
         <section class="mb-12">
             <h2 class="text-3xl font-bold text-gray-800 mb-4">⭐ Editor's Picks</h2>
             <div id="popular-picks-container" class="flex gap-6 pb-4 overflow-x-auto horizontal-scroll"></div>
         </section>
 
         <section class="mb-12">
             <h2 class="text-3xl font-bold text-gray-800">Find by Price Segment</h2>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                 <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-green-500">
                     <h3 class="text-xl font-semibold mb-3 text-green-700">Budget (Under ₱10k)</h3>
                     <ul id="budget-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/best-phones-under-10k-philippines/" target="_blank" class="text-green-500 hover:text-green-600 font-medium mt-4 inline-block">See all Budget Deals &rarr;</a>
                 </div>
                 <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-blue-500">
                     <h3 class="text-xl font-semibold mb-3 text-blue-700">Mid-Range (₱10k-₱25k)</h3>
                     <ul id="mid-range-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/best-mid-range-phones-2025/" target="_blank" class="text-blue-500 hover:text-blue-600 font-medium mt-4 inline-block">Explore Mid-Range Options &rarr;</a>
                 </div>
                 <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-purple-500">
                     <h3 class="text-xl font-semibold mb-3 text-purple-700">Premium (₱25k-₱50k)</h3>
                     <ul id="premium-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/top-10-premium-smartphones-in-the-philippines-for-2023/" target="_blank" class="text-purple-500 hover:text-purple-600 font-medium mt-4 inline-block">See Premium Deals &rarr;</a>
                 </div>
                 <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-red-500">
                     <h3 class="text-xl font-semibold mb-3 text-red-700">Flagship (Over ₱50k)</h3>
                     <ul id="flagship-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/best-flagship-phones-2025/" target="_blank" class="text-red-500 hover:text-red-600 font-medium mt-4 inline-block">View Flagship Deals &rarr;</a>
                 </div>
             </div>
         </section>
 
         <footer class="text-center py-8 border-t mt-8 bg-white rounded-xl card-shadow">
             <p class="text-sm text-gray-500">Prices last updated: <span id="last-updated-date"></span>.</p>
         </footer>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode( 'smartphone_price_list', 'spl_render_price_list_shortcode' );