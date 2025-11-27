<?php 

use PriorityWoocommerceAPI\WooAPI;

add_filter( 'simply_request_customer_data', 'simply_get_customer_info');
function simply_get_customer_info($data){

    // make request to API
    $ivnum = $data['docno']; //PI250000016
    $random =  $data['token'];
    $currency = $_GET['currency'] ?? '';
    $select = '&$select=CUSTNAME,CDES,IVNUM,ROYY_RAND,TOTPRICE,NAME,CODE,Y_9643_5_ESH,IVCODENAME,IVTYPE,DEBIT';
    $expand = '&$expand=RINVOICEITEMS_SUBFORM($select=PARTNAME,PDES,TOTPRICE,VPRICE,QUANT,ICODE),RINVOICESCONT_SUBFORM($select=ADRS,PHONE,PAYDATE)';
    $url_addition = 'IVNUM eq \'' . $ivnum . '\' and ROYY_RAND eq \'' . $random .'\'';
    $response = WooAPI::instance()->makeRequest('GET', 'RINVOICES?$filter='. $url_addition . $select . $expand .'', [], true);
    
    if ($response['code'] <= 201 && $response['code'] >= 200) {
        $data['data'] = [];
        
        $body_array = json_decode($response['body'], true);

        if(!empty($body_array['value'])){

            $user_details = $body_array['value'][0];
            $contact_details = $user_details['RINVOICESCONT_SUBFORM'][0];
            
            // populate the check out form
            $data['first_name'] = $user_details['CDES'];
            $data['price'] = $user_details['TOTPRICE'];

            global $WOOCS;
            if ( !isset($_GET['currency']) ) {                
                $wanted_currency = ($user_details['CODE'] === '$') ? 'USD' : 'ILS';
                $WOOCS->set_currency( $wanted_currency );
                $currency = $wanted_currency;
            }
            
            if ($currency === 'USD' && $user_details['CODE'] === 'ש"ח') {
                $data_price = $WOOCS->convert_from_to_currency($user_details['TOTPRICE'], 'USD', 'ILS');
                // $data_price = convert_my_currency($user_details['TOTPRICE'], 'ILS', 'USD');
                $data['price'] = $data_price;
            }
            if ($currency === 'ILS' && $user_details['CODE'] === '$') {
                $data_price = $WOOCS->convert_from_to_currency($user_details['TOTPRICE'], 'ILS', 'USD');
                // $data_price = convert_my_currency($user_details['TOTPRICE'], 'USD', 'ILS');
                $data['price'] = $data_price;
            }            
            $data['email'] = $user_details['ROYY_EMAIL'];
            $data['data']['contactname'] = $user_details['NAME'];
            $data['data']['custname'] = $user_details['CUSTNAME'];
            $data['data']['docno'] = $user_details['IVNUM'];
            $data['data']['type'] = ($user_details['IVCODENAME'] === '3RD') ? 1 : 0;
            $paydate = date('d/m/y', strtotime($contact_details['PAYDATE']));
            $data['data']['date'] = $paydate;
            $data['data']['currency'] = $user_details['CODE'];
            
            foreach ($user_details['RINVOICEITEMS_SUBFORM'] as $inovice) {
                $price_item = $inovice['VPRICE'];
                if ($currency === 'USD' && $user_details['CODE'] === 'ש"ח') {
                    $price_item = $WOOCS->convert_from_to_currency($inovice['VPRICE'], 'USD', 'ILS');
                }
                if ($currency === 'ILS' && $user_details['CODE'] === '$') {
                    $price_item = $WOOCS->convert_from_to_currency($inovice['VPRICE'], 'ILS', 'USD');
                }   
                $data['data']['invpayments'][] = [
                    'refno' => $inovice['PARTNAME'],
                    'description' => $inovice['PDES'],
                    'quant' => $inovice['QUANT'],
                    'price' => $price_item,
                    'code' => ($currency === 'USD') ? '$' : 'ש"ח',
                ];
            }
        }
        else {
            wp_die(
                '<div style="text-align:center;">
                    <p>Payment can no longer be made using this link. Please contact the company’s accounting department at <strong>00-0000000</strong> to receive an updated link.</p>
                    <span>Thank you,<br>PHILIP STEIN</span>
                </div>',
                'Payment error'
            );
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
    
    $fields['billing']['billing_custname'] = array(
        'type'        => 'text',
        'label'       => __('Customer number', 'woocommerce'),
        'required'    => true,
        'default'     => $retrive_data['data']['custname'],
        'class'       => array('form-row-wide'),
        'priority'    => 35, // Adjust position
    );
    /*$fields['billing']['billing_contact_name'] = array(
        'type'        => 'text',
        'label'       => __('Contact', 'woocommerce'),
        'required'    => true,
        'default'     => $retrive_data['data']['contactname'],
        'class'       => array('form-row-wide'),
        'priority'    => 37, // Adjust position
    );*/
    $fields['billing']['billing_docno'] = array(
        'type'        => 'text',
        'label'       => __('Invoice Number', 'woocommerce'),
        'required'    => true,
        'default'     => $retrive_data['data']['docno'],
        'class'       => array('form-row-wide'),
        'priority'    => 36, // Adjust position
    );
    $fields['billing']['billing_duedate'] = array(
        'type'        => 'text',
        'label'       => __('Due by Date', 'woocommerce'),
        'required'    => true,
        'default'     => $retrive_data['data']['date'],
        'class'       => array('form-row-wide'),
        'priority'    => 37, // Adjust position
    );

    // add a hidden order type field
    $fields['billing']['order_type'] = array(
        'type'        => 'hidden',
        'default'     => $retrive_data['data']['type'],
        'class'       => array('form-row-wide'),
        'priority'    => 120, // Adjust position
    );

    // Change label for fields
    $fields['billing']['billing_first_name']['label'] = 'Payer Customer Name';

    // Remove fields
    if (isset($fields['billing']['billing_last_name'])) 
        unset($fields['billing']['billing_last_name']);

    if (isset($fields['billing']['billing_address_1'])) 
        unset($fields['billing']['billing_address_1']);

    if (isset($fields['billing']['billing_city'])) 
        unset($fields['billing']['billing_city']);

    if (isset($fields['billing']['billing_postcode'])) 
        unset($fields['billing']['billing_postcode']);

    if (isset($fields['billing']['billing_phone'])) 
        unset($fields['billing']['billing_phone']);

    //set all fields read only
    foreach ($fields as $section => $section_fields) {
        foreach ($section_fields as $key => $field) {
            if ( $key !== 'billing_email' ) {
                $fields[$section][$key]['custom_attributes'] = array('readonly' => 'readonly');
                $fields[$section][$key]['class'][] = 'readonly-field';
                $fields[$section][$key]['required'] = false;
            }
        }
    }

    return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'custom_checkout_field' );

// add a hidden original currency field
function add_custom_field( $checkout ) {
    if (function_exists('get_woocommerce_currency')) {
        $retrive_data = WC()->session->get( 'session_vars' );
        $original_currency = ($retrive_data['data']['currency'] === '$') ? 'USD' : 'ILS';
        echo '<input type="hidden" name="original_currency" value="' . esc_attr($original_currency) . '">';
    }
};
add_action('woocommerce_after_order_notes', 'add_custom_field');

// Remove shipping adress fields and the area
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

// Remove order notes fields
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

// Save custom checkout fields to order meta
add_action( 'woocommerce_checkout_create_order', 'save_custom_checkout_field', 10, 2 );
function save_custom_checkout_field( $order, $data ) {

    if ( ! empty( $_POST['billing_custname'] ) ) {
        $order->update_meta_data( 'billing_custname', sanitize_text_field( $_POST['billing_custname'] ) );
    }

    if ( ! empty( $_POST['billing_contact_name'] ) ) {
        $order->update_meta_data( 'billing_contact_name', sanitize_text_field( $_POST['billing_contact_name'] ) );
    }

    if ( ! empty( $_POST['billing_docno'] ) ) {
        $order->update_meta_data( 'billing_docno', sanitize_text_field( $_POST['billing_docno'] ) );
    }

    if ( ! empty( $_POST['billing_duedate'] ) ) {
        $order->update_meta_data( 'billing_duedate', sanitize_text_field( $_POST['billing_duedate'] ) );
    }

    if ( isset( $data['order_type'] ) ) {
        $order->update_meta_data( '_order_type_priority', sanitize_text_field( $data['order_type'] ) );
    }

    // updating the invoice details (invpayments array) in the order meta.
    $retrive_data = WC()->session->get( 'session_vars' );
    if ( ! empty( $retrive_data['data']['invpayments'] ) ) {
        $order->update_meta_data( '_invoice_items_lines', $retrive_data['data']['invpayments'] );
    }

    // update and save source currency
    if (!empty($_POST['original_currency'])) {
        $order->update_meta_data('_original_currency', sanitize_text_field($_POST['original_currency']));
    }
}

// add area to detail invoices
function invoice_details_section_checkout() {
    $retrive_data = WC()->session->get( 'session_vars' );
    echo '<div class="invoice-details-checkout-box">';
    echo '<h3>' . __( 'Invoice details for payment', 'woocommerce' ) . '</h3>';
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . __( 'Invoice Number (Reference)', 'woocommerce' ) . '</th>';
    echo '<th>' . __( 'Product Description', 'woocommerce' ) . '</th>';
    echo '<th>' . __( 'Quantity', 'woocommerce' ) . '</th>';
    echo '<th>' . __( 'Total Price', 'woocommerce' ) . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ( $retrive_data['data']['invpayments'] as $line ) {      
        echo '<tr>';
        echo '<td>' . esc_html__( $line['refno'], 'woocommerce' ) . '</td>';
        echo '<td>' . esc_html__( $line['description'], 'woocommerce' ) . '</td>';
        echo '<td>' . esc_html__( $line['quant'], 'woocommerce' ) . '</td>';
        echo '<td>' . wc_price( $line['price'] ) . '</td>';
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

// sync order after change status
add_action( 'woocommerce_payment_complete', 'syncPaymentAfterOrder', 9999 );
function syncPaymentAfterOrder( $order_id ) {

    $order = wc_get_order($order_id);
    $order_type_priority = $order->get_meta( '_order_type_priority' );
    $cust_number = $order->get_meta( 'billing_custname' );
    $paid_currency = $order->get_currency();
    $original_currency = get_post_meta($order_id, '_original_currency', true);

    foreach ($order->get_items() as $item_id => $item) {
        // Attempt to get product-ivnum directly
        $ivnum = $item->get_meta('product-ivnum', true);
    }

    if ( $order_type_priority == 1 ) {
       
        $data = [
            'ACCNAME' => $cust_number,
            'CDES'     => $order->get_billing_first_name(),
            'IVDATE'   => date('Y-m-d', strtotime($order->get_date_created())),
            'BOOKNUM'  => $order->get_order_number(),
            'CODE'     => ($paid_currency === 'USD') ? '$' : 'ש"ח',   
        ];

        // billing customer details
        $customer_data = [
            'EMAIL' => $order->get_billing_email(),
        ];
        $data['TINVOICESCONT_SUBFORM'][] = $customer_data;

        
        // payment info
        $order_cc_meta = $order->get_meta('_transaction_data');
        $payments = $order_cc_meta['TotalPayments'] ?? 'default';
        switch ($payments) {
            case 1:
                $total_payments = 11;
                break;
            case 2:
                $total_payments = 12;
                break;
            case 3:
                $total_payments = 13;
                break;
            case 4:
                $total_payments = 14;
                break;
            // default
            case 'default':
                $total_payments = 11;
                break;
            default:
                $total_payments = 11;
                break;
        }
        $data['TPAYMENT2_SUBFORM'][]  = [
            'PAYMENTCODE' => ($paid_currency === 'USD') ? '11' : '10',
            'QPRICE'      => floatval($order->get_total()),
            'PAYACCOUNT'  => substr( $order_cc_meta['CreditCardNumber'], strlen( $order_cc_meta['CreditCardNumber'] ) - 4, 4) ?? '',
            'VALIDMONTH'  => $order_cc_meta['CreditCardExpDate'] ?? '',
            'CCUID'       => $order_cc_meta['Token'] ?? '',
            'CONFNUM'     => $order_cc_meta['ConfirmationKey'] ?? '',
            'PAYCODE'     => (string)$total_payments,
            'PAYDATE'     => date('Y-m-d'),
        ]; 
        // $data['TFNCITEMS_SUBFORM'][]  = [
        //     'FNCIREF1' => $ivnum,
        //     'CREDIT' => floatval($order->get_total()),
        // ]; 
        $order->update_meta_data('request_receipt', json_encode($data));

        // make request
        $response = WooAPI::instance()->makeRequest('POST', 'TINVOICES', ['body' => json_encode($data, JSON_UNESCAPED_SLASHES)], WooAPI::instance()->option('log_receipts_priority', true));
        
        if ($response['code'] <= 201 && $response['code'] >= 200) {
            $body_array = json_decode($response['body'], true);
            $ord_status = $body_array['STATDES'];
            $ivnum_number = $body_array['IVNUM'];
            $order->update_meta_data('priority_recipe_status', $ord_status);
            $order->update_meta_data('priority_recipe_number', $ivnum_number);
            $approve_ivnum = mark_rinvoice_payment($ivnum_number, $ivnum);
            if ( $approve_ivnum ) {
                $order->update_meta_data('priority_recipe_status', $approve_ivnum);
                $order->update_meta_data('priority_approve_recipe_status', $approve_ivnum);
                $adjust_diff_ivnum = adjust_diff_rinvoice_payment($ivnum_number, $ivnum, floatval($order->get_total()));
                if ( $adjust_diff_ivnum ) {
                    $order->update_meta_data('priority_recipe_status', $adjust_diff_ivnum);
                    $order->update_meta_data('priority_adjust_diff_recipe_status', $adjust_diff_ivnum);
                    $res_data = close_receipt_priority($ivnum_number);
                    if (isset($res_data)) {
                        $order->update_meta_data('priority_recipe_number', $res_data['ivnum']);
                        if (substr($res_data['ivnum'], 0, 3) === '3RC') { 
                            $order->update_meta_data('priority_recipe_status', 'סגורה');
                        } else {
                            $order->update_meta_data('priority_recipe_status', '<strong>receipt close error: </strong> ' . $res_data['mssg']);
                            WooAPI::instance()->sendEmailError(
                                ['Didi@pstein.com', 'Putter.Yael@pstein.com'],
                                'Error Sync of receipt closing',
                                $res_data['mssg']
                            );
                        }
                    }
                }
            }
            $order->save();            
        } 
        else {
            $message = $response['message'] . '<br>' . $response['body'] . '<br>';
            $mes_arr = json_decode($response['body']);
            if(isset($mes_arr->FORM->InterfaceErrors->text)){
                $message = $mes_arr->FORM->InterfaceErrors->text;
            }
            $order->update_meta_data('priority_recipe_status', $message);
            $order->save();
            WooAPI::instance()->sendEmailError(
                ['Didi@pstein.com', 'Putter.Yael@pstein.com'],
                'Error Sync in creating receipt',
                $message
            );
        }
    }
    elseif ( $order_type_priority == 0 ) {

        if ( $paid_currency != $original_currency ) {

            $data = [
                'CUSTNAME' => $cust_number,
                'CDES'     => $order->get_billing_first_name(),
                'IVDATE'   => date('Y-m-d', strtotime($order->get_date_created())),
                'BOOKNUM'  => $order->get_order_number(),
                'ROYY_IVNUM'   => $ivnum,
                'CODE'     => ($paid_currency === 'USD') ? '$' : 'ש"ח',       
                'CASHNAME' => '105',         
            ];
                  
            // get ordered items
            foreach ($order->get_meta('_invoice_items_lines') as $item) {  
                $code_cuopon = $item['code'];
                $data['EINVOICEITEMS_SUBFORM'][] = [
                    'PARTNAME'  => $item['refno'],
                    'PDES'      => $item['description'],
                    'TQUANT'    => (int)$item['quant'],
                    'VPRICE'     => $item['price'],
                    'ICODE'     => $item['code'],
                ];
            }

            //Calculate the differences if any
            $products = $order->get_meta('_invoice_items_lines');
            $order_total = (float) $order->get_total();

            $total_price = array_sum(
                array_map(function($item){
                    $price = round((float)$item['price'], 2); // Rounding unit price
                    $qty   = isset($item['quant']) ? (int)$item['quant'] : 1; // Quantity
                    return $price * $qty; // Doubling after rounding
                }, $products)
            );


            if ( $total_price !== $order_total ) {
                $diff = round($order_total - $total_price, 2);
                if ($diff >= 0) {
                    $prc = $diff;      
                    $qty = 1;          
                } else {
                    $prc = abs($diff);  
                    $qty = -1;        
                }

                $data['EINVOICEITEMS_SUBFORM'][] = [
                    WooAPI::instance()->get_sku_prioirty_dest_field() =>  'ADJ',
                    'TQUANT' => $qty,
                    'TOTPRICE' => $prc,
                    'ICODE'     => $code_cuopon,
                ];
            }
        } else {
            $data = [
                'RIVNUM'   => $ivnum,
                'BOOKNUM'  => $order->get_order_number(),
                'CASHNAME' => '105',
                // 'ROYY_IVNUM'   => $ivnum,
            ];
        }

        // billing customer details
        $customer_data = [
            'EMAIL' => $order->get_billing_email(),
        ];
        $data['EINVOICESCONT_SUBFORM'][] = $customer_data;

        
        // payment info
        if ( $order->get_total() > 0.0 ) {           
            $order_cc_meta = $order->get_meta('_transaction_data');
            $payments = $order_cc_meta['TotalPayments'] ?? 'default';
            switch ($payments) {
                case 1:
                    $total_payments = 11;
                    break;
                case 2:
                    $total_payments = 12;
                    break;
                case 3:
                    $total_payments = 13;
                    break;
                case 4:
                    $total_payments = 14;
                    break;
                // default
                case 'default':
                    $total_payments = 11;
                    break;
                default:
                    $total_payments = 11;
                    break;
            }

            $data['EPAYMENT2_SUBFORM'][]  = [
                'PAYMENTCODE' => ($paid_currency === 'USD') ? '11' : '10',
                'CASHNAME'    => ($paid_currency === 'USD') ? '106' : '105',  
                'QPRICE'      => floatval($order->get_total()),
                'PAYACCOUNT'  => substr( $order_cc_meta['CreditCardNumber'], strlen( $order_cc_meta['CreditCardNumber'] ) - 4, 4) ?? '',
                'VALIDMONTH'  => $order_cc_meta['CreditCardExpDate'] ?? '',
                'CCUID'       => $order_cc_meta['Token'] ?? '',
                'CONFNUM'     => $order_cc_meta['ConfirmationKey'] ?? '',
                'PAYCODE'     => (string)$total_payments,
                'PAYDATE'     => date('Y-m-d'),
            ];
        }
        // print_r(json_encode($data));
        $order->update_meta_data('request_oct', json_encode($data));

        // make request
        $response = WooAPI::instance()->makeRequest('POST', 'EINVOICES', ['body' => json_encode($data)], true);
    
        if($response['code'] <= 201 && $response['code'] >= 200) {
            $body_array = json_decode($response['body'], true);
            $ord_status = $body_array['STATDES'];
            $ivnum_number = $body_array['IVNUM'];
            $order->update_meta_data('priority_recipe_status', $ord_status);
            $order->update_meta_data('priority_recipe_number', $ivnum_number);
            $res_data = close_otcinvoice_priority($ivnum_number);
            if (isset($res_data)) {
                $order->update_meta_data('priority_recipe_number', $res_data['ivnum']);
                if (substr($res_data['ivnum'], 0, 2) === 'OV') { 
                    $order->update_meta_data('priority_recipe_status', 'סגורה');
                } else {
                    $order->update_meta_data('priority_recipe_status', '<strong>otc close error: </strong> ' . $res_data['mssg']);
                    WooAPI::instance()->sendEmailError(
                        ['Didi@pstein.com', 'Putter.Yael@pstein.com'],
                        'Error Sync of OTC closing',
                        $res_data['mssg']
                    );
                }
            }
            $order->save();
        }
        else {
            $message = $response['message'] . '' . json_encode($response);
            $message = $response['message'] . '<br>' . $response['body'] . '<br>';
            $mes_arr = json_decode($response['body']);
            if(isset($mes_arr->FORM->InterfaceErrors->text)){
                $message = $mes_arr->FORM->InterfaceErrors->text;
            }
            $order->update_meta_data('priority_recipe_status', $message);
            $order->save();
            WooAPI::instance()->sendEmailError(
                ['Didi@pstein.com', 'Putter.Yael@pstein.com'],
                'Error Sync in creating OTC',
                $message
            );
        }
    }
}

function mark_rinvoice_payment($tivnum, $rivnum)
{
    // make request
    $get_res = WooAPI::instance()->makeRequest('GET', 'TINVOICES(IVNUM=\''.$tivnum.'\',IVTYPE=\'T\',DEBIT=\'D\')/TFNCITEMS3_SUBFORM', [], true);
    
    if ($get_res['status'] && $get_res['code'] == 200) {
        $get_res_data = json_decode($get_res['body_raw'], true);      
        if ($get_res_data['value'] > 0) {
            $found = null;
            foreach ($get_res_data['value'] as $item) {
                if ($item['IVNUM'] === $rivnum) {
                    $found = $item;
                    break;
                }
            }
            if ($found) {
                $fnctrans = $found['FNCTRANS'];
                $kline = $found['KLINE'];
                $pdata = [
                    'PAYFLAG' => 'Y',
                ];

                $url_addition = 'TINVOICES(IVNUM=\''.$tivnum.'\',IVTYPE=\'T\',DEBIT=\'D\')/TFNCITEMS3_SUBFORM(FNCTRANS='.$fnctrans.',KLINE='.$kline.')';
                $patch_res = WooAPI::instance()->makeRequest('PATCH', $url_addition, ['body' => json_encode($pdata)], true);
                if ($patch_res['code'] <= 201 && $patch_res['code'] >= 200 ) {
                    $status = "Invoice update for payment completed successfully.";
                }
                else {
                    $mes_arr = json_decode($patch_res['body']);
                    $message = $patch_res['message'] . '' . json_encode($patch_res);
                    $message = $patch_res['message'] . '<br>' . $patch_res['body'] . '<br>';
                    if(isset($mes_arr->FORM->InterfaceErrors->text)){
                        $status = $mes_arr->FORM->InterfaceErrors->text;
                    }  
                    WooAPI::instance()->sendEmailError(
                        ['Didi@pstein.com', 'Putter.Yael@pstein.com'],
                        'Error - Invoice update for payment failed',
                        $status
                    );                  
                }
                return $status;
            }
        }
        else {
            WooAPI::instance()->sendEmailError(
                ['Didi@pstein.com', 'Putter.Yael@pstein.com'],
                'No matching open receipt was found.',
                $get_res_data['body']
            );
        }
    }
}

function adjust_diff_rinvoice_payment($tivnum, $rivnum, $credit)
{
    // make request
   $get_tfncitems = WooAPI::instance()->makeRequest('GET', 'TINVOICES(IVNUM=\''.$tivnum.'\',IVTYPE=\'T\',DEBIT=\'D\')/TFNCITEMS_SUBFORM', [], true);
    
    if ($get_tfncitems['status'] && $get_tfncitems['code'] == 200) {
        $get_tfncitems_data = json_decode($get_tfncitems['body_raw'], true);      
        if ($get_tfncitems_data['value'] > 0) {
            $found = null;
            foreach ($get_tfncitems_data['value'] as $item) {
                if ($item['FNCIREF1'] === $rivnum) {
                    $found = $item;
                    break;
                }
            }
            print_r($found);
            if ($found) {
                $fnctrans = $found['FNCTRANS'];
                $kline = $found['KLINE'];
                $fncitems_data = [
                    'CREDIT' => $credit,
                    'PDACCNAME' => '9999',
                ];

                $url_addition_repeat = 'TINVOICES(IVNUM=\''.$tivnum.'\',IVTYPE=\'T\',DEBIT=\'D\')/TFNCITEMS_SUBFORM(FNCTRANS='.$fnctrans.',KLINE='.$kline.')';
                $patch_repeat = WooAPI::instance()->makeRequest('PATCH', $url_addition_repeat, ['body' => json_encode($fncitems_data)], true);
                if ($patch_repeat['code'] <= 201 && $patch_repeat['code'] >= 200 ) {
                    $status = "Adjusting the invoice difference due to currency change.";
                }
                else {
                    $mes_arr = json_decode($patch_repeat['body']);
                    $message = $patch_repeat['message'] . '' . json_encode($patch_repeat);
                    $message = $patch_repeat['message'] . '<br>' . $patch_repeat['body'] . '<br>';
                    if(isset($mes_arr->FORM->InterfaceErrors->text)){
                        $status = $mes_arr->FORM->InterfaceErrors->text;
                    }  
                    WooAPI::instance()->sendEmailError(
                        ['Didi@pstein.com', 'Putter.Yael@pstein.com'],
                        'Error - Invoice update for payment failed',
                        $status
                    );                  
                }
                return $status;
            }
        }
        else {
            WooAPI::instance()->sendEmailError(
                ['Didi@pstein.com', 'Putter.Yael@pstein.com'],
                'No matching open receipt was found.',
                $get_res_data['body']
            );
        }
    }
}

function close_receipt_priority($ivnum_number)
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

    $data['IVNUM'] = $ivnum_number;
    $data['credentials']['appname'] = 'demo';
    $data['credentials']['username'] = 'JULIE101';
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

function close_otcinvoice_priority($ivnum_number)
{   
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

    $data['IVNUM'] = $ivnum_number;
    $data['credentials']['appname'] = 'demo';
    $data['credentials']['username'] = 'JULIE101';
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
            CURLOPT_URL => 'http://prinodehub1-env.eba-gdu3xtku.us-west-2.elasticbeanstalk.com/closeEinvoices',
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

// shortcode WOOCS
add_action( 'woocommerce_review_order_before_submit', function() {
    echo '<div class="custom-currency-switcher">';
        echo '<span>Select the currency for payment: </span>';
        echo do_shortcode('[woocs txt_type="desc" show_flags=0 width="300px"]'); // כאן השורטקוד של WOOCS
    echo '</div>';
}, 10 );

/**
 * sync currency conversion rate from Priority
 * 
 */
function syncCurrencyPriority() {
    // Get all Currencies from Priority
    $response = WooAPI::instance()->makeRequest('GET', 'CURRENCIES?$select=NAME,EXCH,EXCHQUANT,CODE,ECODE&$filter=(ECODE eq \'USD\' or ECODE eq \'ILS\') ', [], true );
    
    // check response
    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);      
        if ($response_data['value'] > 0) {
            $woocs_rates = get_option( 'woocs' );
            foreach ($response_data['value'] as $currencies) {

                if ( $currencies['ECODE'] === 'USD' ) {
                    foreach ( $woocs_rates as $key => $rate ) {
                        if ( $rate['name'] === "USD") {
                            $woocs_rates[$key]['rate'] = $currencies['EXCH'];
                        }
                    }
                    // update_option( 'usd_rate', $currencies['EXCH'] );
                }
                if ( $currencies['ECODE'] === 'ILS' ) {
                    // update_option( 'ils_rate', $currencies['EXCH'] );
                    foreach ( $woocs_rates as $key => $rate ) {
                        if ( $rate['name'] === "ILS") {
                            $woocs_rates[$key]['rate'] = 1;
                        }
                    }
                }
                           
            }
            update_option( 'woocs', $woocs_rates);       
            global $WOOCS;
            $WOOCS->currencies = $woocs_rates;    
        } else {
            $subj = 'check sync currency';
            wp_mail( 'margalit.t@simplyct.co.il', $subj, implode(" ",$response) );
            exit(json_encode(['status' => 0, 'msg' => 'Error Sync currency Priority']));
        }
    }

};

add_action('syncCurrencyPriority_hook', 'syncCurrencyPriority');
if (!wp_next_scheduled('syncCurrencyPriority_hook')) {
    $res = wp_schedule_event(time(), 'hourly', 'syncCurrencyPriority_hook');
}

/****
 * Saving the contents of the fields on the Checkout page when selecting the currency and refreshing
 * Clearing the save after payment
 * 
 */
add_action('wp_footer', function() {
    if (!is_checkout() && !is_order_received_page()) return;;
    ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // Function to restore fields
        function restoreFields(form) {
            form.querySelectorAll('input[type="email"], input[type="checkbox"], input[type="radio"]').forEach(function(field) {
                const savedValue = sessionStorage.getItem('wc_' + field.name);
                if (savedValue !== null) {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = savedValue === 'true';
                    } else {
                        field.value = savedValue;
                    }
                }
            });
        }

        // Checkout page
        if (document.querySelector('form.checkout')) {
            const checkoutForm = document.querySelector('form.checkout');

            // Restores values
            restoreFields(checkoutForm);

            // Save any changes
            checkoutForm.addEventListener('input', function(e) {
                const el = e.target;
                if (el.name) {
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        sessionStorage.setItem('wc_' + el.name, el.checked);
                    } else {
                        sessionStorage.setItem('wc_' + el.name, el.value);
                    }
                }
            });

            // Recovery after a WooCommerce dynamic update
            jQuery(document.body).on('updated_checkout', function() {
                restoreFields(checkoutForm);
            });
        }

        // Thank You / Order Received page
        if (document.body.classList.contains('woocommerce-order-received')) {
            // Clears all data
            for (let key in sessionStorage) {
                if (key.startsWith('wc_')) {
                    sessionStorage.removeItem(key);
                }
            }
        }

    });
    </script>
    <?php
});