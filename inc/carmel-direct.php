<?php
use PriorityWoocommerceAPI\WooAPI;
// search CUSTNAME by email or phone, input is array user_id or  order object
add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority');
function simply_search_customer_in_priority($data){
    $order = $data['order'];
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
    $url_addition = 'CUSTOMERS?$filter=EMAIL eq \''.$email.'\' or PHONE eq \''.$phone.'\'';
    $res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
    if($res['code']==200){
        $body =   json_decode($res['body']);
        $value = $body->value[0];
        $custname =$value->CUSTNAME;
    }else{
        $custname = null;
    }
    return $custname;
}
