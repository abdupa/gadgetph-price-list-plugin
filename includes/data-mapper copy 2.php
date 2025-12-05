<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        'id'            => $product->get_id(),
        'brand'         => $brand,
        'name'          => $product->get_name(),
        'price'         => (float) $product->get_price(),
        'regular_price' => (float) $product->get_regular_price(), // <-- NEW
        'productUrl'    => $product->get_permalink(),
        'imageUrl'      => get_the_post_thumbnail_url( $product_id, 'thumbnail' ),
        'ram'           => 'N/A',
        'storage'       => 'N/A',
        'camera'        => 'N/A',
        'display'       => 'N/A',
        'processor'     => 'N/A', // <-- NEW
        'battery'       => 'N/A', // <-- NEW
        'isPopular'     => has_term('popular-pick', 'product_tag', $product_id), // Checks for a 'popular-pick' tag
    ];

    // --- PARSE DATA FROM ATTRIBUTES ---
    $attributes = $product->get_attributes();

    foreach ( $attributes as $attribute ) {
        $attribute_name = $attribute->get_name();
        $attribute_value = $product->get_attribute( $attribute_name );

        switch ( $attribute_name ) {
            case 'pa_internal-memory':
                if ( preg_match( '/(\d+GB\s*RAM)/i', $attribute_value, $matches ) ) {
                    $mapped_data['ram'] = str_replace(' RAM', '', $matches[0]);
                }
                if ( preg_match( '/(\d+GB)/i', $attribute_value, $matches ) ) {
                    $mapped_data['storage'] = $matches[0];
                }
                break;
            
            case 'pa_main-camera':
                if ( preg_match( '/(\d+\s*MP)/i', $attribute_value, $matches ) ) {
                    $mapped_data['camera'] = $matches[0];
                }
                break;

            case 'pa_display-size':
                if ( preg_match( '/([\d\.]+\s*inches)/i', $attribute_value, $matches ) ) {
                    $mapped_data['display'] = $matches[0];
                }
                break;
            
            case 'pa_chipset': // <-- NEW
                $mapped_data['processor'] = $attribute_value;
                break;

            case 'pa_battery-type': // <-- NEW
                if ( preg_match( '/(\d+\s*mAh)/i', $attribute_value, $matches ) ) {
                    $mapped_data['battery'] = $matches[0];
                } else {
                    $mapped_data['battery'] = $attribute_value;
                }
                break;
        }
    }

    return $mapped_data;
}