<?php
//SimplyCT.co.il
add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $id = $data["orderId"];
    $order = new \WC_Order($id);
    if (get_post_meta($order->get_id(), '_billing_country', true) != 'IL') {
        $data['998'] = $order->get_cart_tax();
        $i = 0;
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if ($product) {
                $data['ORDERITEMS_SUBFORM'][$i++]['VATPRICE'] = (float)$item->get_subtotal();
            }

        }
    }
    return $data;
}

add_filter('woocommerce_gateway_icon', 'custom_gateway_icon', 10, 2);
add_filter('simply_modify_customer_number', 'simply_modify_customer_number_func');
function simply_modify_customer_number_func($cust_data)
{
    $cust_user = '';
    $order = $cust_data[0];
    $currency = get_post_meta($order->get_id(), '_order_currency', true);
    switch ($currency) {
        case  'USD';
            $cust_user = 77;
            break;
        case  'EUR';
            $cust_user = 78;
            break;
        case  'AUD';
            $cust_user = 79;
            break;
        case  'CAD';
            $cust_user = 292;
            break;
        case  'GBP';
            $cust_user = 291;
            break;
        case  'ILS';
            $cust_user = 76;
            break;
    }
    $cust_data[1] = $cust_user;
    return $cust_data;
}

add_filter('simply_set_priority_sku_field', 'simply_set_priority_sku_field_func');
function simply_set_priority_sku_field_func($fieldname)
{
    $fieldname = 'BARCODE';
    return $fieldname;
}
