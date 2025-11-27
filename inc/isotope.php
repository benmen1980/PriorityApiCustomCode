<?php 

use PriorityWoocommerceAPI\WooAPI;


//session_start();
add_filter( 'simply_request_customer_data', 'simply_get_customer_info');
function simply_get_customer_info($data){

    if ( isset($_GET['d'] ) ) {
        $data = simply_get_data_another_doc($data);
        return $data;
    }
   
    // make request to API
    $args = [];
    $servicecall = $data['docno']; //SO25000009
    $random =  $data['token'];
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
            wp_die(
                '<div style="text-align:center;">
                    <p>לא ניתן לשלם יותר באמצעות לינק זה, אנא פנה למחלקת הנח"ש באיזוטופ בטלפון <strong>08-8697282</strong> לקבלת לינק עדכני.</p>
                    <span>תודה<br>איזוטופ בע"מ</span>
                </div>',
                'שגיאה בתשלום'
            );
        }


    }else {
        WooAPI::instance()->sendEmailError(
            [],
            'Error getting service call info - TTS_ORDSPAY',
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

    if (isset($_GET['i'] )) 
        $fields['order']['order_comments']['default'] = $retrive_data['data']['cprofnum'];

    $fields['billing']['billing_first_name']['label'] = __('שם לקוח', 'woocommerce');
    $fields['billing']['billing_first_name']['class'] = array('form-row-wide');

    $fields['order']['order_comments']['placeholder'] = '';
    
    // Remove "order comment" field if
    if (isset($_GET['d'] ) && isset($fields['order']['order_comments'])) 
        unset($fields['order']['order_comments']); 

    // Remove "Last Name" field
    if (isset($fields['billing']['billing_last_name']))
        unset($fields['billing']['billing_last_name']);

    if (isset($fields['billing']['billing_postcode'])) 
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
    if ( isset($_POST['tranzila_udf9']) ) {
        update_post_meta( $order_id, '_tranzila_udf9', sanitize_text_field($_POST['tranzila_udf9']) );
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

    $order = wc_get_order($order_id);

    //update paid invoices in customer agreements
    if ( !empty( $order->get_meta('customer_agreement') ) ) {
        foreach ($order->get_items() as $item_id => $item) {
            // Attempt to get product-ivnum directly
            $ivnum = $item->get_meta('product-ivnum', true);
        }
        $url_addition = 'TTS_ORDNK(\'' . $ivnum . '\')/TTS_PAYEDIV_SUBFORM';

        $session_data = $order->get_meta('session_vars');
        $data = [];
        if ($session_data) {
            foreach ($session_data as $line) {
                $pay_ivnum = $line['refno'];
                $data  = [
                    'IVNUM' => $pay_ivnum,
                    'PAYNUM' => $order->get_meta('cc_company_approval_num'),
                ];

                //each line will be run and updated separately
                $response = WooAPI::instance()->makeRequest('POST', $url_addition, ['body' => json_encode($data)], true);
                if ($response['code'] <= 201 && $response['code'] >= 200 ) {

                    $order->update_meta_data('priority_recipe_status', 'שולם');
                    $order->update_meta_data('priority_recipe_number', $ivnum);
                    $order->save();

                    //save in DB by agreement number
                    $save_invoices = get_option('paid_invoice_numbers', []);
                    if (!is_array($save_invoices)) {
                        $save_invoices = [];
                    }
                    
                    if (!isset($save_invoices[$ivnum]) || !is_array($save_invoices[$ivnum])) {
                        $save_invoices[$ivnum] = [];
                    }

                    //save the invoice number paid in the DB
                    $save_invoices[$ivnum][] = $pay_ivnum;
                    update_option('paid_invoice_numbers', $save_invoices);

                    WooAPI::instance()->sendEmailError(
                        ['margalit.t@simplyct.co.il'],
                        'enter pay customer agreementafter payment',
                        'payment '.$order_id
                    );
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
                        ['margalit.t@simplyct.co.il'],
                        'Error upating pay customer agreementafter payment',
                        $response['body']
                    );
                }
            }
        }
        return; //exit from hook
    }

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
  
    // echo "<pre>";
    // print_r($data);
    // echo "</pre>";
    // die();
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
            ['elisheva.g@simplyct.co.il'],
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


// displaying the payment document with the details in the agreement certificate
// add_filter( 'simply_request_additional_doc', 'simply_get_data_another_doc');
function simply_get_data_another_doc($data){
    $payment_number = $data['docno']; //749002
    $token = $data['token']; //C10DC17F
    $key_contact =  $_GET['ct'] ;

    $data = [];

    // $url_addition = 'BOOKNUM eq \'' . $payment_number . '\'';
    $url_addition = 'BOOKNUM eq \'' . $payment_number . '\'';
    $select = '&$select=CUSTNAME,CUSTDES,BOOKNUM,ORDNAME,TTS_SUMIVS';
    $expand = '&$expand=TTS_PHONEORDNK_SUBFORM($filter=PHONEC eq \'' . $key_contact . '\' and ORDNUMGPAY eq \'' . $token .'\'; $select=NAME,CITYNAME,ADDRESS,CELLPHONE,EMAIL,PHONEC),TTS_RIVORDS_SUBFORM($filter=SENDLINK eq \'Y\' and IVPAID ne \'Y\'; $select=SENDLINK,TOTPRICE,TTS_ATOTCIV,IVDATE,TYPEWDES,IVNUM,BOOKNUM)';
    $response = WooAPI::instance()->makeRequest('GET', 'TTS_ORDNK?$filter='. $url_addition . $select . $expand .'', [], true);
 
    if ($response['code'] <= 201 && $response['code'] >= 200) {
        $data['data'] = [];

        $body_array = json_decode($response["body"],true);

        if(!empty($body_array['value'])){
            $user_details = $body_array['value'][0];

            $booknum = $user_details['BOOKNUM'];

            if (!empty($user_details['TTS_RIVORDS_SUBFORM'])) {
                $data['first_name'] = $user_details['CUSTDES'];
                $data['data']['custname'] = $user_details['CUSTNAME'];
                $data['data']['docno'] = $booknum;
                $data['price'] = $user_details['TTS_SUMIVS'];

                foreach ($user_details['TTS_PHONEORDNK_SUBFORM'] as $contact) {
                    $data['data']['contactname'] = $contact['NAME'];
                    $data['city'] = $contact['CITYNAME'];
                    $data['street'] = $contact['ADDRESS'];
                    $data['phone'] = $contact['CELLPHONE'];
                    $data['email'] = $contact['EMAIL'];
                }
                
                //check by DB if the invoice has not been paid yet
                $saved_invoices = get_option('paid_invoice_numbers', []);
                if (!isset($saved_invoices[$booknum])) {
                    $saved_invoices[$booknum] = []; 
                }

                foreach ($user_details['TTS_RIVORDS_SUBFORM'] as $inovice) {
                    $current_ivnum = $inovice['IVNUM'];

                    if ( in_array( $current_ivnum, $saved_invoices[$booknum] ) ) {
                        continue; 
                    }

                    $data['data']['invpayments'][] = [
                        'refno' => $current_ivnum,
                        'bookno' => $inovice['BOOKNUM'],
                        'price' => $inovice['TOTPRICE'],
                        'date' => $inovice['IVDATE'],
                        'details' => $inovice['TYPEWDES'],
                    ];
                }
            }
            else {
                wp_die('אין חשבוניות פתוחות לתשלום.');
            }
            if (empty($data['data']['invpayments'])) {
                wp_die('אין חשבוניות פתוחות לתשלום.');
            }
        }
        else {
            wp_die(
                '<div style="text-align:center;">
                    <p>לא ניתן לשלם יותר באמצעות לינק זה, אנא פנה למחלקת הנח"ש באיזוטופ בטלפון <strong>08-8697282</strong> לקבלת לינק עדכני.</p>
                    <span>תודה<br>איזוטופ בע"מ</span>
                </div>',
                'שגיאה בתשלום'
            );

        }
    }
    else {
        WooAPI::instance()->sendEmailError(
            ['margalit.t@simplyct.co.il'],
            'Error getting service call info',
            $response['body']
        );
    }
    return $data;
}

// adding invoice details by agreement number
add_action( 'woocommerce_checkout_before_order_review_heading', 'invoice_details_section_checkout' );
function invoice_details_section_checkout() {
    if ( isset($_GET['d']) ) {
        $retrive_data = WC()->session->get( 'session_vars' );
        echo '<div class="invoice-details-checkout-box">';
        echo '<h3>' . __( 'פרוט החשבוניות לתשלום', 'woocommerce' ) . '</h3>';
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __( 'תאריך', 'woocommerce' ) . '</th>';
        // echo '<th>' . __( 'מס\' חשבונית', 'woocommerce' ) . '</th>';
        echo '<th>' . __( 'מספר חשבון עסקה', 'woocommerce' ) . '</th>';
        echo '<th>' . __( 'סכום כולל מע"מ', 'woocommerce' ) . '</th>';
        echo '<th>' . __( 'פרטים נוספים', 'woocommerce' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ( $retrive_data['data']['invpayments'] as $line ) {
            echo '<tr>';
            echo '<td>' . esc_html__( date( 'd/m/y',strtotime($line['date'])), 'woocommerce' ) . '</td>';
            // echo '<td>' . esc_html__( $line['refno'], 'woocommerce' ) . '</td>';
            echo '<td>' . esc_html__( $line['bookno'], 'woocommerce' ) . '</td>';
            echo '<td>' . esc_html__( $line['price'], 'woocommerce' ) . '</td>';
            echo '<td>' . esc_html__( $line['details'], 'woocommerce' ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}

// adding a hidden field to the payment form if it is an agreement certificate
add_action('woocommerce_after_order_notes', function($checkout){
    $value = isset($_GET['d']) ? sanitize_text_field($_GET['d']) : '';
    if ( $value ) {
        echo '<input type="hidden" name="customer_agreement" value="' . esc_attr($value) . '" />';

        $retrive_data = WC()->session->get( 'session_vars' );
        $invoice_numbers = [];
        foreach ( $retrive_data['data']['invpayments'] as $line ) {
            $invoice_numbers[] = $line['bookno'];
        }
    }

    $udf9_value = ! empty($invoice_numbers) ? implode(', ', $invoice_numbers) : '';
    echo '<input type="hidden" name="tranzila_udf9" value="' . esc_attr($udf9_value) . '">';
});

// saving the field to the order meta
add_action('woocommerce_checkout_create_order', function($order, $data){
    if (isset($_POST['customer_agreement'])) {
        $order->update_meta_data('customer_agreement', sanitize_text_field($_POST['customer_agreement']));
        $session_data = WC()->session->get('session_vars');
        if ($session_data) {
            $order->update_meta_data('session_vars', $session_data['data']['invpayments']);
        }

    }
}, 20, 2);

?>