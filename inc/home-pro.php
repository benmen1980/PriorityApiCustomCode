<?php
add_filter('simply_syncCustomer','simply_syncCustomer');
function simply_syncCustomer($data){
unset($data['EDOCUMENTS']);
return $data;
}
add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $id_order = $data["orderId"];
    $order = new \WC_Order($id_order);
    $data['REFERENCE'] = $data['BOOKNUM'];
    unset($data['BOOKNUM']);
    return $data;
}