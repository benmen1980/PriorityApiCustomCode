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
	
	if (function_exists('dgwt_wcas_index_product')) {
		dgwt_wcas_index_product($item['product_id']);
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
	
	if (function_exists('dgwt_wcas_index_product')) {
		dgwt_wcas_index_product( $item['variation_id'] );
	}

});

add_filter('simply_sync_priority_customers_to_wp','simply_sync_priority_customers_to_wp');
function simply_sync_priority_customers_to_wp($user){
	
    update_user_meta($user['user_id'], 'first_name', $user['SPEC19']);

    if($user['STATDES'] == 'מוגבל' || $user['STATDES'] == 'לא פעיל')
    {
        $wp_user_object = new WP_User($user['user_id']);
        $wp_user_object -> set_role('');
        $wp_user_object->save();
        //update_user_meta($user['user_id'], 'role', '');
    }

    if($user['STATDES'] == 'פעיל' || $user['STATDES'] == 'זמני')
    {
        $user_data = get_userdata($user['user_id']);
        if ($user_data) {
            // Get user roles
            $user_roles = $user_data->roles;
            if(!empty($user_roles)) {
                foreach ($user_roles as $key => $role) {
                    continue;
                }
            } else {
                $wp_user_object = new WP_User($user['user_id']);
                $wp_user_object -> set_role('customer');
                $wp_user_object -> set_user_pass($user['PHONE']);
                wp_set_password($user['PHONE'], $user['user_id']);
                $wp_user_object -> set_email($user['EMAIL']);
                $wp_user_object->save(); 
            
            }
        }
        
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
        if (!$product_weight) $product_weight = 1;
        $tot_weight_product = $item['quantity'] * $product_weight;
        $sum_weight+=$tot_weight_product;
        //סיכום ידני לעלות כוללת לעגלה ללא מעמ בגלל התנגשות תוסף הנחות
        //  $total_price = (float) $item['custom_pricelist'];

        //סהכ משקל ניפחי לעגלה
        $product_volume = get_field('volume', $item['product_id']);
        //default volume
        if (!$product_volume) $product_volume = 1;
        $tot_volume_product = $item['quantity'] * $product_volume;
        $sum_volume+=$tot_volume_product;

    }
    //get total weight  המרת סהכ משקל לק"ג
    // $tot_weight = $sum_weight / 1000;
    $tot_weight = $sum_weight;
    //   סהכ משקל ניפחי
    $tot_volume=$sum_volume;
    /*if($tot_weight<$tot_volume){
        $tot_weight=$tot_volume;
    }*/

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
    $tax_rate_percent = get_standard_tax_rate_percent(); // Returns the tax rate. For example: 18.0
    $tax_multiplier = $tax_rate_percent / 100; // 0.18
    $newTax[1] = $shipping_cost * $tax_multiplier;
    $rates['flat_rate:2'] -> set_taxes($newTax);

    return $rates;
}

//Function that returns the tax rate
function get_standard_tax_rate_percent() {
    // Gets the main tax rates
    $tax_classes = WC_Tax::get_tax_classes(); 
    array_unshift($tax_classes, ''); 

    foreach ($tax_classes as $class) {
        $rates = WC_Tax::get_rates($class);
        foreach ($rates as $rate) {
            if ($rate['label'] === 'מע"מ' && $rate['shipping'] === 'yes') {
                return floatval($rate['rate']); // ext: 18.0
            }
        }
    }

    return 0; 
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
	
	//set vatprice instead of vprice
//     unset($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VPRICE']);
// 	$quantity = (int)$item->get_quantity();
//     $line_tax = (float)$item->get_subtotal_tax();
//     $line_after_discount = (float)$item->get_total();
//     $line_before_discount = (float)$item->get_subtotal();
//     $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VATPRICE'] = ($line_before_discount +  $line_tax) * $quantity;

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

//add another email recipient for creditguard
add_filter( 'woocommerce_email_recipient_new_order', 'payment_id_based_new_order_email_recipient', 10, 2 );
function payment_id_based_new_order_email_recipient( $recipient, $order ){
    // Avoiding backend displayed error in Woocommerce email settings (mandatory)
    if( ! is_a($order, 'WC_Order') ) 
        return $recipient;

    // Here below set in the array the desired payment Ids
    $orderPayment = wc_get_payment_gateway_by_order($order);
   
    if($orderPayment->id =='creditguard') {
        $recipient .= ', ccpayzikit@gmail.com';
    } 
    return $recipient;
}

add_filter('simply_request_data_receipt', 'simply_receipt_func');
function simply_receipt_func($data)
{
    unset($data['CDES']);
    unset($data['BOOKNUM']);
	return $data;
}