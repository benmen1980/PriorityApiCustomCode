<?php
add_filter('simply_syncItemsPriority_data','simply_selectPrice_func');
add_filter('simply_syncPricePriority', 'simply_selectPrice_func');
function simply_selectPrice_func($data)
{
    $data['select'].=',WSPLPRICE';
    return $data;
}
?>
