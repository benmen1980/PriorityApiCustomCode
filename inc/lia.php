<?php
add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $order_id = $data['orderId'];
    $order = new \WC_Order($order_id);
    $user_id = $order->get_user_id();
    $order_user = get_userdata($user_id);
    $boxit_name_of_point = get_post_meta($order_id, 'wcbso_name_of_point', true);
    $boxit_address_of_point = get_post_meta($order_id, 'wcbso_address_of_point', true);
    $boxit_id_of_point = get_post_meta($order_id, 'wcbso_id_of_point', true);
    $address = (!empty($boxit_name_of_point) ? $boxit_name_of_point . ', ' . $boxit_address_of_point : $order->get_shipping_address_1());
    $address2 = (!empty($boxit_id_of_point) ? ' ' : $order->get_shipping_address_2());
    $phone = (!empty($boxit_id_of_point) ? $boxit_id_of_point : $order->get_meta('_shipping_phone'));
    $city = (!empty($boxit_id_of_point) ? ' ' : $order->get_shipping_city());
    $shipping_data = [
        'NAME' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'CUSTDES' => $order_user->user_firstname . ' ' . $order_user->user_lastname,
        'PHONENUM' => $phone,
        'EMAIL' => $order->get_meta('_shipping_email'),
        'CELLPHONE' => get_post_meta($order->get_id(), '_shipping_phone', true) ?? $order->get_billing_phone(),
        'ADDRESS' => $address,
        'ADDRESS2' => $address2,
        'STATE' => $city,
        'ZIP' => $order->get_shipping_postcode(),
    ];
    $data['SHIPTO2_SUBFORM'] = $shipping_data;
    $data['CASHNAME'] = '102';
    return $data;
}

add_filter('simply_sync_inventory_priority', 'simply_sync_inventory_priority_func');
function simply_sync_inventory_priority_func($item)
{
    $item['stock'] = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE'] - 2;
    return $item;
}
