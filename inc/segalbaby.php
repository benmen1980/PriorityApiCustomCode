<?php

// SimplyCT.co.il

add_filter('simply_request_data', 'simply_func');

function simply_func($data)

{

    $items = [];

    foreach ($data['ORDERITEMS_SUBFORM'] as $item) {

        $vatprice= $item['VATPRICE'];

        unset($item['VATPRICE']);

        $item['PACKCODE'] = '1';

        $item['NUMPACK'] = $item['TQUANT'];

        unset($item['TQUANT']);

        $item['VATPRICE']=$vatprice;

        $items[] = $item;

    }

    unset($data['ORDERITEMS_SUBFORM']);

    $data['ORDERITEMS_SUBFORM'] = $items;

    return $data;

}

add_filter('simply_syncInventoryPriority_data', 'simply_syncInventoryPriority_data_func');

function simply_syncInventoryPriority_data_func($data)

{

    $data['select'] = 'PARTNAME,LAVI_TOTINVWEB,VATPRICE';

    return $data;

}



add_filter('simply_sync_inventory_priority', 'simply_sync_inventory_priority');

function simply_sync_inventory_priority($data)

{

    $data['stock'] = $data['LAVI_TOTINVWEB'];

    unset($data['LAVI_TOTINVWEB']);

    return $data;

}
//cron

add_filter('simply_syncItemsPriority_item', 'simply_sync_items_to_priority');

function simply_sync_items_to_priority($data)

{

    $data['BASEPLPRICE'] = $data['LAVI_WEBPRICE'];

    unset($data['LAVI_WEBPRICE']);

    return $data;

}
add_filter('simply_request_data','simply_request_data_func');
function simply_request_data_func($data)
{
    $orderId=$data["orderId"];
    $v=get_post_meta($orderId,'_billing_phone_2',true);
    $data['SHIPTO2_SUBFORM']['FAX']=$v;
    return $data;

}


add_filter('simply_syncItemsPriority_data', 'simply_data');

function simply_data($data)

{

    $data['select'] = 'PARTNAME,VATPRICE,LAVI_WEBPRICE';

    return $data;

}
use PriorityWoocommerceAPI\WooAPI;

function simply_func_syncItem_cron()

{

    $priority_version = WooAPI::instance()->option('priority-version');

    $daysback = 1;

    $url_addition_config = '';

    $search_field = 'PARTNAME';

    $is_update_products = (bool)true;

    // config

    $raw_option = WooAPI::instance()->option('sync_items_priority_config');

    $raw_option = str_replace(array( "\n", "\t", "\r"), '', $raw_option);

    $config = json_decode(stripslashes($raw_option));

    $daysback = (int)$config->days_back;

    $synclongtext = $config->synclongtext;

    $url_addition_config = $config->additional_url;

    $search_field = (!empty($config->search_by) ? $config->search_by : 'PARTNAME');

    // get the items simply by time stamp of today

    $stamp = mktime(0 - $daysback*24, 0, 0);

    $bod = date(DATE_ATOM,$stamp);

    $date_filter = 'UDATE ge '.urlencode($bod);

    $data['select'] = 'PARTNAME,BASEPLPRICE,VATPRICE';

    $data = apply_filters( 'simply_syncItemsPriority_data', $data );

    $response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter='.$date_filter.' '.$url_addition_config.'&$expand=PARTUNSPECS_SUBFORM,PARTTEXT_SUBFORM',[], WooAPI::instance()->option('log_items_priority', true));

    // check response status

    if ($response['status']) {

        $response_data = json_decode($response['body_raw'], true);
        foreach($response_data['value'] as $item)
        {
            // if product exsits, update price
            $search_by_value = $item[$search_field];
            $args = array(

                'post_type'		=>	array('product', 'product_variation'),

                'post_status' => array('publish',  'draft'),

                'meta_query'	=>	array(

                    array(

                        'key'       => '_sku',

                        'value'	=>	$search_by_value

                    )

                )

            );

            $product_id = 0;
            $my_query = new \WP_Query( $args );
            if ( $my_query->have_posts() ) {

                while ( $my_query->have_posts() ) {

                    $my_query->the_post();

                    $product_id = get_the_ID();

                }
            }
            // if product variation skip
            if ($product_id != 0)
            {
                $item = apply_filters( 'simply_syncItemsPriority_item', $item );

                $pri_price = WooAPI::instance()->option('price_method') == true ? $item['VATPRICE'] : $item['BASEPLPRICE'];

                $my_product = new \WC_Product( $product_id );

                $my_product->set_regular_price($pri_price);

                $my_product->save();
            }
        }
        // add timestamp

        WooAPI::instance()->updateOption('items_priority_update', time());
    }
    else {

        WooAPI::instance()->sendEmailError(

            WooAPI::instance()->option('email_error_sync_items_priority'),

            'Error Sync Items Priority',

            $response['body']

        );
    }
    return $response;

}
add_filter( 'cron_schedules', 'example_add_cron_interval' );
function example_add_cron_interval( $schedules ) {

    $schedules['one_hour'] = array(

        'interval' => 3600,

        'display'  => esc_html__( 'Every Hour One' ), );

    return $schedules;

}
add_action( 'simply_cron_hook', 'simply_func_syncItem_cron' );

if ( ! wp_next_scheduled( 'simply_cron_hook' ) ) {

    $res = wp_schedule_event( time(), 'one_hour', 'simply_cron_hook' );

}

