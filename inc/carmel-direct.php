<?php
use PriorityWoocommerceAPI\WooAPI;
// search CUSTNAME by email or phone, input is array user_id or  order object
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
        $phone =  $order->get_billing_phone();
    }
    if($user_id) {
        if ($user = get_userdata($user_id)) {
            $meta = get_user_meta($user_id);
            $email = $user->data->user_email;
            $phone = isset($meta['billing_phone']) ? $meta['billing_phone'][0] : '';
        }
    }
    $tz = get_post_meta($order->get_id(), 'tz', true);
    //check if customer already exist in priority
    $data["select"] = 'VATNUM eq \'' . $tz . '\'';
    $url_addition = 'CUSTOMERS?$select=CUSTNAME&$filter=' . $data['select'] ;
    //$url_addition = 'CUSTOMERS?$filter=EMAIL eq \''.$email.'\' or PHONE eq \''.$phone.'\'';
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
add_filter('simply_syncCustomer', 'simply_syncCustomer_func');
function simply_syncCustomer_func($json)
{
    $id = $json["id"];
    $tz = get_user_meta($id, 'tz', true);
    $json["VATNUM"] = $tz;
    unset($json["CUSTNAME"]);
    $json["CUSTNAMEPATNAME"] = 'CE';
    $json["SPEC3"] = 'אתר כרמל';
    $json["SPEC11"] = 'כן';
    $json["CTYPECODE"] = "10";
    $json["NSFLAG"]="Y";
    return $json;

}

add_filter('simply_post_prospect', 'simply_post_prospect_func');
function simply_post_prospect_func($json)
{
    $id = $json['order_id'];
    $tz = get_post_meta($id, 'tz', true);
    $json["VATNUM"] = $tz;
    unset($json["CUSTNAME"]);
    $json["CUSTNAMEPATNAME"] = 'CE';
    $json["SPEC3"] = 'אתר כרמל';
    $json["SPEC11"] = 'כן';
    $json["CTYPECODE"] = "10";
    $json["NSFLAG"]="Y";
    return $json;
}
/*
 add_filter('simply_modify_customer_number', 'simply_modify_customer_number_func');
function simply_modify_customer_number_func($cust_data)
{

    $order = $cust_data[0];
    $this_cust_data = $cust_data[2];
    $tz = get_post_meta($order->get_id(), 'tz', true);
    //check if customer already exist in priority
    $data["select"] = 'VATNUM eq \'' . $tz . '\'';
    $url_addition = 'CUSTOMERS?$select=CUSTNAME&$filter=' . $data['select'] . '';
    $response = $this_cust_data->makeRequest('GET', $url_addition, []);
	$response_data = json_decode($response['body_raw'], true);
    if($response['code']== '200' && $response_data['value']>0)
    {
		$response_data['value'][0]->CUSTNAME;
        $data = $response_data['value'][0];
        $cust_data[1] = $data['CUSTNAME'];
    } else {
        if ($order->get_customer_id()) {
            $user = $order->get_user();
            $user_id = $user->get_id();
            update_user_meta($user_id, 'tz', $tz);
        }

    }
    return $cust_data;

}
 */