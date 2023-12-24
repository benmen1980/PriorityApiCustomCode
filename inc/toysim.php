<?php

add_action('simply_update_variation_data','simply_update_variation_data_func');
function simply_update_variation_data_func($item){
    $priceDisplay = get_option('woocommerce_prices_include_tax');
    $pri_price = ($priceDisplay == 'yes') ? $item['VATPRICE'] : $item['BASEPLPRICE'];
    $variation_id = $item['variation_id'];
    $product = wc_get_product($variation_id);
    $product->set_regular_price($pri_price);
    $product->save();
    update_post_meta($variation_id, '_regular_price',$pri_price);
}

?>