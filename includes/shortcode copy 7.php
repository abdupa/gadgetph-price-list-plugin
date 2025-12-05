<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function spl_render_price_list_shortcode() {

    // We will use 'v3' as our final cache key.
    $transient_key = 'spl_all_phones_data_v4';
    // $transient_key = 'spl_all_phones_data_v3';
    $cached_products = get_transient($transient_key);

    if ( false !== $cached_products ) {
        // FAST PATH: Data is from the cache.
        $all_mapped_products = $cached_products;
    } else {
        // SLOW PATH: Cache is empty, so we query the database.
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Query all products
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

        // Save the fresh data to the cache for 2 hours.
        set_transient($transient_key, $all_mapped_products, 2 * HOUR_IN_SECONDS);
    }

    // Pass the final data to JavaScript.
    wp_localize_script( 'spl-main-js', 'priceListData', [
        'phones' => $all_mapped_products,
        // We are leaving comparisons out for now as requested.
        'comparisons' => [], 
    ]);
    
    // Begin outputting the final HTML.
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
                         <option value="10000">Under ₱10,000 (Budget)</option>
                         <option value="25000">₱10,001 - ₱25,000 (Mid-Range)</option>
                         <option value="50000">₱25,001 - ₱50,000 (Premium) </option>
                         <option value="150000">Over ₱50,000 (Flagship)</option>
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
                     <a href="https://gadgetph.com/best-phones-under-10k-philippines/" target="_blank" rel="noopener noreferrer" class="text-green-500 hover:text-green-600 font-medium mt-4 inline-block">See all Budget Deals &rarr;</a>
                 </div>
                 <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-blue-500">
                     <h3 class="text-xl font-semibold mb-3 text-blue-700">Mid-Range (₱10k-₱25k)</h3>
                     <ul id="mid-range-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/best-mid-range-phones-2025/" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:text-blue-600 font-medium mt-4 inline-block">Explore Mid-Range Options &rarr;</a>
                 </div>
                 <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-purple-500">
                     <h3 class="text-xl font-semibold mb-3 text-purple-700">Premium (₱25k-₱50k)</h3>
                     <ul id="premium-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/top-10-premium-smartphones-in-the-philippines-for-2023/" target="_blank" rel="noopener noreferrer" class="text-purple-500 hover:text-purple-600 font-medium mt-4 inline-block">See Premium Deals &rarr;</a>
                 </div>
                 <div class="bg-white p-6 rounded-xl card-shadow border-t-4 border-red-500">
                     <h3 class="text-xl font-semibold mb-3 text-red-700">Flagship (Over ₱50k)</h3>
                     <ul id="flagship-contenders" class="text-sm text-gray-700"></ul>
                     <a href="https://gadgetph.com/best-flagship-phones-2025/" target="_blank" rel="noopener noreferrer" class="text-red-500 hover:text-red-600 font-medium mt-4 inline-block">View Flagship Deals &rarr;</a>
                 </div>
             </div>
         </section>
         <!-- FINAL, EXPANDED Brand Spotlight Section with Official Logos -->
         <section class="mb-12">
             <div class="bg-white p-6 md:p-8 rounded-xl card-shadow">
                 <h2 class="text-3xl font-bold text-gray-800 mb-2">Official Price Lists by Top Smartphone Brands</h2>
                 <p class="text-gray-600 mb-8">Find the complete, up-to-date price list for every model from Apple, Samsung, and more in the Philippines. Compare specs and find the best deals today.</p>
                 
                 <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-6">

                     <!-- Apple Card -->
                     <a href="https://gadgetph.com/smartphones/iphone/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                             <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/apple.png'; ?>" alt="Apple Logo" class="h-12 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">Apple</h3>
                         <p class="text-sm text-gray-500 mt-1">Compare iPhones</p>
                     </a>

                     <!-- Samsung Card -->
                     <a href="https://gadgetph.com/smartphones/samsung/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                            <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/samsung.png'; ?>" alt="Samsung Logo" class="h-12 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">Samsung</h3>
                         <p class="text-sm text-gray-500 mt-1">Full Galaxy Price List</p>
                     </a>

                     <!-- Xiaomi Card -->
                     <a href="https://gadgetph.com/smartphones/xiaomi/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                            <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/xiaomi.png'; ?>" alt="Xiaomi Logo" class="h-12 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">Xiaomi</h3>
                         <p class="text-sm text-gray-500 mt-1">Redmi & Poco Prices</p>
                     </a>
                     
                     <!-- Huawei Card -->
                     <a href="https://gadgetph.com/smartphones/huawei/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                            <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/huawei.png'; ?>" alt="Huawei Logo" class="h-12 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">Huawei</h3>
                         <p class="text-sm text-gray-500 mt-1">Latest Nova & Pura</p>
                     </a>

                     <!-- vivo Card -->
                     <a href="https://gadgetph.com/smartphones/vivo/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                            <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/vivo.png'; ?>" alt="vivo Logo" class="h-10 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">vivo</h3>
                         <p class="text-sm text-gray-500 mt-1">See all V-Series</p>
                     </a>

                     <!-- Oppo Card -->
                     <a href="https://gadgetph.com/smartphones/oppo/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                            <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/oppo.png'; ?>" alt="Oppo Logo" class="h-8 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">Oppo</h3>
                         <p class="text-sm text-gray-500 mt-1">Reno & A-Series Deals</p>
                     </a>

                     <!-- realme Card -->
                     <a href="https://gadgetph.com/smartphones/realme/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                            <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/realme.png'; ?>" alt="realme Logo" class="h-12 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">realme</h3>
                         <p class="text-sm text-gray-500 mt-1">Find realme Prices</p>
                     </a>
                     
                     <!-- Infinix Card -->
                     <a href="https://gadgetph.com/smartphones/infinix/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                            <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/infinix.png'; ?>" alt="Infinix Logo" class="h-12 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">Infinix</h3>
                         <p class="text-sm text-gray-500 mt-1">Note & Zero Series</p>
                     </a>

                     <!-- Tecno Card -->
                     <a href="https://gadgetph.com/smartphones/tecno/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                            <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/tecno.png'; ?>" alt="Tecno Logo" class="h-10 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">Tecno</h3>
                         <p class="text-sm text-gray-500 mt-1">Camon & Pova Models</p>
                     </a>

                     <!-- Honor Card -->
                     <a href="https://gadgetph.com/smartphones/honor/price-list/" class="block p-4 bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 text-center transition duration-300 hover:shadow-xl hover:-translate-y-1">
                         <div class="flex justify-center items-center h-16 w-16 mx-auto mb-4">
                            <img src="<?php echo SPL_PLUGIN_URL . 'assets/images/honor.png'; ?>" alt="Honor Logo" class="h-12 w-auto object-contain">
                         </div>
                         <h3 class="text-lg font-bold text-gray-800">Honor</h3>
                         <p class="text-sm text-gray-500 mt-1">Magic Series Prices</p>
                     </a>

                 </div>
             </div>
         </section>

         <!-- Footer Section -->
         <footer class="text-center py-8 border-t border-gray-200 mt-8 bg-white rounded-xl card-shadow">
            <p class="text-2xl font-bold text-gray-800 mb-3">Ready to find your best deal?</p>
            <a href="#phone-list-section" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition transform hover:scale-105">
                Start Comparing Prices Now &uarr;
            </a>
            <p class="text-sm text-gray-500 mt-4">Prices last updated: <span id="last-updated-date"></span>.</p>
         </footer>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode( 'smartphone_price_list', 'spl_render_price_list_shortcode' );