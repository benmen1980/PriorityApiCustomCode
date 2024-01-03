<?php
add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $id_order = $data["orderId"];
    $order = new \WC_Order($id_order);
    $data['CDES'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    return $data;
}

add_filter('simply_sync_inventory_priority', 'simply_code_after_sync_inventory');
function simply_code_after_sync_inventory($item)
{
    $item['stock'] = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE'];
    return $item;
}
