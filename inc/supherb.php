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
    //$data['AMB_CRMCUSTNAME'] = (string)$customer_id;

	// billing customer details
	$billing_building_number = get_post_meta( $order->get_id(), 'billing_building_number', true ) ? get_post_meta( $order->get_id(), 'billing_building_number', true ) : '';
	$billing_app_number      = get_post_meta( $order->get_id(), 'billing_app_number', true ) ? get_post_meta( $order->get_id(), 'billing_app_number', true ) : '';
	$billing_floor_number    = get_post_meta( $order->get_id(), 'billing_floor_number', true ) ? get_post_meta( $order->get_id(), 'billing_floor_number', true ) : '';

	$data['ORDERSCONT_SUBFORM'][0]['ADRS'] = $order->get_billing_address_1().' '.$billing_building_number;

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
	unset($data['PAYMENTDEF_SUBFORM']);
	$payaccount = get_post_meta($order->get_id(), 'icredit_ccnum', true);
	$payaccount = substr($payaccount, -4);
	$data['PAYMENTDEF_SUBFORM']['PAYMENTCODE'] = "24"; 
	$data['PAYMENTDEF_SUBFORM']['payaccount'] = $payaccount;
	$data['PAYMENTDEF_SUBFORM']['QPRICE'] = floatval($order->get_total());
	$data['PAYMENTDEF_SUBFORM']['EMAIL'] = $order->get_billing_email();

	//set coupon to vprice instead vatprice
	$coupon = $order->get_coupon_codes();
	$items = [];
    foreach($data['ORDERITEMS_SUBFORM'] as $item ){
		$sku = $item['PARTNAME'];
		$product_id = wc_get_product_id_by_sku($sku);
		//if coupon or shipping
		if(!$product_id){
			$vatprice = $item['VATPRICE'];
			unset($item['VATPRICE']);
            $item['VPRICE'] =  $vatprice;
			//coupon
			if($item['PARTNAME'] == '000' ){
				if(!empty($coupon) && $item['TQUANT'] == -1){
					$item['PDES'] = $coupon[0];
				}
			}
			//remove shipping if shiping has cost
			else{
				if($item['VPRICE'] > 0){
					continue;
				}
			}
			
		}
        // if($item['PARTNAME'] == '000' ){
		// 	if(!empty($coupon) && $item['TQUANT'] == -1){
		// 		$item['PDES'] = $coupon[0];
		// 	}
		// 	//shipping
		// 	else{
		// 		$item['PDES'] = $order->get_shipping_method();
		// 		if ( 'איסוף עצמי' === $order_shipping_method ) {
		// 			$item['PARTNAME']  = '60';
		// 		}
		// 		// } elseif ( 'שליח עד הבית חינם' === $order_shipping_method ) {
		// 		// 	$item['PARTNAME']  = '10';
		// 		// } elseif ( 'שליח עד הבית' === $order_shipping_method ) {
		// 		// 	$item['PARTNAME']  = '20';
		// 		// } elseif ( 'שליח מוזל' === $order_shipping_method ) {
		// 		// 	$item['PARTNAME']  = '30';
		// 		// } elseif ( 'בוקסיט' === $order_shipping_method ) {
		// 		// 	$item['PARTNAME']  = '40';
		// 		// }
		// 	}
			
        // }
        $items[] = $item;
    }
    $data['ORDERITEMS_SUBFORM'] = $items;

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

//define select field for sync inventory
add_filter('simply_syncInventoryPriority_data', 'simply_syncInventoryPriority_data_func');

function simply_syncInventoryPriority_data_func($data)
{
	$expand = '$expand=LOGCOUNTERS_SUBFORM($select=AMB_BALANCE)';
    $data['expand'] = $expand;
    $data['select'] = 'PARTNAME, PARTDES, MPARTNAME, ADVA_PARTQUANT';
    return $data;

}

add_filter('simply_syncInventoryPriority_filter_addition', 'simply_syncInventoryPriority_filter_addition_func');

function simply_syncInventoryPriority_filter_addition_func($url_addition)

{
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);

    //$url_addition.= ' and SPEC20 eq \'Y\'';
    //$url_addition.= rawurlencode(' or UDATE ge ' . $bod) . ' and SPEC20 eq \'Y\'';
    $url_addition = $url_addition .' and SAPI_SYNC  eq \'Y\' and SPEC15 eq \'סופהרב\'';
	//$url_addition = 'PARTNAME eq \'SU2124TB30\' or PARTNAME eq \'SU4204CP60\'';
    return $url_addition;
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

	$product_attributes = get_post_meta($parent_product_id, '_product_attributes', true);
		if (isset($product_attributes['size'])) {
		// Convert 'size' to taxonomy-based 'pa_size'
		$product_attributes['pa_size'] = array(
			'name'         => 'pa_size',
			'value'        => '', // Leave empty for taxonomy-based attributes
			'position'     => $product_attributes['size']['position'],
			'is_visible'   => $product_attributes['size']['is_visible'],
			'is_variation' => $product_attributes['size']['is_variation'],
			'is_taxonomy'  => 1, // Set to taxonomy-based
		);

		// Remove the old custom attribute
		unset($product_attributes['size']);
	}
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


add_filter('simply_request_data_receipt', 'simply_request_data_receipt_func');
function simply_request_data_receipt_func($data){
	unset($data['PAYMENTDEF_SUBFORM']);
	$order_id = $data['orderId'];
	$order = wc_get_order($order_id);
	$payaccount = get_post_meta($order_id, 'icredit_ccnum', true);
	$payaccount = substr($payaccount, -4);
	$data['TPAYMENT2_SUBFORM'][0]['PAYMENTCODE'] = "40"; 
	$data['TPAYMENT2_SUBFORM'][0]['payaccount'] = $payaccount;
	$data['TPAYMENT2_SUBFORM'][0]['QPRICE'] = floatval($order->get_total());

	//$data['STCODE'] = "40";
	return $data;
}

?>