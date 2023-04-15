<?php
add_filter('simply_ItemsAtrrVariation', 'simply_ItemsAtrrVariation_func');
function simply_ItemsAtrrVariation_func($item)
{
    $attributes['color'] = $item['SPEC14'];
    $attributes['size'] = $item['SPEC12'];
	$item['attributes']= $attributes;
    return $item;
}
use PriorityWoocommerceAPI\WooAPI;
add_filter('simply_modify_long_text', 'simply_modify_long_text_func');
function simply_modify_long_text_func($data)
{

    $response = WooAPI::instance()->makeRequest('GET', 'LOGPART(\'' . $data['sku'] . '\')?$select=PARTNAME&$expand=PARTTEXT_SUBFORM',
        [], true);
    $response_data = json_decode($response['body_raw'], true);

    if (isset($response_data['PARTTEXT_SUBFORM'])) {
        foreach ($response_data['PARTTEXT_SUBFORM'] as $text) {
            $data['text'] .= $text;
        }
    }
    return $data;
}
if(!function_exists('simply_set_ship_class')) {
    function simply_set_ship_class($product_id, $class_name)
    {
        // To get all the shipping classes
        $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));
        foreach ($shipping_classes as $shipping_class) {
            if ($class_name == $shipping_class->name) {
                // assign class to product
                $product = wc_get_product($product_id); // Get an instance of the WC_Product Object
                $product->set_shipping_class_id($shipping_class->term_id); // Set the shipping class ID
                $product->save(); // Save the product data to database
            }
        }
    }
}
add_action('simply_update_product_data',function($item){
    simply_set_ship_class($item['product_id'],$item['SPEC7']);
});
add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority');
function simply_search_customer_in_priority($data){
	$order = $data['order'];
	if(empty($order)){
		$data['CUSTNAME'] = null;
		return $data;
	}
	$user_id = $data['user_id'];
	if($order){
		$email =  $order->get_billing_email();
		//$phone =  $order->get_billing_phone();
	}
	if($user_id) {
		if ($user = get_userdata($user_id)) {
			$meta = get_user_meta($user_id);
			$email = $user->data->user_email;
			//$phone = isset($meta['billing_phone']) ? $meta['billing_phone'][0] : '';
		}
	}

	//check if customer already exist in priority
	$data["select"] = 'EMAIL eq \'' . $email . '\'';
	$url_addition = 'CUSTOMERS?$filter=EMAIL eq \''.$email.'\'';
	$res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
	if($res['code']==200){
		$body =   json_decode($res['body']);
		$value = $body->value[0];
		$custname =$value->CUSTNAME;
	}else{
		$custname = null;
	}
	$data['CUSTNAME'] = $custname;
	return $data;
}
add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
		unset($data['CDES']);
		return $data;
}