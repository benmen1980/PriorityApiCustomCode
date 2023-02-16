<?php

add_filter('simply_sync_inventory_priority', 'simply_sync_inventory_priority_func');
function simply_sync_inventory_priority_func($item)
{
    $item['stock'] = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE'];
    return $item;
}