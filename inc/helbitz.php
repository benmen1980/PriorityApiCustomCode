<?php
add_filter('simply_request_data','manipulate_data');
function manipulate_data($data){
    $items = [];
    foreach($data['ORDERITEMS_SUBFORM'] as $item ){
        if($item['PARTNAME']==''){
            $item['PARTNAME'] =  '000';
        }
        $items[] = $item;
    }
    $data['ORDERITEMS_SUBFORM'] = $items;
    return $data;
}
