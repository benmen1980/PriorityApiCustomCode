<?php

use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_modify_orderitem', 'my_custom_orderitem_modifier');
function my_custom_orderitem_modifier($args) {
    // Access the data and item from the filter
    $data = $args['data'];
    $item = $args['item'];

    $product = $item->get_product();
    $line_before_discount = (float)round($item->get_subtotal());
    $line_tax = (float)$item->get_subtotal_tax();
    $data['TTS_AIRCONITEMSO_SUBFORM'][] = [
        'PARTNAME' => $product->get_sku(),
        'TQUANT' => (int)$item->get_quantity(),
        'PRICE' => ($line_before_discount + $line_tax) / (int)$item->get_quantity(),
    ];

    // Return the modified data
    return ['data' => $data, 'item' => $item];
}

add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    if (isset($data['ORDERITEMS_SUBFORM'])) {
        unset($data['ORDERITEMS_SUBFORM']);
    }
    $data['TYPECODE'] = 'WEB';
	$data['PAYMENTDEF_SUBFORM']['PAYMENTCODE'] = '121';
		
    $order_id = $data['orderId'];
    $order = new \WC_Order($order_id);
    $order_cc_meta = $order->get_meta('_transaction_data');
    $confnum = $order_cc_meta['DebitApproveNumber'];
    $data['PAYMENTDEF_SUBFORM']['CONFNUM'] = $confnum;

    // Return the data
    return $data;
}

add_filter('simply_request_data_receipt', 'simply_request_data_receipt_func');
function simply_request_data_receipt_func($data)
{
    $data['TPAYMENT2_SUBFORM'][0]['PAYMENTCODE'] = '121';
	$data['TPAYMENT2_SUBFORM'][0]['SHVA_TERMINALNAME'] = '6';
	
	$order_id = $data['orderId'];
    $order = new \WC_Order($order_id);
    $order_cc_meta = $order->get_meta('_transaction_data');
    $confnum = $order_cc_meta['DebitApproveNumber'];
	$data['TPAYMENT2_SUBFORM'][0]['CONFNUM'] = $confnum;

    // Return the data
    return $data;
}

add_filter('simply_post_prospect', 'simply_syncProspect_func');
function simply_syncProspect_func($json_request)
{
 	$order_id = $json_request['order_id'];
    $order = new \WC_Order($order_id);

    //turning a temporary customer into an active one
    unset($json_request['CUSTNAME']);
    $json_request['CTYPECODE'] = 'WC';
    $json_request['CTYPE2CODE'] = 'WE'; 
    $json_request['OWNERLOGIN'] = 'apiuser';
    $json_request['STATDES'] = 'זמני';

    $json_request['FSPS_EMAIL'] = $order->get_billing_email();
    $billing_id = $order->get_meta('_biling_id');
    $json_request['WTAXNUM'] = $billing_id;

    return $json_request;

}

//define select field for sync item
add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
function simply_syncItemsPriority_data_func($data)
{
    $data['expand'] .= ',INTERNALDIALOGTEXT_SUBFORM';
	$data['select'] .= ',SAPL_COOLING,SAPL_ENERGY_RATING,SAPL_HEATING,SAPL_HORSEPOWER';
	return $data;
}

add_action('simply_update_product_data', function($item){
    $product_id = $item['product_id'];
    $horsepower = $item['SAPL_HORSEPOWER'];
    $cooling = $item['SAPL_COOLING'];
    $heating = $item['SAPL_HEATING'];
    $energy_rating = $item['SAPL_ENERGY_RATING'];
	
	$short_text = '';
    if ( isset( $item['INTERNALDIALOGTEXT_SUBFORM'] ) ) {
        foreach ( $item['INTERNALDIALOGTEXT_SUBFORM'] as $text ) {
            $clean_text = preg_replace('/<style>.*?<\/style>/s', '', $text);
            $short_text .= ' ' . html_entity_decode( $clean_text );
        }
    }

    if($product_id !== 0) {
        update_post_meta( $product_id, 'horsepower', $horsepower );
        update_post_meta( $product_id, 'cold', $cooling );
        update_post_meta( $product_id, 'hot', $heating );
        update_post_meta( $product_id, 'aword', $energy_rating );
		
		wp_update_post(array(
            'ID' => $product_id,
            'post_excerpt' => $short_text
        ));
    }

});



/**
 * sync inventory from priority
 * Inventory sync once a day without a filter in "days_back" of several days back
 */
function syncInventoryPriority()
{

    // get the items simply by time stamp of today
    $option_filed = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[2];
    $data['select'] = (!empty($option_filed) ? $option_filed . ',PARTNAME' : 'PARTNAME');

    // $wh_name = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[0];
    // $status = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[4];

    $expand = '$expand=LOGCOUNTERS_SUBFORM($select=SAPL_BALANCE),PARTBALANCE_SUBFORM';  
    $data['expand'] = $expand;

    $response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter=SHOWINWEB eq \'Y\' &' . $data['expand'], [], WooAPI::instance()->option('log_inventory_priority', false));
    // check response status
    if ($response['status']) {
        $data = json_decode($response['body_raw'], true);
        foreach ($data['value'] as $item) {
            // if product exsits, update
            $field = (!empty($option_filed) ? $option_filed : 'PARTNAME');
            $args = array(
                'post_type' => array('product', 'product_variation'),
                'post_status' => array('publish', 'draft'),
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'value' => $item[$field]
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
            if (!$product_id == 0) {

                $stock = $item['LOGCOUNTERS_SUBFORM'][0]['SAPL_BALANCE']; //מלאי אינטרנט זמין

                update_post_meta($product_id, '_stock', $stock);

                // set stock status
                if (intval($stock) > 0) {
                    // update_post_meta($product_id, '_stock_status', 'instock');
                    $stock_status = 'instock';
                } else {
                    // update_post_meta($product_id, '_stock_status', 'outofstock');
                    $stock_status = 'outofstock';
                }

                $product = wc_get_product($product_id);
                if ($product->post_type == 'product_variation') {
                    $var = new \WC_Product_Variation($product_id);
                    $var->set_stock_status($stock_status);
                    $var->set_manage_stock(true);
                    $var->save();
                }
                if ($product->post_type == 'product') {
                    $product->set_stock_status($stock_status);
                    $product->set_manage_stock(true);
                }
                $product->save();
            }

        }
        // add timestamp
        WooAPI::instance()->updateOption('inventory_priority_update', time());
    } else {
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

add_action('syncInventoryPriority_hook', 'syncInventoryPriority');
if (!wp_next_scheduled('syncInventoryPriority_hook')) {
    $res = wp_schedule_event(time(), 'daily', 'syncInventoryPriority_hook');
}