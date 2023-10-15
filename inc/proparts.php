<?php

use PriorityWoocommerceAPI\WooAPI;



add_action('simply_update_product_data', function($item){

    $manufacturer =  $item['SUPDES'];
    $id = $item['product_id'];
    $query_args = array(
        'post_type' => 'mutags',
        'post_status' => 'publish',
        'title' => $manufacturer,
    );
    
    // The Query
    $the_query = new WP_Query( $query_args );
    
    // The Loop
    if ( $the_query->have_posts() ) {
        while ( $the_query->have_posts() ) {
            $the_query->the_post();
            $product_id = get_the_ID();
            update_field('mutag', $product_id, $id);
        }
    }   
});