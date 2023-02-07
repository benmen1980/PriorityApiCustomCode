<?php
add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $id_order = $data["orderId"];
    $order = new \WC_Order($id_order);
    $data['CDES'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    return $data;
}
function simply_code_after_sync_inventory($product_id,$item)
{
	$stock = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE'];
	$item['stock'] = $stock;
	update_post_meta($product_id, '_stock', $stock);
}
