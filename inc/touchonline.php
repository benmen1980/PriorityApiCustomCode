<?php
add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    $order_id = $data['BOOKNUM'];
    $order = new \WC_Order($order_id);
    //$cashback = get_post_meta($order_id,'_wallet_cashback',true);
    $cashback = get_order_partial_payment_amount($order_id); // update 14.07.2021
    if ($cashback > 0) {
        $data['ORDERITEMS_SUBFORM'][] = [
            'BARCODE' => 'CB1234', // change to other item
            'VATPRICE' => -1 * $cashback,
            'TQUANT' => -1,

        ];
    }
    return $data;
}

add_filter('simply_set_priority_sku_field', 'simply_set_priority_sku_field_func');
function simply_set_priority_sku_field_func($fieldname)
{
    $fieldname = "BARCODE";
    return $fieldname;
}

add_filter('simply_syncCustomer', 'simply_syncCustomer_func');
function simply_syncCustomer_func($request)
{
    unset($request['EDOCUMENTS']);
}

?>
