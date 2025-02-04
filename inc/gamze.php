<?php 
use PriorityWoocommerceAPI\WooAPI;


add_filter('simply_syncItemsPriority_data', 'simply_data');

function simply_data($data){

    $data['select'] = 'PARTNAME,PARTDES,BASEPLPRICE,VATPRICE,SHOWINWEB,FAMILYDES,INVFLAG,FAMILYNAME,STATDES';

    return $data;

}
//set all product to general category
add_action('simply_update_product_data', function($item){
    $taxon = 'product_cat';
    $id = $item['product_id'];
    $terms_cat = wp_set_object_terms( $id, 'כללי', $taxon, true );
});

//get priority customer number by user email
add_filter('simply_modify_customer_number','simply_search_customer_in_priority_func');
function simply_search_customer_in_priority_func($data){
    $order_id = $data['order']->id;
    $order = wc_get_order($order_id);
    if ($order) {
        $user_id = $order->get_user_id();
    }
    $user_info = get_userdata($user_id);
    if ($user_info) {
        $email = $user_info->user_email;
        $url_addition = 'CUSTOMERS?$filter=EMAIL eq \''.$email.'\'' ;
        $response =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
        if($response['code']==200){
            $body =   json_decode($response['body']);
            if(!empty($body)){
                $value = $body->value[0];
                $custname = $value->CUSTNAME;
                $data['CUSTNAME'] = $custname;
                return $data;
            }
            else{
                //$subj = 'Error user not belong to priority';
                //wp_mail( get_option('admin_email'), $subj,$email.' not exist in priority' );
                $custname = WooAPI::instance()->option('walkin_number');
                $data['CUSTNAME'] = $custname;
                return $data;
            }
        } else{
            //$subj = 'Error user not belong to priority';
            //wp_mail( get_option('admin_email'), $subj,$response['body'] );
            $custname = WooAPI::instance()->option('walkin_number');
            $data['CUSTNAME'] = $custname;
            return $data;
        }
    }
    else{
        $custname = WooAPI::instance()->option('walkin_number');
        $data['CUSTNAME'] = $custname;
        return $data;
        //$subj = 'Error user not exist!';
        //wp_mail( get_option('admin_email'), $subj, $order->id.'order not belong to any user!' );
    }
  
}


add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    unset($data['PAYMENTDEF_SUBFORM']);
    unset($data['CDES']);
    return $data;
}
?>