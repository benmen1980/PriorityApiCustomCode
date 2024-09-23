<?php

add_filter('simply_syncInventoryPriority_data','simply_syncInventoryPriority_data');
function simply_syncInventoryPriority_data($data){
    $data['expand'] = '$expand=LOGCOUNTERS_SUBFORM($expand=PARTAVAIL_SUBFORM($filter=TITLE eq \'הזמנות רכש\')),PARTBALANCE_SUBFORM($filter=WARHSNAME eq \'Main\')';
    return $data;
}
// to updade date of Back to stock
function simply_code_after_sync_inventory($product_id,$item){
    $date_invetury = $item['LOGCOUNTERS_SUBFORM'][0]['PARTAVAIL_SUBFORM'][0]['DUEDATE'];
    $_product = wc_get_product( $product_id);
    if ($_product != 0 && $_product->is_type( 'simple' ) ) {
        update_field('date_inventory', $date_invetury, $product_id);
    }
    else{
        //to variable product
        update_post_meta( $product_id, 'date_invetury_var', esc_attr( $date_invetury ) );
    }
}
//to sync parameters to product
//to sync parameters to product
add_action('simply_update_product_data', function($item){

    $quantity_box = $item['SPEC1'];
    $minimum_to_order = $item['SPEC2'];
    $volume = $item['SPEC4'];
    $weight = $item['SPEC7'];
    $param8 = $item['SPEC8'];
    $param9 = $item['SPEC9'];
    $param10 = $item['SPEC10'];
    $param11 = $item['SPEC11'];
    //update_post_meta( 68, 'quantity_box', 8 );

    $_product = wc_get_product( $item['product_id']);
    //$_product = wc_get_product( 68);

    if ($_product != 0 && $_product->is_type( 'simple' ) ) {
        if ($quantity_box) {
            update_field('quantity_box', $quantity_box, $item['product_id']);
        }
        is_numeric($minimum_to_order) ? $minimum_to_order : 0;
        update_field('minimum_to_order', $minimum_to_order, $item['product_id']);
        is_numeric($volume) ? $volume : 0;
        update_field('volume', $volume, $item['product_id']);
        is_numeric($weight) ? $weight : 0;
        update_field('weight', $weight, $item['product_id']);
        update_field('param8', $param8, $item['product_id']);
        update_field('param9', $param9, $item['product_id']);
        update_field('param10', $param10, $item['product_id']);
        update_field('param11', $param11, $item['product_id']);
    }
    else{
        update_post_meta(  $item['product_id'], 'quantity_box', $quantity_box );
        is_numeric($minimum_to_order) ? $minimum_to_order : 0;
        update_post_meta( $item['product_id'], 'minimum_to_order', $minimum_to_order );
        is_numeric($volume) ? $volume : 0;
        update_post_meta( $item['product_id'], 'volume', $volume );
        is_numeric($weight) ? $weight : 0;
        update_post_meta( $item['product_id'], 'weight', $weight );
        update_post_meta( $item['product_id'], 'param8', $param8 );
        update_post_meta( $item['product_id'], 'param9', $param9 );
        update_post_meta( $item['product_id'], 'param10', $param10 );
        update_post_meta( $item['product_id'], 'param11', $param11 );
    }
});


add_action('simply_update_variation_data', function($item) {

    $quantity_box = $item['SPEC1'];
    $minimum_to_order = $item['SPEC2'];
    $volume = $item['SPEC4'];
    $weight = $item['SPEC7'];
    $param8 = $item['SPEC8'];
    $param9 = $item['SPEC9'];
    $param10 = $item['SPEC10'];
    $param11 = $item['SPEC11'];

    update_post_meta(  $item['variation_id'], 'quantity_box', $quantity_box );
    is_numeric($minimum_to_order) ? $minimum_to_order : 0;
    update_post_meta( $item['variation_id'], 'minimum_to_order', $minimum_to_order );
    is_numeric($volume) ? $volume : 0;
    update_post_meta( $item['variation_id'], 'volume', $volume );
    is_numeric($weight) ? $weight : 0;
    update_post_meta( $item['variation_id'], 'weight1', $weight );
    update_post_meta( $item['variation_id'], 'param8', $param8 );
    update_post_meta( $item['variation_id'], 'param9', $param9 );
    update_post_meta( $item['variation_id'], 'param10', $param10 );
    update_post_meta( $item['variation_id'], 'param11', $param11 );

});

add_filter('simply_sync_priority_customers_to_wp','simply_sync_priority_customers_to_wp');
function simply_sync_priority_customers_to_wp($user){
    //    $wp_user_object = new WP_User($user['user_id']);
    //    $wp_user_object -> set_role('');

    if($user['STATDES'] == 'מוגבל')
    {
        $wp_user_object = new WP_User($user['user_id']);
        $wp_user_object -> set_role('');
        //update_user_meta($user['user_id'], 'role', '');
    }

}

add_filter('simply_sync_receipt_true', 'simply_sync_receipt_true_func', 2);
function simply_sync_receipt_true_func($order){
    $order_id = $data['orderId'];
    $order = new \WC_Order($order_id);
    $orderPayment = wc_get_payment_gateway_by_order($order);
    if($orderPayment->id =='cheque' || $orderPayment->id =='creditguard'){
        return true;
    }
    else{
        return false;
    }
}
add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    $order_id = $data['orderId'];


    $order = new \WC_Order($order_id);
    $orderPayment = wc_get_payment_gateway_by_order($order);

    if($orderPayment->id =='cheque') {
        $data['ORDSTATUSDES'] = "אובליגו";
    }
    if($orderPayment->id =='bacs') {
        $data['ORDSTATUSDES'] = "העברה בנקאית";
    }
    if($orderPayment->id =='creditguard') {
        $data['ORDSTATUSDES'] = "שולם באשראי";
    }

    // להוסיף אופציה של "סליקת אשראי". לדבג עם תוסף סליקה

    //להגדיר את ההנחה כללית להזמנה 0
    $data['PERCENT'] = 0.0;

    //add more condition
    return $data;
}

add_filter('woocommerce_package_rates', 'simply_change_shipping_method_based_on_cart_total', 11, 2);
function simply_change_shipping_method_based_on_cart_total( $rates, $package ) {
    // here all the calculations

    $cart_total = WC()->cart->get_cart_contents_total();
    //סהכ משקל
    $sum_weight = 0;
    //סהכ משקל נפחי
    $sum_volume = 0;
    // סהכ עלות כוללת להזמנה ללא מעמ
    $total_price=0;
    foreach ( WC()->cart->get_cart() as $item ) {
        // סהכ משקל בגרמים לעגלה
        $_product = wc_get_product( $item['product_id']);

        if( $_product->is_type( 'simple' )) {
            $product_weight = get_field('weight', $item['product_id']);
        }
        else {
            $product_weight = get_post_meta($item['variation_id'], 'weight1', true);
        }

        //default weight
        if (!$product_weight) $product_weight = 0;
        $tot_weight_product = $item['quantity'] * $product_weight;
        $sum_weight+=$tot_weight_product;
        //סיכום ידני לעלות כוללת לעגלה ללא מעמ בגלל התנגשות תוסף הנחות
        //  $total_price = (float) $item['custom_pricelist'];

        //סהכ משקל ניפחי לעגלה
        $product_volume = get_field('volume', $item['product_id']);
        //default volume
        if (!$product_volume) $product_volume = 0;
        $tot_volume_product = $item['quantity'] * $product_volume;
        $sum_volume+=$tot_volume_product;

    }
    //get total weight  המרת סהכ משקל לק"ג
    // $tot_weight = $sum_weight / 1000;
    $tot_weight = $sum_weight;
    //   סהכ משקל ניפחי
    $tot_volume=$sum_volume;
    if($tot_weight<$tot_volume){
        $tot_weight=$tot_volume;
    }

    // get number of packs כמות חבילות
    $num_packs = ceil($tot_weight / 19);
    // packs price מחיר חבילות
    $packs_Price = 36 * $num_packs;

    //כמות הנחות
    $discounts = floor($cart_total / 600);

    // סה"כ הנחה
    $tot_discounts= $discounts * 18;


    // final cost and update

    $shipping_cost = $packs_Price - $tot_discounts;
    if($shipping_cost<=0){
        $shipping_cost=0;
    }
    $rates['flat_rate:2'] -> cost = $shipping_cost; // flat_rate:3 is the name of the shipping method
    $newTax[1] = $shipping_cost * 0.17;
    $rates['flat_rate:2'] -> set_taxes($newTax);

    return $rates;
}

add_filter('simply_modify_orderitem','simply_modify_orderitem');
function simply_modify_orderitem($array){
    $data = $array['data'];
    $item = $array['item'];
    $text = wc_get_order_item_meta( $item->get_id(), 'add-custom-text', true );


    $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['ORDERITEMSTEXT_SUBFORM'] = ['TEXT' => $text];

    //adjust the discount percent
    unset($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PERCENT']);
    $order = new \WC_Order($data['BOOKNUM']);
    $user = $order->get_user();
    $user_id = $order->get_user_id();
    //$percent_zikit = get_the_author_meta($user_id, 'customer_percents');
    $percent_zikit = get_user_meta($user_id, 'customer_percents',true);
    $percent_zikit= (int)$percent_zikit[0]['PERCENT'];

//        if (strpos($percent_zikit, '%') !== false) {
//
//        $percent_zikit = str_replace('%', '', $percent_zikit);
//        }
    $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PERCENT'] = (int)$percent_zikit;

    $array['data'] = $data;
    return $array;

}

// Add this code to your theme's functions.php file or a custom plugin

function custom_shipping_method_label($label, $method) {
    if ($method->method_id === 'flat_rate' && $method->cost == 0) {
        $label = 'משלוח חינם';
    }
    return $label;
}

add_filter('woocommerce_shipping_rate_label', 'custom_shipping_method_label', 10, 2);

/* add_filter('sync_order_product', 'sync_order_product_func');

function sync_order_product_func($item) {
return "no";

}*/



