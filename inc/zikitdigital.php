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
add_action('simply_update_product_data', function($item){

	$quantity_box = $item['SPEC1'];
	if($quantity_box)
		update_field('quantity_box', $quantity_box, $item['product_id']);
	$minimum_to_order = $item['SPEC2'];
	if($minimum_to_order)
		update_field('minimum_to_order', $minimum_to_order, $item['product_id']);
	$size = $item['SPEC4'];
	if($size)
		update_field('size', $size, $item['product_id']);
	$weight = $item['SPEC7'];
	if($weight)
		update_field('weight', $weight, $item['product_id']);

});


add_filter('simply_sync_priority_customers_to_wp','simply_sync_priority_customers_to_wp');
function simply_sync_priority_customers_to_wp($user){
// $wp_user_object = new WP_User($user['user_id']);
// $wp_user_object -> set_role('');

	if($user['STATDES'] == 'מוגבל')
	{
		$wp_user_object = new WP_User($user['user_id']);
		$wp_user_object -> set_role('');
//update_user_meta($user['user_id'], 'role', '');
	}

}

add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
	$order_id = $data['orderId'];
	$order = new \WC_Order($order_id);
	$orderPayment = wc_get_payment_gateway_by_order($order);

	if($orderPayment->id =='cheque') {
		$data['ORDSTATUSDES'] = "אשראי פלוס";
	}
	if($orderPayment->id =='bacs') {
		$data['ORDSTATUSDES'] = "העברה בנקאית";
	}
// להוסיף אופציה של "סליקת אשראי". לדבג עם תוסף סליקה

//add more condition
	return $data;
}

add_filter('woocommerce_package_rates', 'simply_change_shipping_method_based_on_cart_total', 11, 2);
function simply_change_shipping_method_based_on_cart_total( $rates, $package ) {
// here all the calculations
// get total price סהכ מחיר
	$tot_price = $package['contents_cost'];

	$sum_weight = 0;
	foreach ( WC()->cart->get_cart() as $item ) {
		$product_weight = get_field('weight', $item['product_id']);
//default weight
		if (!$product_weight) $product_weight = 1;
		$tot_w_product = $item['quantity'] * $product_weight;
		$sum_weight+=$tot_w_product;
	}
//get total weight סהכ משקל
	$tot_weight=$sum_weight;
// get number of packs כמות חבילות
	$num_packs = ceil($tot_weight/19);
// packs price מחיר חבילות
	$packs_price = 36 * $num_packs;
// discount quantity כמות להנחה
	if($tot_price <= 600){
		$discount_single = 0;
		$discount_qty = 0;
	}
// discount_single סכום הנחה לבודד
	if($tot_price < 1200 && $tot_price >= 600){
		$discount_single = -18;
		$discount_qty = 1;
	}
	if($tot_price >= 1200 ){
		$discount_single = -36;
		$discount_qty = min(floor($tot_price/1200),$num_packs);
	}
// total_discount סהכ הנחה
	$total_discount = $discount_qty * $discount_single;
// final cost and update
	$shipping_cost = $packs_price + $total_discount;
	$rates['flat_rate:2']->cost = $shipping_cost; // flat_rate:3 is the name of the shipping method
	return $rates;
}