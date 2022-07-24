<?php
add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $item_warehouse = '75';
    $data['DCODE'] = 'web';
    $data['DETAILS'] = '01699985';
    $data['UNI_ORDTYPE'] = 'B';
    $data['UNI_DUEDATE'] = date('Y-m-d');
    $data['UFLR_ORDERRCVCODE'] = '8';

    for ($i = 0; $i < count($data['ORDERITEMS_SUBFORM']); $i++)
        $data['ORDERITEMS_SUBFORM'][$i] = [
            'UNI_ORDTYPE' => 'B',
            'DUEDATE' => date('Y-m-d'),
            'DOERLOGIN' => 'israela',
            'UNI_WARHSNAME' => $item_warehouse
        ];
    return $data;

}
