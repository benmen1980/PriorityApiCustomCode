<?php 

use PriorityWoocommerceAPI\WooAPI;

//set partname to all product to 000

add_filter('simply_modify_orderitem','simply_modify_orderitem');

function simply_modify_orderitem($array){
	$data = $array['data'];
    $data['ORDERITEMS_SUBFORM'];
	$item = $array['item'];
	$product = $item->get_product();
	if($product){
		$sku = $product->get_sku();
		$product_name = $product->get_name();
		$meta_data = $item->get_meta_data();
		$size_meta = '';
		// Loop through metadata to find keys starting with 'cpo-cart-item-'
		foreach ($meta_data as $meta) {
			$key = $meta->key;
			$value = $meta->value;

			// Check if the key matches the CPO cart item prefix
			if (strpos($key, '_uni_cpo_') === 0) {
				$size_meta = $value;
				break; 
			}
		}
		$data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PDES'] = $sku.'-'.$product->get_name();
		$data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['ROYY_REMARK1'] = $size_meta;
		$data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['ORDERITEMSTEXT_SUBFORM'] = ['TEXT' => $size_meta];
	}
    

	$array['data'] = $data;
	return $array;

}

add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $order_id = $data['orderId'];
    $order = wc_get_order($order_id);
	

    $order_shipping_method = $order->get_shipping_method();
    //$pdt_name = ($array['item']->get_name() != '') ? $array['item']->get_name() : 'משלוח';
    for ($i = 0; $i < count($data['ORDERITEMS_SUBFORM']); $i++) {
        $sku = $data['ORDERITEMS_SUBFORM'][$i]['PARTNAME'];
        
        $data['ORDERITEMS_SUBFORM'][$i]['PARTNAME'] = '000';
		unset($data['ORDERITEMS_SUBFORM'][$i]['VPRICE']);
        if($sku == '' && $data['ORDERITEMS_SUBFORM'][$i]['TQUANT'] == 1){
            $data['ORDERITEMS_SUBFORM'][$i]['PDES'] = $order_shipping_method;
        } 
    }
	
  	$data['ORDSTATUSDES'] = 'מאושר (B)';
	unset($data['PAYMENTDEF_SUBFORM']['PAYCODE']);

    //sync  delivery codes for the order 
	$shipping_method = $order->get_shipping_methods();
	$shipping_method = array_shift($shipping_method);
	if (isset($shipping_method)) {
		$data_shipping = $shipping_method->get_data();
		$method_id = $data_shipping['method_id'];
		if($method_id == "free_shipping" || $method_id == "flat_rate") {
			$shipping_code = "001";
		}
		if ($method_id == "local_pickup") {
			$shipping_code = "002";
		}
		$data['STCODE'] = $shipping_code;
	}
	return $data;
}

add_filter('simply_request_data_receipt', 'simply_request_data_receipt_func');
function simply_request_data_receipt_func($data){
	unset($data['TPAYMENT2_SUBFORM']['PAYCODE']);
	return $data;
}

add_filter('simplyct_sendEmail', 'simplyct_sendEmail_func');
function simplyct_sendEmail_func($emails)
{
    array_push($emails, 'elisheva.g@simplyct.co.il');
    return $emails;
}

add_filter('simply_after_post_receipt', 'simply_after_receipt_func');
function simply_after_receipt_func($array)
{
    $receipt_number = $array["IVNUM"]; 
    $order_id = $array["order_id"];
    
    $username = WooAPI::instance()->option('username');
    $password = WooAPI::instance()->option('password');
    $url = 'https://'.WooAPI::instance()->option('url');
    if( false !== strpos( $url, 'p.priority-connect.online' ) ) {
        $url = 'https://p.priority-connect.online/wcf/service.svc';
    }
    $tabulaini = WooAPI::instance()->option('application');
    $company = WooAPI::instance()->option('environment');
    $appid = WooAPI::instance()->option('X-App-Id');
    $appkey = WooAPI::instance()->option('X-App-Key');

    $data['IVNUM'] = $receipt_number;
    $data['credentials']['appname'] = 'demo';
    $data['credentials']['username'] = $username;
    $data['credentials']['password'] = $password;
    $data['credentials']['url'] = $url;
    $data['credentials']['tabulaini'] = $tabulaini;
    $data['credentials']['language'] = '1';
    $data['credentials']['profile']['company'] = $company;
    $data['credentials']['devicename'] = 'roy';
    $data['credentials']['appid'] = $appid;
    $data['credentials']['appkey'] = $appkey;

    $curl = curl_init();
    curl_setopt_array($curl, 
        array(
            CURLOPT_URL => 'http://prinodehub1-env.eba-gdu3xtku.us-west-2.elasticbeanstalk.com/closeTinvoices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    $response_data = json_decode($response, true);
    $res = curl_getinfo($curl);
    if ($res['http_code'] <= 201) {
        if (isset($response_data['ivnum'])) {
			$order = wc_get_order($order_id);
			$order->update_meta_data('priority_recipe_number', $response_data['ivnum']);
			$order->update_meta_data('priority_recipe_status', 'סגורה');
			$order->save();
            //wp_mail('elisheva.g@simplyct.co.il','success close receipt for order'.$order_id, $response_data['ivnum']);
        }
    }
    else{
        if (isset($response_data['message'])) {
            $msg = $response_data['message'];
        } else {
            $msg = "No error message found.";
        }
        $order->update_meta_data('priority_recipe_status', $msg);
		$order->save();
		//wp_mail('elisheva.g@simplyct.co.il','close receipt', $msg.' error code'.$res['code']);
    }
    curl_close($curl);

}
