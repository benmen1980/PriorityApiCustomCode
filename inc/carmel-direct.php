<?php
use PriorityWoocommerceAPI\WooAPI;
add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    // First function logic
    unset($data['CDES']);

    // Second function logic
    if ($data['doctype'] === 'ORDERS') {

        if (isset($data['PAYMENTDEF_SUBFORM']['mult'])) {
            switch ($data['PAYMENTDEF_SUBFORM']['PAYMENTCODE']) {
                case '9':
                    $data['PAYMENTDEF_SUBFORM']['mult']= '55';
                    break; // Add a semicolon at the end of the break statement
            }
        }
    }
    print_r ($data);
    print_r ('<br/>');
    print_r ($data['PAYMENTDEF_SUBFORM']['PAYMENTCODE']);
    
    // Return the modified data
    return $data;
}
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
	$tz = get_post_meta($order->get_id(), 'id_number', true);
	if(empty($tz)){
		$data['CUSTNAME'] = null;
		return $data;
	}
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
    if (!empty($json["CUSTNAME"])) {
        $json["CUSTNAMEPATNAME"] = 'CE';
    }
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

//sync inventory from warehouse in priority to acf fields in the product
add_action('sync_inventory_to_warehouse_in_product_cron_hook', 'sync_inventory_to_warehouse_in_product');
// Schedule the task to run daily at midnight
if (!wp_next_scheduled('sync_inventory_to_warehouse_in_product_cron_hook')) {
    $res = wp_schedule_event(time(), 'daily', 'sync_inventory_to_warehouse_in_product_cron_hook');
}

function sync_inventory_to_warehouse_in_product() {
    // get the items simply by time stamp of today
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = '('. rawurlencode('WARHSTRANSDATE ge ' . $bod . ' or PURTRANSDATE ge ' . $bod . ' or SALETRANSDATE ge ' . $bod) . ')';
    
    $response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select=PARTNAME&$filter='.$url_addition.' and INVFLAG eq \'Y\' &$expand=PARTBALANCE_SUBFORM',
    [],  
    WooAPI::instance()->option('log_inventory_priority', false));

    // check response status 
    if ($response['status']) {
        $data = json_decode($response['body_raw'], true);

        foreach ($data['value'] as $item) {
            $args = array(
                'post_type' => array('product', 'product_variation'),
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'value' => $item['PARTNAME']
                    )
                )
            );
            $my_query = new \WP_Query($args);
            if ($my_query->have_posts()) {
                while ($my_query->have_posts()) {
                    $my_query->the_post();
                    $product_id = get_the_ID();
                }
            } else {
                $product_id = 0;
            }

            if (!$product_id == 0) {
                foreach($item['PARTBALANCE_SUBFORM'] as $warehouse) {
                    $warehouse_name = $warehouse['WARHSNAME'];
                    $quantity = $warehouse['TBALANCE'];

                    switch ( $warehouse_name ) {
                        case 'GEZR':
                            update_field('gezer',  $quantity, $product_id);
                            break;
                        case 'HIFA':
                            update_field('haifa',  $quantity, $product_id);
                            break;
                        case 'HERM':
                            update_field('hertzelia',  $quantity, $product_id);
                            break;
                        case 'RISH':
                            update_field('rishon',  $quantity, $product_id);
                            break;
                        case 'BSHV':
                            update_field('beersheva',  $quantity, $product_id);
                            break;
                    }

                }
            }
        }

    } else {
        WooAPI::instance()->sendEmailError(
            WooAPI::instance()->option('email_error_sync_inventory_warehouse_in_product'),
            'Error Sync Inventory warehouse in product',
            $response['body']
        );
    }
}

// add_filter('simply_request_data','manipulate_order');
// function manipulate_order($data){
// 	unset($data['CDES']);
// 	return $data;
// }
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