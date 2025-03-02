<?php 

use PriorityWoocommerceAPI\WooAPI;

//set partname to all product to 000

add_filter('simply_modify_orderitem','simply_modify_orderitem');

function simply_modify_orderitem($array){
	$metadata_output = [];
	$data = $array['data'];
	$item = $array['item'];
	$product = $item->get_product();
	if($product){
		$sku = $product->get_sku();
		$product_name = $product->get_name();

		$metadata = $item->get_meta_data();
		foreach ($metadata as $meta) {
			$key = $meta->key;
			$value = $meta->value;
			// Check if the value is not an array
			if (!is_array($value) && $value != '') {
				// Extract string before "|" if present
				$string_before_pipe = explode('|', $value)[0];
				if ($key != '_wcpa_empty_label' && $key != 'wcpa_empty_label') {
					$metadata_output[] = $key . ': ' . $string_before_pipe;
				}
			}
		}
		$metadata_string = implode(", ", $metadata_output);
					
		$remark1 = mb_substr($metadata_string, 0, 120, 'UTF-8');
		$remark2 = mb_substr($metadata_string, 120, null, 'UTF-8');
		// Set product description and remarks for the current item
		$data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PDES'] = $sku . '-' . $product_name;
		$data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['ROYY_REMARK1'] = $remark1;
		$data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['ROYY_REMARK2'] = $remark2;
		$data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['ORDERITEMSTEXT_SUBFORM'] = ['TEXT' => $remark1.' '.$remark2];
	}

	$array['data'] = $data;
	return $array;

}

add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $order_id = $data["orderId"];
    $order = wc_get_order($order_id);
		
    $order_shipping_method = $order->get_shipping_method();
	$fees_array = [];
	$pdts_array = [];
	if ($order) {
			
		// Get fees related to this order item
		foreach ($order->get_items('fee') as $fee_id => $fee) {
			$fee_name = $fee->get_name();
			$fee_total_tax = round($fee->get_total_tax());
			$fee_total = round($fee->get_total());
			$fee_total_inc_tax = $fee_total + $fee_total_tax;
			$fees_array[$fee_name] = $fee_total_inc_tax;
			// Output the fee and associated product ID
			//echo "Product ID: {$product_id} | Fee Name: {$fee_name} | Fee Total: {$fee_total}<br>";
		}
		uksort($fees_array, function ($a, $b) {
			// Handle "פריט" without a number
			if ($a === 'פריט') return -1;
			if ($b === 'פריט') return 1;
		
			// Extract numbers from "פריט(x)"
			preg_match('/\((\d+)\)$/', $a, $matches_a);
			preg_match('/\((\d+)\)$/', $b, $matches_b);
		
			$num_a = $matches_a[1] ?? PHP_INT_MAX; // Default large number if no match
			$num_b = $matches_b[1] ?? PHP_INT_MAX; // Default large number if no match
		
			// Compare the extracted numbers
			return $num_a - $num_b;
		});

		$fee_values = array_values($fees_array);
		// echo "<pre>";
		// print_r($fee_values);
		// echo "</pre>";
		foreach($data['ORDERITEMS_SUBFORM'] as  $index => &$item ){
			if (isset($fee_values[$index])) {
				$item["VATPRICE"] = $fee_values[$index];
			}
            
		}
	}

    for ($i = 0; $i < count($data['ORDERITEMS_SUBFORM']); $i++) {
        $sku = $data['ORDERITEMS_SUBFORM'][$i]['PARTNAME'];
        $data['ORDERITEMS_SUBFORM'][$i]['PARTNAME'] = '000';
		unset($data['ORDERITEMS_SUBFORM'][$i]['VPRICE']);
        if($sku == '' && $data['ORDERITEMS_SUBFORM'][$i]['TQUANT'] == 1){
            $shipping_total = round($order->get_shipping_total()); // Shipping cost excluding tax
            $shipping_tax = round($order->get_shipping_tax()); // Tax on shipping
            $total_including_tax = $shipping_total + $shipping_tax; 
            $data['ORDERITEMS_SUBFORM'][$i]['PDES'] = $order_shipping_method;
            $data['ORDERITEMS_SUBFORM'][$i]['VATPRICE'] = $total_including_tax;
        }  
    }
	$data['ORDSTATUSDES'] = 'מאושר (C)';
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
	unset($data['TPAYMENT2_SUBFORM'][0]['PAYCODE']);
	return $data;
}

add_filter('simplyct_sendEmail', 'simplyct_sendEmail_func');
function simplyct_sendEmail_func($emails)
{
    array_push($emails, 'elisheva.g@simplyct.co.il');
    return $emails;
}

//close receipt	
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
		$order = wc_get_order($order_id);
        $order->update_meta_data('priority_recipe_status', $msg);
		$order->save();
		//wp_mail('elisheva.g@simplyct.co.il','close receipt', $msg.' error code'.$res['code']);
    }
    curl_close($curl);

}




?>