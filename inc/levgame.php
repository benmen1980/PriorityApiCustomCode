<?php


//add_filter('simply_syncInventoryPriority_data', 'simply_syncInventoryPriority_func');
function simply_syncInventoryPriority_func($data) 
{
    $data['select'] .= ',NSFLAG';
    return $data;
}

// function simply_code_after_sync_inventory($product_id,$item) {
//     $manage_inventory = $item['NSFLAG'];

//     if ($manage_inventory !== 'Y') {
//         update_post_meta($product_id, '_backorders', 'yes');
//     }
// }

//add_action('wpcf7_mail_sent', 'mail_sent_sync_dialogue_func');

function mail_sent_sync_dialogue_func($contact_form) {

    
    // Example: Get the submitted form data
    $submission = WPCF7_Submission::get_instance();

    if ($submission) {
        $posted_data = $submission->get_posted_data();

        // Get the current user ID
        $user_id = get_current_user_id();

        // Perform your custom actions based on the form data and user ID
        if ($user_id) {
            $meta = get_user_meta($user_id);
            // if already assigned value it is stronger
            $priority_customer_number = get_user_meta($user_id, 'priority_customer_number', true);

            if (!empty($priority_customer_number)) {
                $request = $this->makeRequest('GET', 
                'CUSTOMERS(\''.$priority_customer_number.' \')', [], 
                $this->option('log_customers', true));

                if ($request['code'] == '200') {
                    $is_customer = json_decode($request['body']);
                    $priority_cust_from_priority = $priority_customer_number;
                }
            }
            if (empty($priority_customer_number)) {
                $priority_customer_number = $contact_form['PHONE'];
            }
        
            // if (!empty($priority_cust_from_wc)) {
            //     $priority_customer_number = $priority_cust_from_wc;
            // } 
            // if (empty($priority_cust_from_wc)) {

            // }

        }
    }
    $request = [
        'CUSTNAME' => $priority_customer_number,
        'CUSTDES' => empty($meta['first_name'][0]) ? $meta['nickname'][0] : $custdes,
        'EMAIL' => $user->data->user_email,
        'PHONE' => isset($meta['billing_phone']) ? $meta['billing_phone'][0] : '',
        'EDOCUMENTS' => 'Y',
        'NSFLAG' => 'Y',
    ];

    $text = [
        'TEXT' => $contact_form['TEXT'],
        'SIGNATURE' => true,
        'APPEND' => true,
    ];
    $request['INTERNALDIALOGTEXT'] = $text;

    $url_eddition = 'CUSTOMERS';
    $method = !empty($priority_cust_from_priority) ? 'PATCH' : 'POST';
    if ($method == 'PATCH') {
        $url_eddition = 'CUSTOMERS(\'' . $priority_customer_number . '\')';
        unset($request['CUSTNAME']);
    }

    // https://prioritydev.simplyct.co.il/odata/Priority/tabula.ini/demo/CUSTOMERS('015592611')/INTERNALDIALOGTEXT_SUBFORM

    $json_request = json_encode($request);
    $response = $this->makeRequest($method, $url_eddition, ['body' => $json_request], true);
    if ($method == 'POST' && $response['code'] == '201' || $method == 'PATCH' && $response['code'] == '200') {
        $data = json_decode($response['body']);
        $priority_customer_number = $data->CUSTNAME;
        update_user_meta($id, 'priority_customer_number', $priority_customer_number);
    }
     else {
        $this->sendEmailError(
            [$this->option('email_error_sync')],
            'Error Sync Customers',
            $response['body']
        );
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

    return $data;
}