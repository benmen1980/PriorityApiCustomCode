<?php
add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $item_warehouse = '75';
    $data['DETAILS'] = 'MED-' . $data['BOOKNUM'];
    $data['UNI_ORDTYPE'] = 'B';
    $data['UNI_DUEDATE'] = date('Y-m-d');
    $data['UFLR_ORDERRCVCODE'] = '25';

    for ($i = 0; $i < count($data['ORDERITEMS_SUBFORM']); $i++) {
        $data['ORDERITEMS_SUBFORM'][$i]['UNI_ORDTYPE'] = 'B';
        $data['ORDERITEMS_SUBFORM'][$i]['DUEDATE'] = date('Y-m-d');
        $data['ORDERITEMS_SUBFORM'][$i]['DOERLOGIN'] = 'israela';
        $data['ORDERITEMS_SUBFORM'][$i]['UNI_WARHSNAME'] = $item_warehouse;
    }
    unset($data['PAYMENTDEF_SUBFORM']['EMAIL']);
    return $data;

}

add_filter('simply_request_data_receipt', 'simply_request_data_receipt_func');
function simply_request_data_receipt_func($data)
{
    $data['DETAILS'] = 'MED-' . $data['BOOKNUM'];
    $data['BOOKNUM'] = $data['DETAILS'];
    return $data;
}
