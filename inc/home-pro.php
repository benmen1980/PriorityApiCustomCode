<?php
use PriorityWoocommerceAPI\WooAPI;
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
    $data['SHIPTO2_SUBFORM']['CUSTDES'] = $data['CDES'];
    $data['WARHSNAME'] = '90';
    $data['STCODE'] = '107';
    return $data;
}
add_action('HomeProSyncPricePriority', 'HomeProSyncPricePriority');

function HomeProSyncPricePriority()
{
    $product_price_list = '3'; // need to change to 199
    $response = WooAPI::instance()->makeRequest('GET', 'PRICELIST?$select=PLNAME&$filter=PLNAME eq \'' . $product_price_list . '\'&$expand=PARTPRICE2_SUBFORM'
        , [], WooAPI::instance()->option('log_items_priority', true));
    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);
        foreach ($response_data['value'][0]['PARTPRICE2_SUBFORM'] as $item) {
            // if product exsits, update price
            if ($item['PARTNAME'] == '0101') {
                $foo = 'ppp';
            }
            $product = new WC_Product(wc_get_product_id_by_sku($item['PARTNAME']));
            // if product variation skip
            if ($product) {
                $pri_price = $item['VATPRICE'];
                $product->set_sale_price($pri_price);
                $product->save();
            }
        }
    }
}