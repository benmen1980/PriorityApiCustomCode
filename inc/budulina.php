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