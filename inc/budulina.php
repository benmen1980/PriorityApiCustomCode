<?php 

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
    $url_addition.= ' and SPEC20 eq \'Y\'';
    
    return $url_addition;
}

add_filter('simply_syncInventoryPriority_data', 'simply_syncInventoryPriority_data_func');

function simply_syncInventoryPriority_data_func($data)

{

    $data['select'] = 'PARTNAME,EPARTDES';

    return $data;

}