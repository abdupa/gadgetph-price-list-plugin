<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function spl_map_wc_product_to_pricelist_format( $product_id ) {
    if ( ! function_exists( 'wc_get_product' ) ) { return null; }
    $product = wc_get_product( $product_id );
    if ( ! $product ) { return null; }

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

    // ** THE BULLETPROOF FIX IS HERE **
    // We will bypass the broken get_external_url() method entirely.
    $deal_link = $product->get_permalink(); // Start with the safe default link.
    
    // Check the product type safely.
    if ( $product->is_type('external') ) {
        // Instead of calling a method, we get the data directly from the product's saved meta field.
        // This is a core WordPress function and is guaranteed to exist.
        $external_url_from_meta = get_post_meta($product_id, '_product_url', true);

        // If we successfully found a URL in the meta field, use it.
        if ( ! empty($external_url_from_meta) ) {
            $deal_link = $external_url_from_meta;
        }
    }

    $mapped_data = [
        'id'            => $product->get_id(),
        'brand'         => $brand,
        'name'          => $product->get_name(),
        'price'         => (float) $product->get_price(),
        'regular_price' => (float) $product->get_regular_price(),
        'productUrl'    => $product->get_permalink(),
        'dealUrl'       => $deal_link, // Use the safe link we just determined.
        'imageUrl'      => get_the_post_thumbnail_url( $product_id, 'thumbnail' ),
        'ram'           => 'N/A',
        'storage'       => 'N/A',
        'camera'        => 'N/A',
        'display'       => 'N/A',
        'processor'     => 'N/A',
        'battery'       => 'N/A',
        'isPopular'     => has_term('editor-pick', 'product_tag', $product_id),
    ];

    $attributes = $product->get_attributes();
    if ( ! empty($attributes) ) {
        foreach ( $attributes as $attribute ) {
            $attribute_name = $attribute->get_name();
            $attribute_value = $product->get_attribute( $attribute_name );
            switch ( $attribute_name ) {
                case 'pa_internal-memory': if ( preg_match( '/(\d+GB\s*RAM)/i', $attribute_value, $matches ) ) { $mapped_data['ram'] = str_replace(' RAM', '', $matches[0]); } if ( preg_match( '/(\d+GB)/i', $attribute_value, $matches ) ) { $mapped_data['storage'] = $matches[0]; } break;
                case 'pa_main-camera': if ( preg_match( '/(\d+\s*MP)/i', $attribute_value, $matches ) ) { $mapped_data['camera'] = $matches[0]; } break;
                case 'pa_display-size': if ( preg_match( '/([\d\.]+\s*inches)/i', $attribute_value, $matches ) ) { $mapped_data['display'] = $matches[0]; } break;
                case 'pa_chipset': $mapped_data['processor'] = $attribute_value; break;
                case 'pa_battery-type': if ( preg_match( '/(\d+\s*mAh)/i', $attribute_value, $matches ) ) { $mapped_data['battery'] = $matches[0]; } else { $mapped_data['battery'] = $attribute_value; } break;
            }
        }
    }

    return $mapped_data;
}