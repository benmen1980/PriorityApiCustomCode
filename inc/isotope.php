<?php 

use PriorityWoocommerceAPI\WooAPI;


//session_start();
add_filter( 'simply_request_customer_data', 'simply_get_customer_info');
function simply_get_customer_info($data){

   
    // make request to API
    $args = [];
    $servicecall = $data['docno']; //SO25000009
    $random =  $_GET['r'] ;
    $select = 'BOOKNUM,ORDNAME,CDES,CUSTNAME,TOTPRICE,CURDATE';
    //$expand = '&$expand=ORDERSCONT_SUBFORM';
   // $response = WooAPI::instance()->makeRequest('GET', 'DOCUMENTS_Q?$filter=DOCNO eq \''.$servicecall.'\'&$select='.$select.$expand, [], true);
    $response = WooAPI::instance()->makeRequest('GET', 'TTS_ORDSPAY?$filter=ORDNAME eq \''.$servicecall.'\' and TTS_ORDNUMGPAY eq \''.$random.'\'', [], true);
 
    if ($response['code']<=201) {
        $data['data'] = array();
        $body_array = json_decode($response["body"],true);
        if(!empty($body_array['value'])){
            $user_details = $body_array['value'][0];
            // check expiration of the service call
            // $expDateTime = new DateTime($user_details['TLOS_LINKEXPIREDAT']); // datetime object
            // $now =  new DateTime();
            // //if($now>$expDateTime){
            //  if($now>$expDateTime&&$user_details['TLOS_LINKEXPIREDAT']!=null){
            //     // this is where should die
            //     wp_die('הקישור פג תוקף, פנה לשירות לקוחות 6620*');
            // }
            // populate the check out form
            $data['first_name'] = $user_details['CDES'];
            $data['city'] = $user_details['EDPT_CITYNAME'];
            $data['street'] = $user_details['ADDRESS'];
            //$data['postcode'] = $user_details['ORDERSCONT_SUBFORM'][0]['ZIP'];
            $data['postcode'] = '11111';
            $data['phone'] = $user_details['PHONENUMEMAIL'];
            $data['price'] = $user_details['TOTPRICE'];
            $data['email'] = $user_details['EMAILP'];
            $data['data']['custname'] = $user_details['CUSTNAME'];
            $data['data']['docno'] = $user_details['BOOKNUM'];
            $data['data']['cprofnum'] = $user_details['TTS_CPROFNUM'];
            $data['data']['contactname'] = $user_details['NAME'];
        }
        else{
            wp_die('לא ניתן לשלם עבור נתונים אלו, בדוק מספר הזמנה ומספר רנדומלי');
        }


    }else {
        WooAPI::instance()->sendEmailError(
            [],
            'Error getting service call info',
            $response['body']
        );
    }
    return $data;
}

// add_filter( 'woocommerce_checkout_get_value','add_comment_checkout_fields',10,2);

// function add_comment_checkout_fields($input, $key ){
//     $retrive_data = WC()->session->get( 'session_vars' );
//     $comments = $retrive_data['data']['cprofnum'];
// }

add_filter( 'woocommerce_checkout_fields', 'custom_checkout_field' );

function custom_checkout_field( $fields ) {
    $retrive_data = WC()->session->get( 'session_vars' );
    // echo "<pre>";
    // print_r($retrive_data);
    // echo "</pre>";
    $fields['billing']['billing_custname'] = array(
        'type'        => 'text',
        'label'       => __('מספר לקוח', 'woocommerce'),
        //'placeholder' => __('Enter custom data', 'woocommerce'),
        'required'    => true,
        'default' => $retrive_data['data']['custname'],
        'class'       => array('form-row-wide'),
        'priority'    => 35, // Adjust position
    );

    $fields['billing']['billing_docno'] = array(
        'type'        => 'text',
        'label'       => __('מספר הסכם', 'woocommerce'),
        //'placeholder' => __('Enter custom data', 'woocommerce'),
        'required'    => false,
        'default' => $retrive_data['data']['docno'],
        'class'       => array('form-row-wide'),
        'priority'    => 39, // Adjust position
    );
    $fields['billing']['billing_contactname'] = array(
        'type'        => 'text',
        'label'       => __('שם איש קשר', 'woocommerce'),
        //'placeholder' => __('Enter custom data', 'woocommerce'),
        'required'    => false,
        'default' => $retrive_data['data']['contactname'],
        'class'       => array('form-row-wide'),
        'priority'    => 37, // Adjust position
    );

    $fields['order']['order_comments']['default'] = $retrive_data['data']['cprofnum'];
    $fields['billing']['billing_first_name']['label'] = __('שם לקוח', 'woocommerce');
    $fields['billing']['billing_first_name']['class'] = array('form-row-wide');

    $fields['order']['order_comments']['placeholder'] = '';
    

     // Remove "Last Name" field
     if (isset($fields['billing']['billing_last_name'])) {
        unset($fields['billing']['billing_last_name']);
    }
    unset($fields['billing']['billing_postcode']);
    $fields['billing']['billing_contactname']['required'] = false;
    //$fields['billing']['billing_phone']['required'] = false;
    //$fields['billing']['billing_postcode']['required'] = false;

    //set all fields read only
    foreach ($fields as $section => $section_fields) {
        foreach ($section_fields as $key => $field) {
            $fields[$section][$key]['custom_attributes'] = array('readonly' => 'readonly');
        }
    }

    return $fields;
}

add_filter( 'default_checkout_shipping_country', 'set_default_shipping_country' );

function set_default_shipping_country() {
    return 'IL'; // קוד המדינה הרצויה, לדוגמה 'IL' עבור ישראל
}


add_action( 'woocommerce_checkout_update_order_meta', 'save_custom_checkout_field' );

function save_custom_checkout_field( $order_id ) {
    if ( ! empty( $_POST['billing_custname'] ) ) {
        update_post_meta( $order_id, 'billing_custname', sanitize_text_field( $_POST['billing_custname'] ) );
    }
    if ( ! empty( $_POST['billing_docno'] ) ) {
        update_post_meta( $order_id, 'billing_docno', sanitize_text_field( $_POST['billing_docno'] ) );
    }
    if ( ! empty( $_POST['billing_contactname'] ) ) {
        update_post_meta( $order_id, 'billing_contactname', sanitize_text_field( $_POST['billing_contactname'] ) );
    }
}


add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_custom_field_in_admin', 10, 1 );

function display_custom_field_in_admin( $order ) {
    $billing_custname = get_post_meta( $order->get_id(), 'billing_custname', true );
    if ( $billing_custname ) {
        echo '<p><strong>' . __( 'מספר לקוח', 'woocommerce' ) . ':</strong> ' . esc_html( $billing_custname ) . '</p>';
    }
    $billing_docno = get_post_meta( $order->get_id(), 'billing_docno', true );
    if ( $billing_custname ) {
        echo '<p><strong>' . __( 'מספר הסכם', 'woocommerce' ) . ':</strong> ' . esc_html( $billing_docno ) . '</p>';
    }
    $billing_contactname = get_post_meta( $order->get_id(), 'billing_contactname', true );
    if ( $billing_contactname ) {
        echo '<p><strong>' . __( 'איש קשר', 'woocommerce' ) . ':</strong> ' . esc_html( $billing_contactname ) . '</p>';
    }
}

add_filter( 'woocommerce_email_order_meta_keys', 'custom_checkout_field_email' );

function custom_checkout_field_email( $keys ) {
    $keys[] = 'billing_custname';
    $keys[] = 'billing_docno';
    $keys[] = 'billing_contactname';
    return $keys;
}


add_filter('simply_after_post_order', 'simply_after_order_func');
function simply_after_order_func($array)
{
    // $ord_status = $array["STATDES"];
    $order_number = $array["ORDNAME"]; 
    $order_id = $array["order_id"];

}


//add_action('woocommerce_order_status_changed', 'syncPaymentAfterOrder');
add_action('woocommerce_payment_complete', 'syncPaymentAfterOrder', 99999999);

function syncPaymentAfterOrder($order_id){
    WooAPI::instance()->sendEmailError(
        ['elisheva.g@simplyct.co.il',get_bloginfo('admin_email')],
        'enter sync payment',
        'payment'.$order_id
    );
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item_id => $item) {
        // Attempt to get product-ivnum directly
        $order_name = $item->get_meta('product-ivnum', true);
       
    }
    $confnum = $order->get_meta('cc_company_approval_num');

    $url_addition = 'TTS_ORDSPAY(ORDNAME=\'' . $order_name . '\')';
    $order_comments = $order->get_customer_note();
    //$data['TTS_CPROFNUM'] = $order_comments; 
    $data['TTS_PAYED'] = 'Y';
    $data['TTS_NUMPAYMENT'] = (string) ($order->get_meta('w2t_npay') + 1);
    $data['TTS_PAYORD_SUBFORM'][]  = [
        'PAYTYPECODE' => "1",
        'AMOUNT' => floatval($order->get_total()),
        'RECPAPP' => $order->get_meta('cc_company_approval_num'),
    ];
  

    $response = WooAPI::instance()->makeRequest('PATCH', $url_addition, ['body' => json_encode($data)], true);
    if ($response['code'] <= 201 && $response['code'] >= 200 ) {
        $order->update_meta_data('priority_recipe_status', 'שולם');
        $order->update_meta_data('priority_recipe_number', $order_name);
        $order->save();
    }
    else {
        $mes_arr = json_decode($response['body']);
        $message = $response['message'] . '' . json_encode($response);
        $message = $response['message'] . '<br>' . $response['body'] . '<br>';
        if(isset($mes_arr->FORM->InterfaceErrors->text)){
            $message = $mes_arr->FORM->InterfaceErrors->text;
        }
        $order->update_meta_data('priority_recipe_status', $message);
        $order->save();
        WooAPI::instance()->sendEmailError(
            ['elisheva.g@simplyct.co.il',get_bloginfo('admin_email')],
            'Error upating order after payment',
            $response['body']
        );
    }
}

function change_woocommerce_order_button_text( $button_text ) {
    return 'לתשלום'; // New text for the checkout button
}
add_filter( 'woocommerce_order_button_text', 'change_woocommerce_order_button_text' );


add_filter( 'woocommerce_email_subject_customer_processing_order', function( $subject, $order ) {
    return 'התשלום שלך התקבל בהצלחה! מספר הסכם: ' .  get_post_meta($order->get_id(), 'billing_docno', true);
}, 10, 2 );


add_filter( 'woocommerce_email_order_items_args', function( $args ) {
    $args['show_sku'] = false; // Hide the default SKU
    return $args;
});

add_filter( 'woocommerce_order_item_name', function( $product_name, $item, $is_plain_text ) {
    $order_id = $item->get_order_id(); // Get the order ID
    
    // Replace 'custom_order_meta_key' with the actual meta key you're using
    $custom_name = get_post_meta( $order_id, 'billing_docno', true );

    // If custom meta exists, use it instead of the product name
    if ( ! empty( $custom_name ) ) {
        $product_name .= ' '.esc_html( $custom_name );
    }

    return $product_name;
}, 10, 3 );

add_filter( 'woocommerce_order_item_get_formatted_meta_data', function( $formatted_meta, $item ) {
    foreach ( $formatted_meta as $key => $meta ) {
        // List of meta keys to remove (change these to match your site's meta keys)
        $meta_keys_to_remove = array( 'product-ivnum', 'color', 'size' );

        if ( in_array( $meta->key, $meta_keys_to_remove ) ) {
            unset( $formatted_meta[$key] );
        }
    }
    return $formatted_meta;
}, 10, 2 );

add_filter( 'woocommerce_thankyou_order_received_text', function( $text, $order ) {
    $docno = get_post_meta( $order->get_id(), 'billing_docno', true );
    return 'תודה לך. תשלום עבור הסכם ' . esc_html( $docno ) . ' בוצע בהצלחה.';
}, 10, 2 );

add_filter('woocommerce_email_heading_customer_processing_order', function($heading, $email) {
    return ''; // Remove the heading by returning an empty string
}, 10, 2);



add_filter( 'woocommerce_order_item_get_formatted_meta_data', function( $formatted_meta, $item ) {
    return array(); // Return an empty array to remove all item meta
}, 10, 2 );


add_filter( 'gettext', 'custom_wc_terms_checkbox_text', 20, 3 );
function custom_wc_terms_checkbox_text( $translated_text, $text, $domain ) {
    if ( 'woocommerce' === $domain && 'I have read and agree to the website %s' === $text ) {
        $translated_text = 'קראתי ואני מסכים/מה ל%s'; // Space after "ל" removed
    }
    return $translated_text;
}




 ?>