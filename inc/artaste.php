<?php
use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    if($data['doctype']=='ORDERS') {
        // CURDATE = PDATE
        $data['PDATE'] = $data['CURDATE'];

        $order_id = $data['orderId'];
        $order = new \WC_Order($order_id);
        $data['REFERENCE']= $order->get_meta('טווח שעות');
        $date_text = $order->get_meta('תאריך');
        $datetime = DateTime::createFromFormat('d/m/y', $date_text);
        $data['EXPIRYDATE'] = $datetime->format('Y-m-d');
        $data['DETAILS'] = '#'.$data['DETAILS'];
        unset($data['CURDATE']);
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
    }
    return $data;
}

// search CUSTNAME by email or vat num, input is array user_id or  order object
add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority');
function simply_search_customer_in_priority($data){
    $order = $data['order'];
    $user_id = $data['user_id'];
    if($order){
        $email =  $order->get_billing_email();
    }
    if($user_id) {
        if ($user = get_userdata($user_id)) {
            $email = $user->data->user_email;
        }
    }
    //check if customer already exist in priority
    $url_addition = 'CUSTOMERS?$filter=EMAIL eq \''.$email.'\'' ;
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
