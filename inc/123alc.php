<?php
use PriorityWoocommerceAPI\WooAPI;

/**
* Change email for errors from the site manager
*
*/
add_filter('simplyct_sendEmail', 'simplyct_sendEmail_func');
function simplyct_sendEmail_func($emails)
{
    array_push($emails, 'margalit.t@simplyct.co.il');
    return $emails;
}

/**
 * sync inventory from priority
 */
function syncInventoryPriority()
{

    // get the items simply by time stamp of today
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = '('. rawurlencode('WARHSTRANSDATE ge ' . $bod . ' or PURTRANSDATE ge ' . $bod . ' or SALETRANSDATE ge ' . $bod . ' or UDATE ge ' . $bod) . ')';

    $data['select'] = 'PARTNAME,STATDES';
    // $data['expand'] = '$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM';
    
    $response = WooAPI::instance()->makeRequest('GET', 
    'LOGPART?$select='.$data['select'].'&$filter='.$url_addition.' and SHOWINWEB eq \'Y\' ', [], 
    WooAPI::instance()->option('log_inventory_priority', true));
    
    // check response status 
    if ($response['status']) {
        $data = json_decode($response['body_raw'], true);
        foreach ($data['value'] as $item) {
            // if product exsits, update
            $args = array(
                'post_type' => array('product', 'product_variation'),
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'value' => $item['PARTNAME']
                    )
                )
            );
            $my_query = new \WP_Query($args);
            if ($my_query->have_posts()) {
                while ($my_query->have_posts()) {
                    $my_query->the_post();
                    $product_id = get_the_ID();
                }
            } else {
                $product_id = 0;
            }
            //if ($id = wc_get_product_id_by_sku($item['PARTNAME'])) {
            if (!$product_id == 0) {
                // get the in-stock by statdes
                $in_stock = $item['STATDES'];
                // set stock status
                if ($in_stock == 'פעיל') {
                    $stock_status = 'instock';
                } else {
                    $stock_status = 'outofstock';
                }

                $product = wc_get_product($product_id);
                if ($product->post_type == 'product_variation') {
                    $var = new \WC_Product_Variation($product_id);
                    $var->set_stock_status($stock_status);
                    $var->save();
                }
                if ($product->post_type == 'product') {
                    $product->set_stock_status($stock_status);
                    $product->set_manage_stock(false);
                    $product->save();
                }
                
            }
        }
        // add timestamp
        WooAPI::instance()->updateOption('inventory_priority_update', time());
    } else {
        exit(json_encode(['status' => 0, 'msg' => 'Error Sync Inventory Priority']));
        $subj = 'check sync Inventory';
        wp_mail( 'margalit.t@simplyct.co.il', $subj, implode(" ",$response) );
        /**
         * t149
         */
        WooAPI::instance()->sendEmailError(
            WooAPI::instance()->option('email_error_sync_inventory_priority'),
            'Error Sync Inventory Priority',
            $response['body']
        );
    }
}


add_action('sync_inventory_priority_hook', 'syncInventoryPriority');

// // Clear any existing scheduled events for 'sync_cprof'
// wp_clear_scheduled_hook('sync_inventory_priority_hook');

if (!wp_next_scheduled('sync_inventory_priority_hook')) {

    $res = wp_schedule_event(time(), 'hourly', 'sync_inventory_priority_hook');

}

add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $tomorrow = strtotime('tomorrow');
    $data['DUEDATE'] = date('Y-m-d', $tomorrow);
    $data['ORDSTATUSDES'] = "טיוטא";
    return $data;
}

add_filter('simply_modify_orderitem', 'my_custom_orderitem_modifier');
function my_custom_orderitem_modifier($args) {
    // Access the data and item from the filter
    $data = $args['data'];
    $item = $args['item'];

    $product = $item->get_product();
    if ($product) {
        $product_sku = $product->get_sku();

        $response = WooAPI::instance()->makeRequest('GET', 
        'LOGPART?&$filter=PARTNAME eq \'' . $product_sku . '\'', [], 
        true);

        // check response status
        if ($response['status']) {
            $data_res = json_decode($response['body_raw'], true);
            foreach ($data_res['value'] as $item_p) {
                if ( $item_p['OFER_DEPOSIT'] == "Y" ) {
                    $deposit_partname = $item_p['OFER_PARTNAME'];
                    $deposit_tquant = $item_p['OFER_DEPOSIT_TQUANT'];
                    $deposit_cost = $deposit_tquant * 0.3;
                }
            }
        }
        $price = $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VATPRICE'];
        $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VATPRICE'] = $price - $deposit_cost;
        // $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VATPRICE'] = round($price - $deposit_cost);
        
        $tomorrow = strtotime('tomorrow');
        $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['DUEDATE'] =  date('Y-m-d', $tomorrow);
    }
    // Return the modified data
    return ['data' => $data, 'item' => $item];
}