<?php
use PriorityWoocommerceAPI\WooAPI;

//sync inventory by SPEC1
add_filter('simply_syncInventoryPriority_filter_addition', 'simply_syncInventoryPriority_filter_addition_func');
function simply_syncInventoryPriority_filter_addition_func($url_addition)
{
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);

    $url_addition= '('. $url_addition .rawurlencode(' or UDATE ge ' . $bod) . ') and SPEC1 eq \'Y\' ';

    return $url_addition;
}
add_filter('simply_syncInventoryPriority_by_sku_filter_addition', 'simply_syncInventoryPriority_by_sku_filter_addition_func');
function simply_syncInventoryPriority_by_sku_filter_addition_func($url_addition)
{
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);

    $url_addition= '('. $url_addition .rawurlencode(' or UDATE ge ' . $bod) . ') and SPEC1 eq \'Y\' ';

    return $url_addition;
}

//define select field for sync item
add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
function simply_syncItemsPriority_data_func($data)
{
    $data['expand'] .= ',INTERNALDIALOGTEXT_SUBFORM';
	return $data;
}

// update another fields to product
add_action('simply_update_product_data', function($item){
    $product_id = $item['product_id'];

    $short_text = '';
    if ( isset( $item['PARTTEXT_SUBFORM'] ) ) {
        foreach ( $item['PARTTEXT_SUBFORM'] as $text ) {
            $clean_text = preg_replace('/<style>.*?<\/style>/s', '', $text);
            $short_text .= ' ' . html_entity_decode( $clean_text );
        }
    }
	
	$post_content = '';
    if ( isset( $item['INTERNALDIALOGTEXT_SUBFORM'] ) ) {
        foreach ( $item['INTERNALDIALOGTEXT_SUBFORM'] as $content ) {
            $clean_text = preg_replace('/<style>.*?<\/style>/s', '', $content);
            $post_content .= ' ' . html_entity_decode( $clean_text );
        }
    }
    

    if($product_id !== 0) {

		update_post_meta( $product_id, 'תיאור_קצר_מתחת_לטייטל', $short_text );

		wp_update_post(array(
            'ID' => $product_id,
            'post_content' => $post_content
        ));
    }

});

// update status of product to publish if SHOEINWEB equal to 'Y'
add_action('custom_change_product_status', 'custom_handle_product_status_change');
function custom_handle_product_status_change($product_id) {
    wp_update_post(array(
        'ID'          => $product_id,
        'post_status' => 'publish'
    ));
    wp_cache_flush(); // Clears cache to ensure fresh data
}

// search CUSTNAME in priority by phone or email
add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority_func');
function simply_search_customer_in_priority_func($data){
    $order = $data['order'];
    if($order) {
        $phone =  $order->get_billing_phone();
        $email =  strtolower($order->get_billing_email());
    }

    $user_id = $data['user_id'];
    if (empty($phone) && $user = get_userdata($user_id)) 
        $phone = get_user_meta($user_id, 'billing_phone', true);

    if (empty($email) && $user = get_userdata($user_id)) 
        $email = $user->user_email;

    $custname = null;

    //Check if the customer already exists as a priority by the phone
    if( !empty($phone) ) {
        $url_addition = 'CUSTOMERS?$filter=PHONE eq \''.$phone.'\'' ;
        $res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
        if($res['code'] == 200) {
            $body =   json_decode($res['body']);
            $value = $body->value[0];
            if (isset($value) && !empty($value)) {
                $custname = $value->CUSTNAME;
            } 
        } 
    }
    if ( !isset($custname) && !empty($email) ) {
        $url_addition = 'CUSTOMERS?$filter=EMAIL eq \''.$email.'\'' ;
        $email_res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
        if($email_res['code'] == 200) {
            $email_body =   json_decode($email_res['body']);
            $email_value = $email_body->value[0];
            if (isset($email_value) && !empty($email_value)) {
                $custname = $email_value->CUSTNAME;
            } 
        } 

    }
    
    $data['CUSTNAME'] = $custname;
    return $data;
}