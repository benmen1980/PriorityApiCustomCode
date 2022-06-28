<?php
add_filter('simply_request_data','manipulate_data');
function manipulate_data($data){
    $items = [];
    foreach($data['EINVOICEITEMS_SUBFORM'] as $item ){
        if($item['PARTNAME']==''){
            $item['PARTNAME'] =  '000';
            $item['PDES']=get_post($item['id'])->post_title;
        }
        $items[] = $item;
    }
    $data['EINVOICEITEMS_SUBFORM'] = $items;
    unset($data['IVDATE']);
    return $data;
}
