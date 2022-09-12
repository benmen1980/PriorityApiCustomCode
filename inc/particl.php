<?php
//SimplyCT.co.il
use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $id = $data["orderId"];
    $order = new \WC_Order($id);
    $warehouseId = get_post_meta($id, 'warehouseId', true);
    if (!empty($warehouseId)) {
        switch ($warehouseId) {
            case "275357";
                $warehousename = "Ful";
                break;
            case "277427";
                $warehousename = "Gre";
                break;
            case "393335";
                $warehousename = "SbTx";
                break;
        }
        $data['WARHSNAME'] = $warehousename;
    }
    if (get_post_meta($order->get_id(), '_billing_country', true) != 'IL') {
        if ($data['doctype'] == 'ORDERS') {
            $i = 0;
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if ($product) {
                    $data['ORDERITEMS_SUBFORM'][$i++]['VATPRICE'] = (float)$item->get_subtotal();
                }

            }
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM'])] = [
                WooAPI::instance()->get_sku_prioirty_dest_field() => '998',
                'TQUANT' => (int)1,
                'DUEDATE' => date('Y-m-d'),
            ];
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VATPRICE'] = (float)$order->get_cart_tax();


        } else if ($data['doctype'] == 'AINVOICES') {
            $i = 0;
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if ($product) {
                    $data['AINVOICEITEMS_SUBFORM'][$i++]['TOTPRICE'] = (float)$item->get_subtotal();
                }

            }
            $data['AINVOICEITEMS_SUBFORM'][sizeof($data['AINVOICEITEMS_SUBFORM'])] = [
                WooAPI::instance()->get_sku_prioirty_dest_field() => '998',
                'TQUANT' => (int)1,
            ];
            $data['AINVOICEITEMS_SUBFORM'][sizeof($data['AINVOICEITEMS_SUBFORM']) - 1]['TOTPRICE'] = (float)$order->get_cart_tax();
        }
    }
    return $data;
}

add_filter('woocommerce_gateway_icon', 'custom_gateway_icon', 10, 2);
add_filter('simply_modify_customer_number', 'simply_modify_customer_number_func');
function simply_modify_customer_number_func($cust_data)
{
    $cust_user = '';
    $order = $cust_data['order'];
    $currency = get_post_meta($order->get_id(), '_order_currency', true);
    switch ($currency) {
        case  "USD";
            $cust_user = "77";
            break;
        case  "EUR";
            $cust_user = "78";
            break;
        case  "AUD";
            $cust_user = "79";
            break;
        case  "CAD";
            $cust_user = "292";
            break;
        case  "GBP";
            $cust_user = "291";
            break;
        case  "ILS";
            $cust_user = "76";
            break;
    }
    $cust_data['CUSTNAME'] = $cust_user;
    return $cust_data;
}

add_filter('simply_set_priority_sku_field', 'simply_set_priority_sku_field_func');
function simply_set_priority_sku_field_func($fieldname)
{
    $fieldname = 'BARCODE';
    return $fieldname;
}
