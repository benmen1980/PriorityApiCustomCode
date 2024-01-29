<?php 
use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_request_data', 'simply_func');
function simply_func($data){

    //$order_id = $data['AMB_CRMORDNAME'];

	$order_id = $data['orderId'];
    $order = new \WC_Order($order_id);

	$data['AMB_CRMORDNAME'] = $order_id;

	$user_id = $order->get_user_id();
	$order_user = get_userdata($user_id);
    $order_shipping_method = $order->get_shipping_method();
    if ( 'איסוף עצמי' === $order_shipping_method ) {
		$order_shipping_code  = '0';
		$order_shipping_desc  = 'ללא';
		$order_shipping_price = 0;
	} elseif ( 'שליח עד הבית חינם' === $order_shipping_method ) {
		$order_shipping_code  = '10';
		$order_shipping_desc  = $order_shipping_method;
		$order_shipping_price = 0;
	} elseif ( 'שליח עד הבית' === $order_shipping_method ) {
		$order_shipping_code  = '20';
		$order_shipping_desc  = $order_shipping_method;
		$order_shipping_price = 29.90;
	} elseif ( 'שליח מוזל' === $order_shipping_method ) {
		$order_shipping_code  = '30';
		$order_shipping_desc  = $order_shipping_method;
		$order_shipping_price = 14.90;
	} elseif ( 'בוקסיט' === $order_shipping_method ) {
		$order_shipping_code  = '40';
		$order_shipping_desc  = $order_shipping_method;
		$order_shipping_price = 14.90;
	}

    $data['AYEL_SHIPCCODE'] = $order_shipping_code;
	
	$data['STCODE'] = "40";

    $customer_id = $order->get_customer_id() ? $order->get_customer_id() : '0';
    $data['AMB_CRMCUSTNAME'] = $customer_id;

	// billing customer details
	$billing_first_name = $order->get_billing_first_name() ? $order->get_billing_first_name() : '0';
	$billing_last_name  = $order->get_billing_last_name() ? $order->get_billing_last_name() : '0';
	$billing_full_name  = $billing_first_name . ' ' . $billing_last_name;
	$billing_building_number = get_post_meta( $order->get_id(), 'billing_building_number', true ) ? get_post_meta( $order->get_id(), 'billing_building_number', true ) : '0';
	$billing_app_number      = get_post_meta( $order->get_id(), 'billing_app_number', true ) ? get_post_meta( $order->get_id(), 'billing_app_number', true ) : '0';
	$billing_floor_number    = get_post_meta( $order->get_id(), 'billing_floor_number', true ) ? get_post_meta( $order->get_id(), 'billing_floor_number', true ) : '0';

	$customer_data = [

		'PHONE' => $order->get_billing_phone(),
		'EMAIL' => $order->get_billing_email(),
		'ADRS' => $order->get_billing_address_1().' '.$billing_building_number,
		'STATEA' => $order->get_billing_city(),
		'ZIP' => $order->get_shipping_postcode(),
	];

	$data['AINVOICESCONT_SUBFORM'][] = $customer_data;

	// Shipping address line.
	$shipping_address_1       = $order->get_shipping_address_1();
	$shipping_city            = $order->get_shipping_city();
	//$shipping_state           = $order->get_shipping_state() ? $order->get_shipping_state() : '0';
	$shipping_postcode        = $order->get_shipping_postcode();
	$shipping_country         = $order->get_shipping_country();
	$shipping_building_number = get_post_meta( $order->get_id(), 'shipping_building_number', true ) ? get_post_meta( $order->get_id(), 'shipping_building_number', true ) : $billing_building_number;
	$shipping_phone_number    = get_post_meta( $order->get_id(), 'shipping_phone_number', true ) ? get_post_meta( $order->get_id(), 'shipping_phone_number', true ) : $order->get_billing_phone();
	$shipping_app_number      = get_post_meta( $order->get_id(), 'shipping_app_number', true ) ? get_post_meta( $order->get_id(), 'shipping_app_number', true ) : $billing_app_number ;
	$shipping_floor_number    = get_post_meta( $order->get_id(), 'shipping_floor_number', true ) ? get_post_meta( $order->get_id(), 'shipping_floor_number', true ) : $billing_floor_number;

	$shipping_first_name = $order->get_shipping_first_name(); // Get shipping first name.
	$shipping_last_name  = $order->get_shipping_last_name(); // Get shipping_last_name.
	$shipping_full_name  = $shipping_first_name . ' ' . $shipping_last_name;


	$shipping_data = [
        'NAME' => $shipping_full_name,
        'CUSTDES' => (!empty($order_user)) ? $order_user->user_firstname . ' ' . $order_user->user_lastname : ($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
        'PHONENUM' => $shipping_phone_number,
        'ADDRESS' => $shipping_address_1.' '.$shipping_building_number,
        //'ADDRESS2' => $address2,
        'STATE' => $shipping_city,
        'ZIP' => $shipping_postcode,
		'ADDRESS3' => $shipping_app_number,
		'SHIPACCOUNTNUM' => $shipping_floor_number
    ];

	$data['SHIPTO2_SUBFORM'] = $shipping_data;

	$order_total = $order->get_total();
	$data['TOTPRICE'] = $order_total;

	return $data;
}

add_filter('simply_modify_customer_number', 'simply_modify_customer_number_func');
function simply_modify_customer_number_func($cust_data)
{
    $order = $cust_data['order'];

    $currency = $order->get_currency();

	if ( 'ILS' === $currency ) {
		$client_number = 'CRMSUP';
		$client_name   = 'CRM SUPHERB';
	} elseif ( 'USD' === $currency ) {
		$client_number = 'CRMSUP$';
		$client_name   = '$ CRM SUPHERB';
	} else {
		$client_number = 'CRMSUPE';
		$client_name   = 'CRM SUPHERB יורו';
	}

    $cust_data['CUSTNAME'] = $client_number;
    return $cust_data;
}

// define new field to set stock
add_filter('simply_sync_inventory_priority', 'simply_sync_inventory_priority_func');
function simply_sync_inventory_priority_func($item)
{
    $item['stock'] = $item['LOGCOUNTERS_SUBFORM'][0]['AMB_BALANCE'];
    return $item;
}

//define out of stock if stock less than 20
function simply_code_after_sync_inventory($product_id,$item){
	$this_product = wc_get_product( $product_id );
	if($product_id){
		$item['stock'] = $item['LOGCOUNTERS_SUBFORM'][0]['AMB_BALANCE'];
		$stock = $item['stock'];
		if ( $stock <= 20 ) {
			update_post_meta( $product_id, '_stock_status', wc_clean( 'outofstock' ) );
			wp_set_post_terms( $product_id, 'outofstock', 'product_visibility', true );
			$this_product->set_stock_status( wc_clean( 'outofstock' ) );
		}
		else{
			update_post_meta( $product_id, '_stock_status', wc_clean( 'instock' ) );
			wp_set_post_terms( $product_id, 'outofstock', 'product_visibility', false );
			$this_product->set_stock_status( wc_clean( 'instock' ) );
		}
	}
}

//define select field for sync item
add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
function simply_syncItemsPriority_data_func($data)
{
	$data['select'] = 'PARTNAME,PARTDES,MPARTNAME,MPARTDES,SAPI_SYNC,ADVA_PARTQUANT,SAPI_VARIENT,INVFLAG';
	return $data;
}

//allow only to update price if product already exist
add_action('simply_update_product_price', 'simply_update_product_price_func');
function simply_update_product_price_func($item)
{
	//$stop_processing = true;
    $product_id = $item['product_id'];
	$pri_price = $item['PARTINCUSTPLISTS_SUBFORM'][0]['VATPRICE'];
	//$product_status = get_post_status($product_id);
	$my_product = wc_get_product( $product_id );
	//if ($product_status == 'publish') {
	$my_product->set_regular_price( $pri_price );
	$my_product->save();
	//}
}

//allow only to update price if product variation already exist
add_action('simply_update_variation_price','simply_update_variation_price_func');
function simply_update_variation_price_func($variation_data){
    $variation_id = wc_get_product_id_by_sku($variation_data['sku']);
    $product = wc_get_product($variation_id);
	$parent_product_id = $product->get_parent_id();
	$pri_price = $variation_data['regular_price'];
	//$product_status = get_post_status($parent_product_id);
	//if ($product_status == 'publish') {
	update_post_meta($variation_id, '_regular_price',$pri_price);
	$product->set_status('publish');
	$product->save();
	//}
}

//field to create attribute variation
add_filter('simply_ItemsAtrrVariation', 'simply_ItemsAtrrVariation_func');
function simply_ItemsAtrrVariation_func($item)
{
	$attributes['size'] = $item['ADVA_PARTQUANT'].' כמוסות';
	$item['attributes'] = $attributes;
	return $item;
}

?>