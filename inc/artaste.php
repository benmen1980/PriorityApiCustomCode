<?php
use PriorityWoocommerceAPI\WooAPI;

/**
* Change email for errors from the site manager
*
*/
add_filter('simplyct_sendEmail', 'simplyct_sendEmail_func');
function simplyct_sendEmail_func($emails)
{
    array_push($emails, 'margalit.t@simplyct.co.il');
    return $emails;
}

add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
	$order_id = $data['orderId'];
    $order = new \WC_Order($order_id);
	
    if($data['doctype']=='ORDERS') {
        // CURDATE = PDATE
        $data['PDATE'] = $data['CURDATE'];
        $data['REFERENCE']= $order->get_meta('_billing_delivery_timeset');
        $date_text = $order->get_meta('_billing_delivery_day');
        if(!empty($date_text)){
            $datetime = DateTime::createFromFormat('d/m/Y', $date_text);
            $data['EXPIRYDATE'] = $datetime->format('Y-m-d');
            $data['DETAILS'] = '#'.$data['BOOKNUM'];
            unset($data['BOOKNUM']);
        }

        unset($data['CURDATE']);
        //unset($data['CDES']);
        // ORDERSTEXT_SUBFORM replace with CPROFTEXT
        $data = WooAPI::instance()->change_key($data, 'ORDERSTEXT_SUBFORM', 'CPROFTEXT_SUBFORM');
        // CPROFCONT
        $data = WooAPI::instance()->change_key($data, 'ORDERSCONT_SUBFORM', 'CPROFCONT_SUBFORM');
        // ORDER ITEMS REPLACE CPROFITEMS
        $data = WooAPI::instance()->change_key($data, 'ORDERITEMS_SUBFORM', 'CPROFITEMS_SUBFORM');
        // replace cprofitems fields
        $items = [];
        foreach ($data['CPROFITEMS_SUBFORM'] as $item) {
            unset($item['DUEDATE']);
            $item['VPRICE'] = $item['VATPRICE'] / $item['TQUANT'];
            unset($item['VATPRICE']);
            $items[] = $item;
        }
        $data['CPROFITEMS_SUBFORM'] = $items;
        // PAYMENTDEF
        unset($data['PAYMENTDEF_SUBFORM']);
    }
    if($data['doctype']=='EINVOICES') {
        $id = $data['DETAILS'];
        $CPROFNUM = get_post_meta($id,'priority_order_number',true);
        $data['DETAILS'] = $CPROFNUM;
        unset($data['IVDATE']);
    
		//update name of company to CDES 
        unset($data['CDES']);
        $company_name = get_post_meta($order_id, 'billing_invoice_name', true);
        $data['CDES'] = !empty($company_name) ? $company_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    }
	
	if(isset($data['SHIPTO2_SUBFORM'])) {
		unset($data['SHIPTO2_SUBFORM']);
        $shipping_data = [
            'CELLPHONE' => $order->get_billing_phone(),
            'EMAIL' => $order->get_billing_email(),
            'ADDRESS' => $order->get_billing_address_1(),
            'ADDRESS' => $order->get_billing_address_1() . (!empty(get_post_meta($data['orderId'], '_billing_delivery_floor', true)) ? ' קומה ' . get_post_meta($data['orderId'], '_billing_delivery_floor', true) : '') . (!empty(get_post_meta($data['orderId'], '_billing_delivery_apartment', true)) ? ' דירה ' . get_post_meta($data['orderId'], '_billing_delivery_apartment', true) : ''),
            //'STATEA' => get_post_meta($data['orderId'], '_billing_delivery_city', true),
            'STATE' => $order->get_meta('_billing_delivery_city'),
            'ZIP' => $order->get_shipping_postcode(),
            //'ADDRESS2' => !empty(get_post_meta($data['orderId'], '_billing_delivery_floor', true)) ? get_post_meta($data['orderId'], '_billing_delivery_floor', true) : '',
            //'ADDRESS3' => !empty(get_post_meta($data['orderId'], '_billing_delivery_apartment', true)) ? get_post_meta($data['orderId'], '_billing_delivery_apartment', true) : '',
            'CUSTDES' => get_post_meta($data['orderId'], '_billing_another_person_delivery_first_name', true) . ' ' . get_post_meta($data['orderId'], '_billing_another_person_delivery_last_name', true),
            'PHONENUM' => get_post_meta($data['orderId'], '_billing_another_person_delivery_phone_1', true),
        ];

        if ($priority_version > 19.1) {
            $shipping_data['EMAIL'] = $order->get_billing_email();
            $shipping_data['CELLPHONE'] = $order->get_billing_phone();
        }

        $data['SHIPTO2_SUBFORM'] = $shipping_data;
    }
    return $data;
}

//Update address and city in offline/unknown customer
add_filter('simply_post_prospect', 'simply_post_prospect_func');
function simply_post_prospect_func($json) 
{
    $id = $json['order_id'];
    $order = new \WC_Order($id);

    unset($json['ADDRESS']);
    unset($json['STATEA']);

    $json['ADDRESS'] = $order->get_billing_address_1() . (!empty(get_post_meta($id, '_billing_delivery_floor', true)) ? ' קומה ' . get_post_meta($id, '_billing_delivery_floor', true) : '') . (!empty(get_post_meta($id, '_billing_delivery_apartment', true)) ? ' דירה ' . get_post_meta($id, '_billing_delivery_apartment', true) : '');
    //$json['STATEA'] = get_post_meta($id, '_billing_delivery_city', true);
    $json['STATEA'] = $order->get_meta('_billing_delivery_city');
    return $json;    
}

//Update address and city in connected client
add_filter('simply_syncCustomer', 'simply_syncCustomer_func');
function simply_syncCustomer_func($request) 
{
    $id = $request["id"];
    $order = new \WC_Order($id);

    unset($request['ADDRESS']);
    unset($request['STATEA']);

    $request['ADDRESS'] = $order->get_billing_address_1() . (!empty(get_post_meta($id, '_billing_delivery_floor', true)) ? ' קומה ' . get_post_meta($id, '_billing_delivery_floor', true) : '') . (!empty(get_post_meta($id, '_billing_delivery_apartment', true)) ? ' דירה ' . get_post_meta($id, '_billing_delivery_apartment', true) : '');
    //$request['STATEA'] = get_post_meta($id, '_billing_delivery_city', true);
    $request['STATEA'] = $order->get_meta('_billing_delivery_city');  
    return $request;   
}

// search CUSTNAME by email or vat num, input is array user_id or  order object
add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority');
function simply_search_customer_in_priority($data){
    $order = $data['order'];
    $user_id = $data['user_id'];
    if($order){
        $company_id =  get_post_meta($order->id, 'billing_invoice_id_type', true);
        $email =  strtolower($order->get_billing_email());
    }
    //Check if the customer already exists as a priority by the company's id
    if (!empty($company_id)) {
        $url_addition = 'CUSTOMERS?$filter=WTAXNUM eq \''.$company_id.'\'' ;
        $res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
        if($res['code']==200){
            $body =   json_decode($res['body']);
            $value = $body->value[0];
            if (isset($value) && !empty($value)) {
                $custname = $value->CUSTNAME;
            } else {
                $company_name = get_post_meta($order->id, 'billing_invoice_name', true);
                $request = [
                    'CUSTNAME' =>  $company_id,
                    'CUSTDES' => !empty($company_name) ? $company_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'EMAIL' => $order->get_billing_email(),
                    'ADDRESS' => $order->get_billing_address_1() . (!empty(get_post_meta($order->id, '_billing_delivery_floor', true)) ? ' קומה ' . get_post_meta($order->id, '_billing_delivery_floor', true) : '') . (!empty(get_post_meta($order->id, '_billing_delivery_apartment', true)) ? ' דירה ' . get_post_meta($order->id, '_billing_delivery_apartment', true) : ''),
                    //'STATEA' => get_post_meta($order->id, '_billing_delivery_city', true),
                    'STATEA' => $order->get_meta('_billing_delivery_city'),
                    'ZIP' => $order->get_shipping_postcode(),
                    'PHONE' => $order->get_billing_phone(),
                    'NSFLAG' => 'Y',
                    'WTAXNUM' => $company_id,
                ];
                $json_request = json_encode($request);
				$url_eddition = 'CUSTOMERS';
                $response =  WooAPI::instance()->makeRequest('POST', $url_eddition, ['body' => $json_request], true);
                if ($response['code'] == '201' ) {
                    $res_data = json_decode($response['body']);
					if (isset($res_data->CUSTNAME)) {
                        $custname = $res_data->CUSTNAME;
                        if($user_id) {
                            update_user_meta($user_id, 'priority_customer_number', $custname);
                        }
                    }
                } // set priority customer id
                else {
                    WooAPI::instance()->sendEmailError(
                        [ WooAPI::instance()->option('email_error_sync_customers_web')],
                        'Error Sync Business Customers ',
                        $response['body']
                    );
                }
            }
        } 
    } else {
        if($email) {
            if ($user = get_userdata($user_id)) {
                $email = strtolower($user->data->user_email);
            }
            // Check if the customer already exists in priority by email
            $url_addition = 'CUSTOMERS?$filter=EMAIL eq \''.$email.'\'' ;
            $res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
            if($res['code']==200){
                $body =   json_decode($res['body']);
                $value = $body->value[0];
                $custname =$value->CUSTNAME;
            } else{
                $custname = null;
            }
        } else{
            $custname = null;
        }
    }
    $data['CUSTNAME'] = $custname;
    return $data;
}

add_filter('check_order_is_payment', 'check_order_payment_method', 2);
function check_order_payment_method($order)
{

    $payment_method = $order->get_payment_method();
	$payment_method_title = $order->get_payment_method_title();

    if($payment_method === 'tranzila' || $payment_method_title === 'כרטיס אשראי'){
        return 'true';
    }
    else {
        return 'false';     
    }
    
}