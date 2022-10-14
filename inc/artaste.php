<?php
use PriorityWoocommerceAPI\WooAPI;


add_filter('simply_request_data', 'simply_func');
function simply_func($data)

{
    if($data['doctype']=='ORDERS') {
        // CURDATE = PDATE
        $data['PDATE'] = $data['CURDATE'];
        unset($data['CURDATE']);
        // ORDERSTEXT_SUBFORM replace with CPROFTEXT
        $data = WooAPI::instance()->change_key($data, 'ORDERSTEXT_SUBFORM', 'CPROFTEXT_SUBFORM');
        // CPROFCONT
        $data = WooAPI::instance()->change_key($data, 'ORDERSCONT_SUBFORM', 'CPROFCONT_SUBFORM');
        // ORDER ITEMS REPLACE CPROFITEMS
        $data = WooAPI::instance()->change_key($data, 'ORDERITEMS_SUBFORM', 'CPROFITEMS_SUBFORM');
        // replace cprofitems fields
        $items = [];
        foreach ($data['CPROFITEMS_SUBFORM'] as $item) {
            unset($item['DUEDATE']);
            $item['VPRICE'] = $item['VATPRICE'] / $item['TQUANT'];
            unset($item['VATPRICE']);
            $items[] = $item;
        }
        $data['CPROFITEMS_SUBFORM'] = $items;
        // PAYMENTDEF
        unset($data['PAYMENTDEF_SUBFORM']);
    }
    if($data['doctype']=='EINVOICES') {
        $id = $data['DETAILS'];
        $CPROFNUM = get_post_meta($id,'priority_order_number',true);
        $data['DETAILS'] = $CPROFNUM;
        unset($data['IVDATE']);
    }
    return $data;
}
