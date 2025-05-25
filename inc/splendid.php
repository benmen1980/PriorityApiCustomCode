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
        $phone =  $order->billing_phone();

    $user_id = $data['user_id'];
    if ($user = get_userdata($user_id)) 
        $phone = $user->data->user_phone;

    $custname = null;

    //Check if the customer already exists as a priority by the phone
    if($phone) {
        $url_addition = 'CUSTOMERS?$filter=PHONE eq \''.$phone.'\'' ;
        $res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
        if($res['code'] == 200){
            $body =   json_decode($res['body']);
            $value = $body->value[0];
            $custname =$value->CUSTNAME;
        } 
    } 
    
    $data['CUSTNAME'] = $custname;
    return $data;
}

add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    if($data['doctype'] === 'ORDERS') {
        if(isset($data['SHIPTO2_SUBFORM'])) {
            $ord_id = $data["orderId"];
            $order = new \WC_Order($ord_id);
            $data['SHIPTO2_SUBFORM']['ADDRESS2'] = $order->get_billing_address_2();
            $data['SHIPTO2_SUBFORM']['ADDRESS3'] = $order->get_billing_apartment();
            $data['SHIPTO2_SUBFORM']['ADDRESSA'] = $order->get_billing_code();
        }
    }
    return $data;
}