<?php
use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_syncInventoryPriority_filter_addition', 'simply_syncInventoryPriority_filter_addition_func');
function simply_syncInventoryPriority_filter_addition_func($url_addition)
{
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);

    $url_addition= '('. $url_addition .rawurlencode(' or UDATE ge ' . $bod) . ') and SPEC1 eq \'Y\' ';

    return $url_addition;
}

// search CUSTNAME in priority by phone
add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority_func');
function simply_search_customer_in_priority_func($data){
    $order = $data['order'];
    if($order)
        $phone =  $order->get_billing_phone();

    $user_id = $data['user_id'];
    if (empty($phone) && $user = get_userdata($user_id)) 
        $phone = get_user_meta($user_id, 'billing_phone', true);

    $custname = null;

    $option_name = 'custom_customer_counter';
    // Retrieve the last number or start from 0
    $last_number = get_option($option_name, 0);
    $next_number = $last_number + 1;
    $formatted_number = str_pad($next_number, 3, '0', STR_PAD_LEFT); // Format the code with leading zeros

    //Check if the customer already exists as a priority by the phone
    if($phone) {
        $url_addition = 'CUSTOMERS?$filter=PHONE eq \''.$phone.'\'' ;
        $res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
        if($res['code'] == 200){
            $body =   json_decode($res['body']);
            $value = $body->value[0];
            if (isset($value) && !empty($value)) {
                $custname = $value->CUSTNAME;
                $priority_cust_from_wc = $custname;
            } else {
                $custname = 'WEB-' . (string)$formatted_number;
                update_option($option_name, $next_number); //update in DB the new value
            }
        } else {
            $custname = 'WEB-' . (string)$formatted_number;
            update_option($option_name, $next_number); //update in DB the new value
        }

        $custdes = !empty($order->get_billing_company()) ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $request = [
            'CUSTNAME' => $custname,
            'CUSTDES' => $custdes,
            'EMAIL' => $order->get_billing_email(),
            'ADDRESS' => $order->get_billing_address_1(),
            'ADDRESS2' => $order->get_billing_address_2(),
            'ADDRESS3' => $order->get_meta('_billing_apartment'),
            'FAX' => $order->get_meta('_billing_code'),
            'STATEA' => $order->get_billing_city(),
            'ZIP' => $order->get_shipping_postcode(),
            'PHONE' => $order->get_billing_phone(),
            'NSFLAG' => 'Y',
            'EDOCUMENTS' => 'Y',
        ];

        $personal_data = [
            'NAME' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'EMAIL' => $order->get_billing_email(),
            'CELLPHONE' => $order->get_billing_phone(),
        ];

        $request['CUSTPERSONNEL_SUBFORM'][] = $personal_data;

        $method = !empty($priority_cust_from_wc) ? 'PATCH' : 'POST';
        $url_eddition = 'CUSTOMERS';
        if ($method == 'PATCH') {
            $url_eddition = 'CUSTOMERS(\'' . $custname . '\')';
            unset($request['CUSTNAME']);
        }

        $json_request = json_encode($request);
        $response = WooAPI::instance()->makeRequest($method, $url_eddition, ['body' => $json_request], true);
        if ($method == 'POST' && $response['code'] == '201' || $method == 'PATCH' && $response['code'] == '200') {
            $res = json_decode($response['body']);
            $priority_customer_number = $res->CUSTNAME;
            update_user_meta($id, 'priority_customer_number', $priority_customer_number);
        } 
        else {
            WooAPI::instance()->sendEmailError(
                [WooAPI::instance()->option('email_error_sync_customers_web')],
                'Error Sync Customers',
                $response['body']
            );
        }
    } 

    
    $data['CUSTNAME'] = $custname;
    return $data;
}

add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    $ord_id = $data["orderId"];
    $order = new \WC_Order($ord_id);
    if($data['doctype'] === 'ORDERS') {
        if(isset($data['SHIPTO2_SUBFORM'])) {

            $data['SHIPTO2_SUBFORM']['ADDRESS2'] = (!empty($order->get_shipping_address_2()) ? $order->get_shipping_address_2() : $order->get_billing_address_2());
            $data['SHIPTO2_SUBFORM']['ADDRESS3'] = $order->get_meta('_billing_apartment');
            $data['SHIPTO2_SUBFORM']['ADDRESSA'] = $order->get_meta('_billing_code');
        }
    }
    if($data['doctype'] === 'EINVOICES') {
        $data['NAME'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    }
    return $data;
}

add_filter('simply_modify_orderitem', 'custom_modify_orderitem');
function custom_modify_orderitem($args) {
    $data = $args['data'];
    $item = $args['item'];

    $product = $item->get_product();
	if ( $product && $product->is_type('variation') ) {
		$sku = $product->get_sku();
        if (substr($sku, 0, 2) === "MP") { 
            $attributes = $product->get_attributes();

            foreach ( $attributes as $taxonomy => $value ) {
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['REMARK1'] = $value;
            }
        }
    }
    // Return the modified data
    return ['data' => $data, 'item' => $item];
}

//close over the counter invoice
add_filter('simply_after_post_otc', 'simply_after_post_otc_func');
function simply_after_post_otc_func($array)
{
    $otc_number = $array["IVNUM"]; 
    $order_id = $array["order_id"];
    
    // update_receipt_status($otc_number);
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

    $data['IVNUM'] = $otc_number;
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
            CURLOPT_URL => 'http://prinodehub1-env.eba-gdu3xtku.us-west-2.elasticbeanstalk.com/closeEinvoices',
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
            $order->update_meta_data('priority_invoice_number', $response_data['ivnum']);
            $order->update_meta_data('priority_invoice_status', 'סגורה');
			$order->save(); 
        }
    }
    else if ( $res['http_code'] == 200 ) {
        if (isset($response_data['message'])) {
            $msg = $response_data['message'];
        } else {
            $msg = "No error message found.";
        }
        $order = wc_get_order($order_id);
        $order->update_meta_data('priority_invoice_status', $msg);
        $order->save(); 
    }
    curl_close($curl);

}