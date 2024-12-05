<?php 
use PriorityWoocommerceAPI\WooAPI;


function simply_code_after_sync_inventory($product_id,$item){

$desc = $item['EPARTDES'];
$purchase_order = $item['LOGCOUNTERS_SUBFORM'][0]['PORDERS']; //הזמנות רכש
$available_inventory = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE']; //מלאי זמין
//הזמנת רכש ממחסן 50
$purchase_order_warshname_50 = $item['PARTBALANCE_SUBFORM'][0]['TBALANCE'];

if ($purchase_order_warshname_50 > 0) {
    update_post_meta($product_id, '_backorders', 'notify');
    update_post_meta($product_id, '_backorder_description', $desc);
} 
else {
    if ( $purchase_order > 0 && $available_inventory < 4 ) {
    update_post_meta($product_id, '_backorders', 'notify');
    update_post_meta($product_id, '_backorder_description', $desc);
    }
    else{
        update_post_meta($product_id, '_backorders', 'no');
        update_post_meta($product_id, '_backorder_description', '');
    }
}

return null;
}

add_filter('simply_syncInventoryPriority_filter_addition', 'simply_syncInventoryPriority_filter_addition_func');

function simply_syncInventoryPriority_filter_addition_func($url_addition)

{
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);

    //$url_addition.= ' and SPEC20 eq \'Y\'';
    //$url_addition.= rawurlencode(' or UDATE ge ' . $bod) . ' and SPEC20 eq \'Y\'';
    $url_addition= '('. $url_addition .rawurlencode(' or UDATE ge ' . $bod) . ') and SPEC20 eq \'Y\' ';

    return $url_addition;
}

add_filter('simply_syncInventoryPriority_data', 'simply_syncInventoryPriority_data_func');

function simply_syncInventoryPriority_data_func($data)

{
    $expand = '$expand=LOGCOUNTERS_SUBFORM($select=DIFF,BALANCE,PORDERS),PARTBALANCE_SUBFORM($filter=WARHSNAME eq \'50\')';
    $data['expand'] = $expand;
    $data['select'] = 'PARTNAME,EPARTDES';

    return $data;

}


add_action('syncPriorityCompletedOrderStatuses_cron_hook', 'syncPriorityCompletedOrderStatus');

if (!wp_next_scheduled('syncPriorityCompletedOrderStatuses_cron_hook')) {

   $res = wp_schedule_event(time(), 'hourly', 'syncPriorityCompletedOrderStatuses_cron_hook');

}

function syncPriorityCompletedOrderStatus(){
    $daysback = 1;
    $stamp = mktime(0 - $daysback * 24, 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $date_filter = 'UDATE ge ' . urlencode($bod);
    $data['select'] = 'UDATE,STCODE,STDES,DISTRDATE,AIRWAYBILL,STATDES,REFERENCE';
    $additionalurl = 'DOCUMENTS_D?$select=STCODE,STDES,DISTRDATE,AIRWAYBILL,STATDES,REFERENCE,DOCNO,TYPE&$expand=DOCORDI_SUBFORM&$filter='.$date_filter.' and REFERENCE ne \'\' and STATDES eq \'סופית\' ';
    $response_doc = WooAPI::instance()->makeRequest( "GET", $additionalurl, [], true );
    if($response_doc['code'] == '200'){
        $doc_shippings     = json_decode( $response_doc['body'], true )['value'];
        foreach($doc_shippings as $data){
            $statedes = $data['STATDES'];
            $order_id = intval($data['REFERENCE']);
            $order = wc_get_order($order_id);
            write_to_custom_log('update status for '.$order_id);
            $docordiSubform = $data['DOCORDI_SUBFORM'];
            //skip order that i have anither partname not shipping closed
            if (!empty($docordiSubform)) {
                foreach ($docordiSubform as $item) {
                    if (isset($item['PARTNAME']) && $item['PARTNAME'] !== "000") {
                        write_to_custom_log($order_id.' not completed because another item');
                        continue 2;
                    }
                }
            }
            if ( $order && $order->get_status() !== 'completed' ) {
                $unixTime = strtotime($data['DISTRDATE']);
                $distrDate = date("d-m-Y", $unixTime);
                $note = ' נשלח ב'.$data['STDES'].' בתאריך '.$distrDate;
                $order->add_order_note( $note );
                $order->update_status( 'completed' );
                write_to_custom_log($order_id.' has status completed');
            }       
        }  
    }
    else{
        write_to_custom_log('error get documents_d');
        WooAPI::instance()->sendEmailError(
            array(get_bloginfo('admin_email'),'liora@budulina.co.il'),
            'כשל סגירת תעודת באתר',
            $response_doc['body']
        );
    } 
}


function write_to_custom_log($log_msg)
{

    $uploads = wp_upload_dir(null, false);
    $log_filename = $uploads['basedir'] . '/logs';
    if (!file_exists($log_filename)) {
        // create directory/folder uploads.
        mkdir($log_filename, 0777, true);
    }

    // Get all files in the log directory
    $log_files = glob($log_filename . '/*.log');

    // Define the time limit (files older than 7 days will be deleted)
    $time_limit = strtotime('-7 days');

    foreach ($log_files as $file) {
        if (filemtime($file) < $time_limit) {
            unlink($file); // Delete files older than 7 days
        }
    }

        
    $log_file_data = $log_filename . '/' . date('d-M-Y') . '.log';
    // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
    file_put_contents($log_file_data, date('H:i:s') . ' ' . $log_msg . "\n", FILE_APPEND);
}

//not in use
function syncPriorityCompletedOrderIdStatus($order_ids){
    $conditions = [];

    // Loop through each SKU in the array
    foreach ($order_ids as $order_id) {
        // Append each condition for PARTNAME eq 'sku'
        $conditions[] = WooAPI::instance()->option('order_order_field')." eq '{$order_id}'";
    }
    $url_addition = '(' . implode(' OR ', $conditions) . ')';
    $url_addition = 'ORDERS?$select=ORDNAME,BOOLCLOSED&$filter=(' . implode(' OR ', $conditions) . ')';
    $response = WooAPI::instance()->makeRequest("GET", $url_addition, [], true);
    if($response['code'] == '200'){
        //write_to_custom_log('success get orders id from priority');
        $priorirty_orders =json_decode($response['body_raw'], true)['value'];
        if(!empty($priorirty_orders)){
            $ord_names = [];
            foreach ($priorirty_orders as $priorirty_order) {
                $order_status = $priorirty_order['BOOLCLOSED'];
                if( $order_status == 'Y'){
                    $ord_names[] = $priorirty_order['ORDNAME'];
                }
            }
            foreach ($ord_names as $ord_name){
                $doc_conditions[] = "ORDNAME eq '{$ord_name}'";
            }
            if(!empty($doc_conditions)){
                $additionalurl = 'DOCUMENTS_D?$select=STCODE,STDES,DISTRDATE,AIRWAYBILL,STATDES,REFERENCE,DOCNO,TYPE&$filter=(' . implode(' OR ', $doc_conditions) . ')';
                $response_doc = WooAPI::instance()->makeRequest( "GET", $additionalurl, [], true );
                if($response_doc['code'] == '200'){
                    $doc_shippings     = json_decode( $response_doc['body'], true )['value'];
                    foreach($doc_shippings as $data){
                        $statedes = $data['STATDES'];
                        $order_id = intval($data['REFERENCE']);
                        if( $statedes == "סופית"){
                            $unixTime = strtotime($data['DISTRDATE']);
                            $distrDate = date("d-m-Y", $unixTime);
                            $note = ' נשלח ב'.$data['STDES'].' בתאריך '.$distrDate;
                            //write_to_custom_log('update notes for '.$ord_name);
                            $order = wc_get_order($order_id);
                            if ( ! $order ) {
                                //write_to_custom_log($order_id.' is not a valid WooCommerce order.');
                            }
                            else{
                                $order->add_order_note( $note );
                                $order->update_status( 'completed' );
                                $order_status = get_order_status($order_id);
                                //write_to_custom_log($order_id.' has status: '.$order_status);
                            }    
                        
                            
                            
                        }
                    }  
                }
                else{
                    //write_to_custom_log('error get documents_d for'.$ord_name);
                    WooAPI::instance()->sendEmailError(
                        get_bloginfo('admin_email'),
                        'Error Update Order from priority to website',
                        $response_doc['body']
                    );
                }    
            }
           
        }
     
  
    }
    else{
        //write_to_custom_log('error get orders for'.$ord_name);
        WooAPI::instance()->sendEmailError(
            get_bloginfo('admin_email'),
            'Error get Order from priority to update status',
            $response['body']
        );
    }
}