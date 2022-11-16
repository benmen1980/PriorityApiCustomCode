<?php
use PriorityWoocommerceAPI\WooAPI;
if(!is_admin()) {
    if (get_current_user_id()) {
        add_filter('woocommerce_currency', 'simply_change_currency_by_price_list');
    }
}
function simply_change_currency_by_price_list($currency){
    $meta = get_user_meta(get_current_user_id(), 'custpricelists', true);
    $list = $meta[0]['PLNAME']; // use base price list if there is no list assigned
    $code = WooAPI::instance()->getPriceListData($list)['price_list_currency'];
    if ($list && $meta && $code) {
        return $code;
    }else{
        return $currency;
    }
}
