<?php

// SimplyCT.co.il
add_filter('simply_request_data', 'simply_func');

function simply_func($data){
    //var_dump($data);
    $id_order = $data["orderId"];
    $order = new \WC_Order($id_order);
   //$i = sizeof($data['ORDERITEMS_SUBFORM']);

    foreach ($order->get_items() as $item) {
        $formatted_meta_data = $item->get_formatted_meta_data( '_', true );
    // 		echo "<pre>";
    // 		print_r($formatted_meta_data);
    // 		echo "</pre>";
        if(!empty($formatted_meta_data)){
			$items= [];
            foreach($data['ORDERITEMS_SUBFORM'] as  $item_id => $item ){
                if($item['PERCENT'] < 0){
                    unset($item['PERCENT']);
                }
				 $items[] = $item;
            }
			 //unset($data['ORDERITEMS_SUBFORM']);
    		$data['ORDERITEMS_SUBFORM'] = $items;
            // 			echo "<pre>";
            // 				print_r($data['ORDERITEMS_SUBFORM']);
            // 			echo "</pre>";
            foreach($formatted_meta_data as $formatted_data){
                $data_details = explode(")", $formatted_data->value);
                foreach($data_details as $data_item){
                    if(!empty($data_item)){
                        $data_item = explode("|", $data_item);
                        $pdt_id = url_to_postid($data_item[2]);
                        $product = wc_get_product(  $pdt_id );
                        if( $product instanceof WC_Product ){
                            $sku = $product->get_sku();
                            $price = $product->get_price();
                        }
                        $qtty = intval(trim($data_item[1], ' x'));
						 
						 $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM'])] = [
                            'PARTNAME' => $sku,
                            'TQUANT' => $qtty,
                            'VPRICE' => (float)$price,
                            'DUEDATE' => date('Y-m-d'),
                            
                        ];
	
           
                    }
                    
                } 
            }
			
        }
    }
	

	$items = [];
    foreach ($data['ORDERITEMS_SUBFORM'] as $item) {
        $vatprice= $item['VATPRICE'];

        unset($item['VATPRICE']);

        $item['PACKCODE'] = '1';

        $item['NUMPACK'] = $item['TQUANT'];

        unset($item['TQUANT']);

        $item['VATPRICE']= $vatprice;

        $items[] = $item;

    }

    unset($data['ORDERITEMS_SUBFORM']);

    $data['ORDERITEMS_SUBFORM'] = $items;
    // 	echo "<pre>";
    // 	print_r($data['ORDERITEMS_SUBFORM']);
    // 	echo "</pre>";
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

add_filter( 'cron_schedules', 'example_add_cron_interval' );
function example_add_cron_interval( $schedules ) {

    $schedules['one_hour'] = array(

        'interval' => 3600,

        'display'  => esc_html__( 'Every Hour One' ), );

    return $schedules;

}

//use PriorityWoocommerceAPI\WooAPI;

class SyncItemCron extends \PriorityAPI\API {

    private static $instance; // api instance
    private $countries = []; // countries list


    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function __construct()
    {

        add_action( 'simply_cron_hook', array($this, 'simply_func_syncItem_cron'));

        if ( ! wp_next_scheduled( 'simply_cron_hook' ) ) {

            $res = wp_schedule_event( time(), 'one_hour', 'simply_cron_hook' );

        }

    }

    public function simply_func_syncItem_cron()

    {

        $priority_version =  $this->option('priority-version');

        $daysback = 1;

        $url_addition_config = '';

        $search_field = 'PARTNAME';

        $is_update_products = (bool)true;

        // config

        $raw_option =  $this->option('sync_items_priority_config');

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

        $response =  $this->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter='.$date_filter.' '.$url_addition_config.'&$expand=PARTUNSPECS_SUBFORM,PARTTEXT_SUBFORM',[], $this->option('log_items_priority', true));

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
                    
                    $pri_price = $this->option('price_method') == true ? $item['VATPRICE'] : $item['BASEPLPRICE'];

                    $my_product = new \WC_Product( $product_id );

                    $my_product->set_regular_price($pri_price);

                    // Get the current product type
                    $current_product_type = wc_get_product( $product_id );

                    if ( $current_product_type) {
                        $saved_product_type = $current_product_type->get_type(); 

                    }

                    $my_product->save();

                    $updated_product = new \WC_Product( $product_id );

                    // Check if the saved product is still a bundle type
                    if ($saved_product_type == "bundle") {

                        $product_id = $updated_product->get_id();

                        // Set the product type back to "bundle" and save again
                        wp_set_object_terms($product_id, 'bundle', 'product_type');

                        $product_terms = wp_get_object_terms($product_id, 'product_type');

                    }
            
                }

            }


            // add timestamp

            $this->updateOption('items_priority_update', time());
        }
        else {

            $this->sendEmailError(

                $this->option('email_error_sync_items_priority'),

                'Error Sync Items Priority',

                $response['body']

            );
        }
        return $response;

    }

}
$SyncItemCron = new SyncItemCron();




//syncInventory

// use WooCommercePriorityAPI\WooAPI;
class SyncInventory extends \PriorityAPI\API {

    private static $instance; // api instance
    private $countries = []; // countries list


    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }
    
    public function __construct()
    {
  
        add_action('syncInventory_cron_hook', array($this, 'simply_func_syncInventory_cron'));

        if (!wp_next_scheduled('syncInventory_cron_hook')) {
                
            $res = wp_schedule_event(time(), 'one_hour', 'syncInventory_cron_hook');

        }

    }
    
    public function simply_func_syncInventory_cron()

    {

        $response = $this->makeRequest('GET', 'LOGPART?$select=PARTNAME,LAVI_TOTINVWEB&$filter=SHOWINWEB eq \'Y\'', [], $this->option('log_inventory_priority', false));
        // check response status       

        if ($response['status']) {

            $response_data = json_decode($response['body_raw'], true);
            foreach ($response_data['value'] as $item) {
                // if product exsits, update price
                $search_by_value = $item['PARTNAME'];
                $args = array(

                    'post_type' => array('product', 'product_variation'),

                    'post_status' => array('publish', 'draft'),

                    'meta_query' => array(

                        array(

                            'key' => '_sku',

                            'value' => $search_by_value

                        )

                    )

                );

                $product_id = 0;
                $my_query = new \WP_Query($args);
                if ($my_query->have_posts()) {

                    while ($my_query->have_posts()) {

                        $my_query->the_post();

                        $product_id = get_the_ID();

                    }
                }
                // if product variation skip
                if ($product_id != 0) {
                    update_post_meta($product_id, '_stock', $item['LAVI_TOTINVWEB']);
                    $product = wc_get_product($product_id);
                    if ($product->post_type == 'product_variation') {
                        $var = new \WC_Product_Variation($product_id);
                        $var->set_manage_stock(true);
                        $var->save();
                    }
                    if ($product->post_type == 'product') {
                        $product->set_manage_stock(true);
                    }
   
                    $product->save();
                }

            }
            // add timestamp

            $this->updateOption('items_priority_update', time());

        } else {

            $this->sendEmailError(

                $this->option('email_error_sync_items_priority'),

                'Error Sync Items Priority',

                $response['body']

            );
        }
        return $response;

    }

}
$syncInventory = new SyncInventory();

//send email if order not sync

function check_order_not_sync_cron(){

    $date_from = date('Y-m-d', strtotime('-3 days'));
    $date_to = date("Y-m-d");

    $query = new \WC_Order_Query(array(
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'return' => 'ids',
        'status'=> array( 'wc-processing'),
        'date_created'=> $date_from .'...'. $date_to, 
        'meta_key' => 'priority_order_number', // The postmeta key field
        'meta_compare' => 'NOT EXISTS', // The comparison argument
    ));

    $orders = $query->get_orders();
    if(!empty($orders)){
        $orders = implode(", ", $orders);

        $multiple_recipients = array(
            'elisheva.g@simplyct.co.il',
            'neomi@segalbaby.co.il',
        );
        $subj = 'Orders that were not synchronized in the previous day';
        $body = 'The Orders id are:';
        $body.= $orders;
        wp_mail( $multiple_recipients, $subj, $body );
    }


}

add_action('check_order_not_sync_cron_hook', 'check_order_not_sync_cron');

if (!wp_next_scheduled('check_order_not_sync_cron_hook')) {
    //$local_time_to_run = 'midnight';
    //$timestamp = strtotime( $local_time_to_run ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
    $res = wp_schedule_event(time(), 'daily', 'check_order_not_sync_cron_hook');

}



?>

