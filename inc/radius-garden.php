<?php


add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $id_order = $data["orderId"];
    $order = new \WC_Order($id_order);
   // $data['REFERENCE'] = $data['BOOKNUM'];
  //  unset($data['BOOKNUM']);
    //$i = sizeof($data['ORDERITEMS_SUBFORM']) - 1;
    $i = 0;
    foreach ($order->get_items() as $item) {
        $item_meta = wc_get_order_item_meta($item->get_id(), '_options_sku');
        $json = explode(',', $item_meta);
        foreach ($json as $sku_optoin) {
            $str = explode(' ', $sku_optoin);
            if((int)$str[2]==0)
                continue;
            $data['ORDERITEMS_SUBFORM'][$i++] =
                [
                    'PARTNAME' => $str[0],
                    'TQUANT' => (int)$str[2],
                    'VPRICE' => (int)$str[1],
                    'DUEDATE' => date('Y-m-d'),
                    'DISCOUNT' => $str[3] // this is how I want to pull the discount if any
                ];
        }
    }
    return $data;
}
add_filter('simply_syncCustomer','simply_syncCustomer');
function simply_syncCustomer($data){
    unset($data['EDOCUMENTS']);
    return $data;
}

