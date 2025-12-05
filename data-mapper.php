function spl_map_wc_product_to_pricelist_format( $post_id ) {
    $product = wc_get_product( $post_id );
    if ( ! $product ) return false;

    // --- GET BRAND LOGIC ---
    // Assuming you use Product Categories for Brands. 
    // Adjust 'product_cat' if you use a custom attribute like 'pa_brand'
    $terms = get_the_terms( $post_id, 'product_cat' ); 
    $brand_name = '';
    
    if ( $terms && ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            // Logic to skip "Smartphones" parent category and find the brand
            if ( $term->slug !== 'mobile-phones' && $term->parent != 0 ) { 
                $brand_name = $term->name;
                break;
            }
            // Fallback: just take the first one if logic above fails
            if ( empty($brand_name) && $term->slug !== 'mobile-phones') {
                $brand_name = $term->name;
            }
        }
    }

    return array(
        'id'    => $product->get_id(),
        'title' => $product->get_name(),
        'price' => $product->get_price(),
        'image' => get_the_post_thumbnail_url( $post_id, 'thumbnail' ),
        'link'  => get_permalink( $post_id ),
        'brand' => $brand_name, // <--- IMPORTANT: This key is required for the shortcode filter
        // ... any other specs ...
    );
}