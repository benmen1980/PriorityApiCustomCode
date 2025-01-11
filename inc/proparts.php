<?php

use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
function simply_syncItemsPriority_data_func($data)
{
    $data['select'] .= ',SUPDES';
    return $data;
}


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
            $mutag_id = get_the_ID();
            update_field('mutag', $mutag_id, $id); 
        }
    } else {
        error_log("Manufacturer: '$manufacturer' not found.");
    } 

    if($item['product_id'] !== 0) {
        $product = wc_get_product($item['product_id']);
        $short_description = $item['SPEC1'] . ' ' . $item['SPEC2'];
        $product->set_short_description($short_description);
        
        $description = $product->get_description();
        $allowed_tags = '<p>';
        $content = strip_tags($description, $allowed_tags);
        $product->set_description($content);

        $product->save();
    }    
});

add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    if (isset($data['CDES'])){
        unset($data['CDES']);
    };
    return $data;
}

add_filter('change_design_pricelist_qty_table', 'design_pricelist_qty_table');
function design_pricelist_qty_table($data) {
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $priority_number = get_user_meta($user_id, 'priority_customer_number', true);
        if (!empty($priority_number) ||  $priority_number !== 'C22222') {
            ?>
            <table style="width:100%!important" class="simply-tire-price-grid">
                <tbody id="simply-tire-price-grid-rows">
                <?php
                foreach ($data as $item) {
                    $price = $item["price_list_price"];
                    $float_price = $price;
                    $quantity = $item["price_list_quant"];
                    $price_discount = $item["price_list_disprice"];
                    $percent = $item["price_list_percent"];
                    ?>
                    <tr class="price_list_tr" >
                        <td class="price_list_td"> <?php echo 'קנה <span class="simply-tire-quantity">' . $quantity . '</span> יחידות ב <strong class="simply-tire-price">₪<span class="simply-tire-price-float simply-tire-price-number">' . $price_discount. '</span></strong> ליחידה (כולל מע"מ) וחסוך ' . $percent . '%'?> </td>
                    </tr>
                    <?php
                }
                ?></tbody>
            </table>
        <?php
        }
    } 
}