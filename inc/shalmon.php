<?php
add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $id_order = $data["orderId"];
    $order = new \WC_Order($id_order);
    $data['CDES'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    return $data;
}
