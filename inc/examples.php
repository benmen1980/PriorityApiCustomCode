<?php
//add_filter('simply_request_data','manipulate_data2');
use PriorityWoocommerceAPI\WooAPI;

function manipulate_data2($data){
    $items = [];
    foreach($data['ORDERITEMS_SUBFORM'] as $item ){
        if($item['PARTNAME']==''){
            $item['PARTNAME'] =  '000';
        }
        $items[] = $item;
    }
    $data['ORDERITEMS_SUBFORM'] = $items;
    return $data;
}
// search CUSTNAME by email or phone, input is array user_id or  order object
add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority');
function simply_search_customer_in_priority($order){
    $url_addition = 'CUSTOMERS?$filter=CUSTNAME eq \'050654848797\'';
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
