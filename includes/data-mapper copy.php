<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Maps a WC_Product object to a simple array for the price list.
 *
 * @param int $product_id The ID of the WooCommerce product.
 * @return array|null A simplified array of product data or null if invalid.
 */
function spl_map_wc_product_to_pricelist_format( $product_id ) {
    if ( ! function_exists( 'wc_get_product' ) ) { return null; }
    $product = wc_get_product( $product_id );
    if ( ! $product ) { return null; }

    // --- Get Brand from Product Categories ---
    $brand = 'Uncategorized';
    $terms = get_the_terms( $product_id, 'product_cat' );
    if ( ! empty( $terms ) ) {
        foreach ( $terms as $term ) {
            if ( $term->slug !== 'mobile-phones' ) {
                $brand = $term->name;
                break;
            }
        }
    }

    // --- Initialize the data array ---
    $mapped_data = [
        'id'         => $product->get_id(),
        'brand'      => $brand,
        'name'       => $product->get_name(),
        'price'      => (float) $product->get_price(),
        'productUrl' => $product->get_permalink(),
        'imageUrl'   => get_the_post_thumbnail_url( $product_id, 'thumbnail' ),
        'ram'        => 'N/A',
        'storage'    => 'N/A',
        'camera'     => 'N/A',
        'display'    => 'N/A',
        'segment'    => 'N/A',
        'isPopular'  => false,
    ];

    // --- NEW: PARSE DATA FROM ATTRIBUTES ---
    $attributes = $product->get_attributes();

    foreach ( $attributes as $attribute ) {
        $attribute_name = $attribute->get_name(); // This will be the slug, e.g., 'pa_internal-memory'
        $attribute_value = $product->get_attribute( $attribute_name ); // Gets the text value

        switch ( $attribute_name ) {
            case 'pa_internal-memory':
                // Attempt to find RAM, e.g., "8GB RAM"
                if ( preg_match( '/(\d+GB\s*RAM)/i', $attribute_value, $matches ) ) {
                    $mapped_data['ram'] = str_replace(' RAM', '', $matches[0]); // Extracts "8GB"
                }
                // Attempt to find Storage, e.g., "128GB". We get the first one.
                if ( preg_match( '/(\d+GB)/i', $attribute_value, $matches ) ) {
                    $mapped_data['storage'] = $matches[0];
                }
                break;
            
            case 'pa_main-camera':
                // Get the megapixel count, e.g., "50 MP"
                if ( preg_match( '/(\d+\s*MP)/i', $attribute_value, $matches ) ) {
                    $mapped_data['camera'] = $matches[0];
                }
                break;

            case 'pa_display-size':
                // Get the display size in inches, e.g., "6.67 inches"
                if ( preg_match( '/([\d\.]+\s*inches)/i', $attribute_value, $matches ) ) {
                    $mapped_data['display'] = $matches[0];
                }
                break;
        }
    }

    return $mapped_data;
}