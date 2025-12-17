<?php
use PriorityWoocommerceAPI\WooAPI;

/**
* Change email for errors from the site manager
*
*/
add_filter('simplyct_sendEmail', 'simplyct_sendEmail_func');
function simplyct_sendEmail_func($emails)
{
    array_push($emails, 'margalit.t@simplyct.co.il');
    return $emails;
}

//sync inventory by SPEC16 field
add_filter('simply_syncInventoryPriority_filter_addition', 'simply_syncInventoryPriority_filter_addition_func');
function simply_syncInventoryPriority_filter_addition_func($url_addition)
{
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);

    $url_addition= '('. $url_addition .rawurlencode(' or UDATE ge ' . $bod) . ') and SPEC16 eq \'Y\' ';

    return $url_addition;
}

add_filter('simply_syncInventoryPriority_by_sku_filter_addition', 'simply_syncInventoryPriority_by_sku_filter_addition_func');
function simply_syncInventoryPriority_by_sku_filter_addition_func($url_addition)
{
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);

    $url_addition= '('. $url_addition .rawurlencode(' or UDATE ge ' . $bod) . ') and SPEC16 eq \'Y\' ';

    return $url_addition;
}

// update another fields to product in creating a product
add_action( 'simply_update_product_data', function($item) {
    $product_id = $item['product_id'];

    if( $product_id !== 0 ) {
        wp_update_post([
            'ID'         => $product_id,
            'post_title' => sanitize_text_field( $item['SPEC1'] ),
        ]);

        update_post_meta( $product_id, '_global_unique_id', $item['BARCODE'] );
    }
} );

// update only title, price, barcode etc. but not update ststus anyone
add_action('simply_update_product_price', 'simply_update_product_price_func');
function simply_update_product_price_func($item)
{
    $product_id = $item['product_id'];
    
    update_post_meta( $product_id, '_global_unique_id', $item['BARCODE'] );

    $set_tax = get_option('woocommerce_calc_taxes');    
    $pri_price = (wc_prices_include_tax() == true || $set_tax == 'no') ? $item['VATPRICE'] : $item['BASEPLPRICE'];

    $my_product = wc_get_product( $product_id );
    $my_product->set_name( sanitize_text_field( $item['SPEC1'] ) );
    $my_product->set_regular_price( $pri_price );
    $my_product->save();
}

add_filter('simply_update_parent_status', function($post_data) {
    // Add a custom field before product creation
    $_product = wc_get_product( $post_data['ID'] );
    $current_status        = $_product->get_status(); 
    $post_data['post_status'] = $current_status;

    return $post_data;
}, 10, 1 );

// search CUSTNAME in priority by phone
add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority_func');
function simply_search_customer_in_priority_func($data) {
    $order = $data['order'];
    if($order)
        $phone =  $order->get_billing_phone();

    $user_id = $data['user_id'];
    if (empty($phone) && $user = get_userdata($user_id)) 
        $phone = get_user_meta($user_id, 'billing_phone', true);

    $custname = null;

    $option_name = 'custom_customer_counter';
    // Retrieve the last number or start from 0
    $last_number = get_option($option_name, 0);
    $next_number = $last_number + 1;
    $formatted_number = str_pad($next_number, 3, '0', STR_PAD_LEFT); // Format the code with leading zeros

    //Check if the customer already exists as a priority by the phone
    if($phone) {
        $url_addition = 'CUSTOMERS?$filter=PHONE eq \''.$phone.'\'' ;
        $res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
        if($res['code'] == 200){
            $body =   json_decode($res['body']);
            $value = $body->value[0];
            if (isset($value) && !empty($value)) {
                $custname = $value->CUSTNAME;
                $priority_cust_from_wc = $custname;
            } else {
                $custname = 'WEB-' . (string)$formatted_number;
                update_option($option_name, $next_number); //update in DB the new value
            }
        } else {
            $custname = 'WEB-' . (string)$formatted_number;
            update_option($option_name, $next_number); //update in DB the new value
        }

        $custdes = !empty($order->get_billing_company()) ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $request = [           
            'CUSTNAME' => $custname,
            'CUSTDES' => $custdes,
            'EMAIL' => $order->get_billing_email(),
            'ADDRESS' => $order->get_billing_address_1(),
            'ADDRESS2' => $order->get_billing_address_2(),
            'STATEA' => $order->get_billing_city(),
            'ZIP' => $order->get_shipping_postcode(),
            'PHONE' => $order->get_billing_phone(),
            'NSFLAG' => 'Y',
            'CTYPECODE' => '20',
        ];

        $method = !empty($priority_cust_from_wc) ? 'PATCH' : 'POST';
        $url_eddition = 'CUSTOMERS';
        if ($method == 'PATCH') {
            $url_eddition = 'CUSTOMERS(\'' . $custname . '\')';
            unset($request['CUSTNAME']);
            unset($request['CTYPECODE']);
        }

        $json_request = json_encode($request);
        $response = WooAPI::instance()->makeRequest($method, $url_eddition, ['body' => $json_request], true);
        if ($method == 'POST' && $response['code'] == '201' || $method == 'PATCH' && $response['code'] == '200') {
            $res = json_decode($response['body']);
        } 
        else {
            WooAPI::instance()->sendEmailError(
                [WooAPI::instance()->option('email_error_sync_customers_web')],
                'Error Sync Customers',
                $response['body']
            );
        }
    }     
    $data['CUSTNAME'] = $custname;
    return $data;
}