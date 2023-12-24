<?php 
use PriorityWoocommerceAPI\WooAPI;

function simply_code_after_sync_inventory($product_id,$item){

$desc = $item['EPARTDES'];
$purchase_order = $item['LOGCOUNTERS_SUBFORM'][0]['PORDERS']; //הזמנות רכש
$available_inventory = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE']; //מלאי זמין
if ( $purchase_order > 0 && $available_inventory < 4 ) {
    update_post_meta($product_id, '_backorders', 'notify');
    update_post_meta($product_id, '_backorder_description', $desc);
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
    $url_addition.= rawurlencode(' or UDATE ge ' . $bod) . ' and SPEC20 eq \'Y\'';
    
    return $url_addition;
}

add_filter('simply_syncInventoryPriority_data', 'simply_syncInventoryPriority_data_func');

function simply_syncInventoryPriority_data_func($data)

{

    $data['select'] = 'PARTNAME,EPARTDES';

    return $data;

}