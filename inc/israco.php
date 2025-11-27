<?php 

use PriorityWoocommerceAPI\WooAPI;

add_filter( 'simply_request_customer_data', 'simply_get_customer_info');
function simply_get_customer_info($data){

    // make request to API
    $ivnum = $data['docno']; //T96
    $random =  $data['token'] ;
    $expand = '&$expand=TFNCITEMS_SUBFORM($select=FNCIREF1,DETAILS,CREDIT,FNCDATE),TINVOICESCONT_SUBFORM($select=EMAIL,STATEA,PHONE,AGENTNAME,ADRS)';
    $select = '&$select=CDES,IVNUM,ROYY_RAND,DISPRICE,NAME,IVTYPE,DEBIT';
    $url_addition = 'IVNUM eq \'' . $ivnum . '\' and ROYY_RAND eq \'' . $random .'\'';
    $response = WooAPI::instance()->makeRequest('GET', 'TINVOICES?$filter='. $url_addition . $expand . $select .'', [], true);
 
    if ($response['code'] <= 201 && $response['code'] >= 200) {
        $data['data'] = [];

        $body_array = json_decode($response["body"], true);

        if(!empty($body_array['value'])){

            $user_details = $body_array['value'][0];
            $contact_details = $user_details['TINVOICESCONT_SUBFORM'][0];
            $invoice_details = $user_details['TFNCITEMS_SUBFORM'];
            // populate the check out form
            $data['first_name'] = $user_details['CDES'];
            $data['city'] = $contact_details['STATEA'];
            $data['street'] = $contact_details['ADRS'];
            $data['phone'] = $contact_details['PHONE'];
            $data['price'] = $user_details['DISPRICE'];
            $data['email'] = $contact_details['EMAIL'];
            
            foreach ($user_details['TFNCITEMS_SUBFORM'] as $inovice) {
                $data['data']['invpayments'][] = [
                    'refno' => $inovice['FNCIREF1'],
                    'price' => $inovice['CREDIT'],
                    'date' => $inovice['FNCDATE'],
                    'details' => $inovice['DETAILS'],
                ];
            }
            $data['data']['contactname'] = $user_details['NAME'];
        }
        else{
            wp_die('לא ניתן לשלם עבור נתונים אלו, בדוק מספר הזמנה ומספר טוקן');
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

function custom_checkout_field( $fields ) {
    //add custom fields
    $retrive_data = WC()->session->get( 'session_vars' );
    $fields['billing']['billing_contact_name'] = array(
        'type'        => 'text',
        'label'       => __('איש הקשר', 'woocommerce'),
        'required'    => true,
        'default'     => $retrive_data['data']['contactname'],
        'class'       => array('form-row-wide'),
        'priority'    => 35, // Adjust position
    );

    // Remove fields
    if (isset($fields['billing']['billing_last_name'])) 
        unset($fields['billing']['billing_last_name']);

    if (isset($fields['billing']['billing_postcode'])) 
        unset($fields['billing']['billing_postcode']);

    if (isset($fields['billing']['billing_postcode'])) 
        unset($fields['billing']['billing_postcode']);

    //set field to required
    if ( isset($fields['billing']['billing_phone']) ) 
        $fields['billing']['billing_phone']['required'] = true; 

    //set all fields read only
    foreach ($fields as $section => $section_fields) {
        foreach ($section_fields as $key => $field) {
            $fields[$section][$key]['custom_attributes'] = array('readonly' => 'readonly');
            $fields[$section][$key]['class'][] = 'readonly-field';
            $fields[$section][$key]['required'] = false;
        }
    }

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'custom_checkout_field' );

function save_custom_checkout_field( $order_id ) {
    if ( ! empty( $_POST['billing_contact_name'] ) ) {
        update_post_meta( $order_id, 'billing_contact_name', sanitize_text_field( $_POST['billing_contact_name'] ) );
    }
}
add_action( 'woocommerce_checkout_update_order_meta', 'save_custom_checkout_field' );

function display_custom_field_in_admin( $order ) {
    $billing_contact_name = get_post_meta( $order->get_id(), 'billing_contact_name', true );
    if ( $billing_contact_name ) {
        echo '<p><strong>' . __( 'איש הקשר', 'woocommerce' ) . ':</strong> ' . esc_html( $billing_contact_name ) . '</p>';
    }
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_custom_field_in_admin', 10, 1 );

// Remove shipping adress fields and the area
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

// Remove order notes fields
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );


function invoice_details_section_checkout() {
    $retrive_data = WC()->session->get( 'session_vars' );
    echo '<div class="invoice-details-checkout-box">';
    echo '<h3>' . __( 'פירוט חשבוניות לתשלום:', 'woocommerce' ) . '</h3>';
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . __( 'סכום החשבונית', 'woocommerce' ) . '</th>';
    echo '<th>' . __( 'אסמכתא', 'woocommerce' ) . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ( $retrive_data['data']['invpayments'] as $line ) {
        echo '<tr>';
        echo '<td>' . wc_price( $line['price'] ) . '</td>';
        echo '<td>' . esc_html__( $line['refno'], 'woocommerce' ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}
add_action( 'woocommerce_checkout_before_order_review_heading', 'invoice_details_section_checkout' );

function set_default_shipping_country() {
    return 'IL'; 
}
add_filter( 'default_checkout_shipping_country', 'set_default_shipping_country' );

// New text for the checkout button
function change_woocommerce_order_button_text( $button_text ) {
    return 'לתשלום'; 
}
add_filter( 'woocommerce_order_button_text', 'change_woocommerce_order_button_text' );

//sync order for recipt and closing in priority
function syncPaymentAfterOrder($order_id){
    WooAPI::instance()->sendEmailError(
        ['margalit.t@simplyct.co.il',get_bloginfo('admin_email')],
        'enter sync payment',
        'payment'.$order_id
    );
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item_id => $item) {
        // Attempt to get product-ivnum directly
        $tinv_number = $item->get_meta('product-ivnum', true);
    }    

    $card_brand = $order->get_meta('_cardBrand') ?? 'default';
    if ( $card_brand == 'PrivateLabel' ) {
        $card_brand = $order->get_meta('_cardAcquirer') ?? 'default';
    }

    switch ($card_brand) {
        //isracart
        case 'Isracard':
            $payment_code = '14';
            break;
        //diners
        case 'Diners':
            $payment_code = '16';
            break;
        //american express
        case 'Amex':
            $payment_code = '20';
            break;
        //visa cal
        case 'Visa':
            $payment_code = '12';
            break;
        //max
        case 'Mastercard':
            $payment_code = '14';
            break;
        // default
        case 'default':
            $payment_code = '7';
            break;
        default:
            $payment_code = '7';
            break;
    }

    $url_addition = 'TINVOICES(IVNUM=\'' . $tinv_number . '\',IVTYPE=\'T\',DEBIT=\'D\')';
    $data['TPAYMENT2_SUBFORM'][]  = [
        'PAYMENTCODE' => $payment_code,
        'QPRICE'      => floatval($order->get_total()),
        'PAYACCOUNT'  => substr( $order->get_meta('_cardMask'), strlen( $order->get_meta('_cardMask') ) - 4, 4) ?? '',
        'VALIDMONTH'  => $order->get_meta('_cardExp') ?? '',
        'CCUID'       => $order->get_meta('_cardToken') ?? '',
        'CONFNUM'     => $order->get_meta('_authNumber') ?? '',
        // 'PAYCODE'  => (string)$order->get_meta('_numberOfPayments') ?? '1',
        'PAYDATE'     => date('Y-m-d'),
    ];

    $response = WooAPI::instance()->makeRequest('PATCH', $url_addition, ['body' => json_encode($data)], true);
    if ($response['code'] <= 201 && $response['code'] >= 200 ) {
        $order->update_meta_data('priority_recipe_status', 'שולם');
        $order->update_meta_data('priority_recipe_number', $tinv_number);
        $res_data = close_receipt_priority($tinv_number);
        if (isset($res_data)) {
            $order->update_meta_data('priority_recipe_number', $res_data['ivnum']);
            if (substr($res_data['ivnum'], 0, 2) === "RC") { 
                $order->update_meta_data('priority_recipe_status', 'סגורה');
            } else {
                $order->update_meta_data('priority_recipe_status', '<strong>שגיאה בסגירת הקבלה:</strong> ' . $res_data['mssg']);
            }
        }
        $order->save();
        WooAPI::instance()->sendEmailError(
            ['margalit.t@simplyct.co.il',get_bloginfo('admin_email')],
            'enter success payment',
            'payment'.$order_id
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
            ['margalit.t@simplyct.co.il',get_bloginfo('admin_email')],
            'Error upating order after payment',
            $response['body']
        );
    }
}
add_action('woocommerce_payment_complete', 'syncPaymentAfterOrder', 99999);

function close_receipt_priority($tinv_number)
{   
    // update_receipt_status($otc_number);
    $username = WooAPI::instance()->option('username');
    $password = WooAPI::instance()->option('password');
    $url = 'https://'.WooAPI::instance()->option('url');
    if( false !== strpos( $url, 'p.priority-connect.online' ) ) {
        $url = 'https://p.priority-connect.online/wcf/service.svc';
    }
    $tabulaini = WooAPI::instance()->option('application');
    $company = WooAPI::instance()->option('environment');
    $appid = WooAPI::instance()->option('X-App-Id');
    $appkey = WooAPI::instance()->option('X-App-Key');

    $data['IVNUM'] = $tinv_number;
    $data['credentials']['appname'] = 'demo';
    $data['credentials']['username'] = $username;
    $data['credentials']['password'] = $password;
    $data['credentials']['url'] = $url;
    $data['credentials']['tabulaini'] = $tabulaini;
    $data['credentials']['language'] = '1';
    $data['credentials']['profile']['company'] = $company;
    $data['credentials']['devicename'] = 'roy';
    $data['credentials']['appid'] = $appid;
    $data['credentials']['appkey'] = $appkey;

    $curl = curl_init();
    curl_setopt_array($curl, 
        array(
            CURLOPT_URL => 'http://prinodehub1-env.eba-gdu3xtku.us-west-2.elasticbeanstalk.com/closeTinvoices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    $response_data = json_decode($response, true);
    $res = curl_getinfo($curl);
    $res_data = [];
    if ($res['http_code'] <= 201) {
        if (isset($response_data['ivnum'])) {
            $res_data = [
                'ivnum' => $response_data['ivnum'],
                'mssg'  => $response_data['message'],
            ];
        }
    } else {
        if (isset($response_data['message'])) {
            $res_data = [
                'ivnum' => $tinv_number,
                'mssg'  => $response_data['message'],
            ];
        } else {
            $res_data = [
                'ivnum' => $tinv_number,
                'mssg'  => "No error message found.",
            ];
        }
    }
    curl_close($curl);
    return $res_data;

}


//replace the word "order" with the word "payment" in every reference
add_filter('gettext', 'custom_wc_text_changes', 20, 3);
function custom_wc_text_changes($translated_text, $text, $domain) {
    // sure it's WooCommerce only
    if ($domain === 'woocommerce') {

        if ($translated_text === 'תודה לך. ההזמנה התקבלה בהצלחה.') {
            $translated_text = 'תודה לך. התשלום התקבל בהצלחה.';
        }
        if ($translated_text === 'מספר הזמנה') {
            $translated_text = 'מספר תשלום';
        }
        if ($translated_text === 'ההזמנה התקבלה') {
            $translated_text = 'התשלום התקבל';
        }
        if ($translated_text === 'אישור הזמנה') {
            $translated_text = 'אישור תשלום';
        }
        if ($translated_text === 'הזמנה מספר') {
            $translated_text = 'תשלום מספר';
        }
        if ($translated_text === 'פרטי הזמנה') {
            $translated_text = 'פרטי התשלום';
        }

        // General replacement of the word "order" with "payment"
        $translated_text = str_replace('הזמנה', 'תשלום', $translated_text);
    }

    return $translated_text;
}