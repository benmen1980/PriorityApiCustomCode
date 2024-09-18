<?php
use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_syncInventoryPriority_data', 'simply_syncInventoryPriority_data_func');
function simply_syncInventoryPriority_data_func($data) 
{
    $data['select'] .= ',SPEC10';
    return $data;
}

//עדכון מלאי לפי מלאי זמין
add_filter('simply_sync_inventory_priority', 'simply_sync_inventory_priority_func');
function simply_sync_inventory_priority_func($item)
{
    $item['stock'] = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE'];
    return $item;
}

//prevent shop for product with spec10 =yes
function simply_code_after_sync_inventory($product_id,$item) {
    $manage_inventory = $item['SPEC10'];

    if ($manage_inventory == 'yes') {
        update_post_meta($product_id, '_backorders', 'no');
    }
    else{
        update_post_meta($product_id, '_backorders', 'yes');
    }

}

//add filter by udate for product with change in spec10, to be included in the inventory sync
add_filter('simply_syncInventoryPriority_filter_addition', 'simply_syncInventoryPriority_filter_addition_func');

function simply_syncInventoryPriority_filter_addition_func($url_addition)

{
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    //just for dev site
    //$url_addition.= ' and PARTNAME eq \'10028\' or PARTNAME eq \'10031\'';
    $url_addition= '('. $url_addition .rawurlencode(' or UDATE ge ' . $bod) . ')';
    return $url_addition;

}


add_action('wpcf7_mail_sent', 'mail_sent_sync_dialogue_func');

function mail_sent_sync_dialogue_func($contact_form) {

   
    // Example: Get the submitted form data
    $submission = \WPCF7_Submission::get_instance();
	    // Make sure submission is valid


    if ($submission) {
        $posted_data = $submission->get_posted_data();

        // Access specific fields by name and sanitize
        $name = isset($posted_data['your-name']) ? sanitize_text_field($posted_data['your-name']) : '';
        $email = isset($posted_data['your-email']) ? sanitize_email($posted_data['your-email']) : '';
        $phone = isset($posted_data['TEL-1']) ? sanitize_text_field($posted_data['TEL-1']) : '';
        $subject = isset($posted_data['your-subject']) ? sanitize_text_field($posted_data['your-subject']) : ''; //מקור הגעה
        //$time = isset($posted_data['your-time']) ? sanitize_text_field($posted_data['your-time']) : '';
        $message = isset($posted_data['your-message']) ? sanitize_textarea_field($posted_data['your-message']) : '';
        //$newsletter = isset($posted_data['newletter-confirmation']) ? 'Yes' : 'No';

        $json_request = [
            'CUSTNAME' => $phone,
            'CUSTDES' => $name,
            'EMAIL' => $email,
            'PHONE' => $phone,
            'SPEC1' => "API",
            'NSFLAG' => 'Y',
        ];
    
        $text = [
            'TEXT' => $message,
            'SIGNATURE' => true,
            'APPEND' => true,
        ];

        $json_request['INTERNALDIALOGTEXT_SUBFORM'] = $text;

		
		
        //check customer exist in priority by phone
        $request = WooAPI::instance()->makeRequest('GET', 
        'CUSTOMERS(\''.$phone.' \')', [], true);

        if ($request['status']) {
            if ($request['code'] == '200') {
                $is_customer = json_decode($request['body']);
                $priority_cust_from_priority = $phone;
            }
        }

        //if it exists, update method patch
        $method = !empty($priority_cust_from_priority) ? 'PATCH' : 'POST';
        $url_eddition = 'CUSTOMERS';
        if ($method == 'PATCH') {
            $url_eddition = 'CUSTOMERS(\'' . $phone . '\')';
            unset($json_request['CUSTNAME']);
        }
        $json_request = json_encode($json_request);
        $response = WooAPI::instance()->makeRequest($method, $url_eddition, ['body' => $json_request], true);
       
        if ($method == 'POST' && $response['code'] == '201' || $method == 'PATCH' && $response['code'] == '200') {
			return;
        }
        else{
			$headers = [ 'content-type: text/html'];
			wp_mail(get_bloginfo('admin_email'), 'Error Sync Customers form cf7', $response['body'], $headers);
			
//             WooAPI::instance()->sendEmailError(
//                 WooAPI::instance()->option('email_error_sync_customers_web'),
//                 'Error Sync Customers',
//                 $response['body']
//             );
        }
		
    }




   

}
//get tranzila payment details- different from standart
add_filter('simply_request_data', 'simply_func');
function simply_func($data){

    $order_id = $data['orderId'];
    $order = new \WC_Order($order_id);

    $validmonth = !empty(get_post_meta($order->get_id(), 'cc_expmonth', true)) ? get_post_meta($order->get_id(), 'cc_expmonth', true) . '/' . get_post_meta($order->get_id(), 'cc_expyear', true) : '';
    $confnum = $order->get_meta('cc_company_approval_num');
    $ccuid = $order->get_meta('cc_order_token');

    unset($data['PAYMENTDEF_SUBFORM']);
    $data['PAYMENTDEF_SUBFORM']['QPRICE'] = floatval($order->get_total());
    $data['PAYMENTDEF_SUBFORM']['PAYMENTCODE'] = '10';
    $data['PAYMENTDEF_SUBFORM']['VALIDMONTH'] = $validmonth;
    $data['PAYMENTDEF_SUBFORM']['CONFNUM'] = $confnum;
    $data['PAYMENTDEF_SUBFORM']['CCUID'] = $ccuid;
	
	//change priority status for canceled order
    // $order_status = $order->get_status();
    // if($order_status == 'cancelled'){
    //     $data['ORDSTATUSDES'] = "מבוטלת באתר";
    // }

    return $data;
}

//get tranzila payment details- different from standart for receipt
add_filter('simply_request_data_receipt', 'simply_func_receipt');
function simply_func_receipt($data){

    $order_id = $data['BOOKNUM'];
    $order = new \WC_Order($order_id);

    $validmonth = !empty(get_post_meta($order->get_id(), 'cc_expmonth', true)) ? get_post_meta($order->get_id(), 'cc_expmonth', true) . '/' . get_post_meta($order->get_id(), 'cc_expyear', true) : '';
    $confnum = $order->get_meta('cc_company_approval_num');
    $ccuid = $order->get_meta('cc_order_token');

    unset($data['TPAYMENT2_SUBFORM']);
    $data['TPAYMENT2_SUBFORM'][0]['QPRICE'] = floatval($order->get_total());
    $data['TPAYMENT2_SUBFORM'][0]['PAYMENTCODE'] = '10';
    $data['TPAYMENT2_SUBFORM'][0]['VALIDMONTH'] = $validmonth;
    $data['TPAYMENT2_SUBFORM'][0]['CONFNUM'] = $confnum;
    $data['TPAYMENT2_SUBFORM'][0]['CCUID'] = $ccuid;
    $data['TPAYMENT2_SUBFORM'][0]['PAYDATE'] = date('Y-m-d');
    return $data;
}