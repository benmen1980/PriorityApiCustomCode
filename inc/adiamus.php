<?php
add_filter('simply_syncPricePriority', 'simply_syncPricePriority_func');
function simply_syncPricePriority_func($data)
{
    $data['select']='PARTNAME,BASEPLPRICE,VATPRICE,BARCODE,WSPLPRICE';
    return $data;
}
?>
