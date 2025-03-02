<?php
use PriorityWoocommerceAPI\WooAPI;

// sync inventory from priority
// update that the product is always in stock
function simply_code_after_sync_inventory($product_id, $item){
    // set stock status
    // $product = wc_get_product($product_id);
	$stock_status = 'instock';
    $backorder_status = 'no';

    $kare_stock = intval(get_post_meta($product_id, 'kare_general_stock', true));
    $stock_available = intval(get_post_meta($product_id, '_stock', true));

    if (($kare_stock <= 0) && ($stock_available <= 0)) {
        $stock_status = 'outofstock';
        $backorder_status = 'no';
    } elseif ($kare_stock > 0 && $stock_available <= 0) {
        $stock_status = 'instock';
        $backorder_status = 'yes';
    }

    update_post_meta($product_id, '_stock_status', $stock_status);
    update_post_meta($product_id, '_backorders', $backorder_status);

}

function simply_code_after_sync_inventory_by_sku($product_id, $item){
    // set stock status
    // $product = wc_get_product($product_id);
 	$stock_status = 'instock';
    $backorder_status = 'no';

    $kare_stock = intval(get_post_meta($product_id, 'kare_general_stock', true));
    $stock_available = intval(get_post_meta($product_id, '_stock', true));

    if (($kare_stock <= 0) && ($stock_available <= 0)) {
        $stock_status = 'outofstock';
        $backorder_status = 'no';
    } elseif ($kare_stock > 0 && $stock_available <= 0) {
        $stock_status = 'instock';
        $backorder_status = 'yes';
    }

    update_post_meta($product_id, '_stock_status', $stock_status);
    update_post_meta($product_id, '_backorders', $backorder_status);
}

// sync items from priority
//define select field for sync item
add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
function simply_syncItemsPriority_data_func($data)
{
    $data['select'] .= ',SUPNAME,SUPDES,ZYOU_LONGFAMILYDES,ZYOU_MFAMILYDES,ZYOU_FAMILYDES';
    return $data;
}

add_action('simply_update_product_data', function($item){

    $product_id = $item['product_id'];

    // check if the item flagged is 'O.C.' 
    // הוחלט לא להתיחס ל O.C  במוצרים כרגע רק לפי שדה "showinweb"
    /*if ( $product_id != 0 && $item['SPEC6'] == 'O.C' ) {
        $kare_stock = get_post_meta($product_id, 'kare_general_stock', true);
        $stock_available = get_post_meta($product_id, '_stock', true);

        if (  (empty($kare_stock) || $kare_stock <= 0) && intval($stock_available) <= 0  ) {
			$_product = wc_get_product( $product_id );
			$_product->delete( true );
			$_product->save();
            return;
        }
    }*/
    
    if($product_id !== 0) {
        $pdt_product = wc_get_product( $product_id );

        //update procuct details
        $item_num =  $item['SPEC1'];
        $width =  $item['SPEC9'];
        $depth =  $item['SPEC13'];
        $height =  $item['SPEC11'];
        $weight =  $item['SPEC8'];
        $color =  $item['ZYOU_SPECEDES15'];

        $colors = array_map('trim', explode(',', $color));
        update_product_color_attributes($product_id, $colors);

        // $series =  $item['SPEC12'];

        $product_details_data = [
            'pdt_information' => [
                'item_number' => $item_num,
                'width' => $width,
                'depth' => $depth,
                'height' => $height,
                'weight' => $weight,
            ],
        ];
        update_field('product_details', $product_details_data, $product_id);

        $main_module =  $item['SPEC4'];
        $qty_product_order =  $item['SPEC12'];
        update_post_meta($product_id, 'main_module', $main_module);
        update_post_meta($product_id, 'quantity_product_order', $qty_product_order);

        //update size ship
        $shipping_size =  $item['SPEC5'];
        // simply_set_ship_class($product_id, $shipping_size);
        $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));
        foreach ($shipping_classes as $shipping_class) {
            if (strcasecmp($shipping_size, $shipping_class->name) == 0) {
                // assign class to product
                $product = wc_get_product($product_id); // Get an instance of the WC_Product Object
                $product->set_shipping_class_id($shipping_class->term_id); // Set the shipping class ID
                $product->save(); // Save the product data to database
                continue;
            }
        }
        
        //update main parent category
        $taxon = 'product_cat';
        $main_parent_category_name = $item['ZYOU_FAMILYDES']; //FURNITURE
        $parent_category_name = $item['ZYOU_MFAMILYDES']; //Tables

        $main_parent_category = term_exists( $main_parent_category_name, $taxon );
        if ( ! $main_parent_category ) {
            $main_parent_category = wp_insert_term( $main_parent_category_name, $taxon );
        }

        if ( ! is_wp_error( $main_parent_category ) && ! empty( $main_parent_category['term_id'] ) ) {
            $parent_category = term_exists( $parent_category_name, $taxon );
            // $child_category = term_exists( $parent_category_name, $taxon, $main_parent_category['term_id'] );
            if ( ! empty( $parent_category ) && ! is_wp_error( $parent_category )) {
                // wp_insert_term( $parent_category, $taxon, array( 'parent' => $main_parent_category['term_id'] ) );
                wp_update_term( $parent_category['term_id'], $taxon, array( 'parent' => $main_parent_category['term_id'] ) );
            }
        }

        
        //update tag
        $tag =  $item['SPEC6'];
        if ($tag == 'A') {
            wp_set_object_terms($product_id, 'new', 'product_tag');
        } 
        elseif ($tag == 'B.S') {
            wp_set_object_terms($product_id, 'bestseller', 'product_tag');
        }
        else {
            wp_set_object_terms($product_id, null, 'product_tag'); 
        }
        $bs_tags = $item['SPEC10'];
        if ($bs_tags) {
            wp_set_object_terms($product_id, 'bestseller', 'product_tag', true);
        }


        //update inspiration caegory - preferred supplier
        //כרגע הוחלט שזה הזנה באתר ולא מגיע מפריוריטי
        /*$trend = $item['SUPDES'];
        if ( $trend && $trend != null ) {
            wp_set_object_terms($product_id, $trend, 'trend_cat', true);
        } else {
            wp_set_object_terms($product_id, null, 'trend_cat');
        }*/

        // check if there is a main image to product and publish
        if (has_post_thumbnail($product_id)) {
            $product = wc_get_product($product_id);
            if ($product->get_status() !== 'publish') {
                $product->set_status('publish');
                $product->save();
            }
        }
        $pdt_product->save();   
        
    }
});

function update_product_color_attributes( $product_id, $colors ) {

    
    // Ensure the attribute is registered globally in WooCommerce
    $attr_slug = 'color'; // Global attribute slug for color
    $attr_name = 'color';

    // Check if the attribute exists; if not, create it
    if (!WooAPI::instance()->is_attribute_exists($attr_slug)) {
        $attribute_id = wc_create_attribute(
            array(
                'name'         => $attr_name,
                'slug'         => $attr_slug,
                'type'         => 'select',
                'order_by'     => 'menu_order',
                'has_archives' => 0,
            )
        );
    }

    $clean_colors = array_map( function ( $color ) {
        return ucwords( trim( strtolower( $color ) ) ); // הפוך לאות ראשונה גדולה, הסר רווחים
    }, $colors );

    // Ensure terms exist for each color
    foreach ( $clean_colors as $color ) {
        if ( ! term_exists( $color, $attr_slug ) ) {
            wp_insert_term( $color, $attr_slug );
        }
    }

    // Assign the colors to the product
    wp_set_object_terms( $product_id, $clean_colors, $attr_slug, false );

    // Update the product attributes to link the taxonomy
    $product_attributes = get_post_meta( $product_id, '_product_attributes', true );

    // Check if the product already has the attribute
    if ( ! is_array( $product_attributes ) ) {
        $product_attributes = array();
    }

    // Add or update the color attribute
    $product_attributes[ 'pa_' . $attr_slug ] = array(
        'name'         => 'pa_' . $attr_slug,
        'value'        => '',
        'is_visible'   => 1, // Show on product page
        'is_variation' => 0, // Not used for variations
        'is_taxonomy'  => 1, // This is a taxonomy
    );

    // Save the updated attributes
    update_post_meta( $product_id, '_product_attributes', $product_attributes );

}

//open customer in priority then in register
add_action( 'template_redirect', 'get_user_details_after_registration');
function get_user_details_after_registration() {
    if ( is_user_logged_in() && !current_user_can( 'manage_options' )) {
        $id = get_current_user_id();
        if(empty(get_user_meta($id, 'user_reg', true))){
            update_user_meta($id, 'user_reg', true);
            if ($user = get_userdata($id)) {
                $meta = get_user_meta($id);
                // if already assigned value it is stronger
                $email = strtolower($user->data->user_email);
                $phone = $meta['billing_phone'][0];
                // if ($email) {
                //$url_addition = 'CUSTOMERS?$filter=(EMAIL eq \''.$email.'\' or PHONE eq \''.$phone .'\')';
                $url_addition = 'CUSTOMERS?$filter=EMAIL eq \''.$email.'\'';
                $response_email =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
                if($response_email['code'] == 200) {
                    $body_email =   json_decode($response_email['body'])->value;
                    if(empty($body_email)){
                        $response_phone =  WooAPI::instance()->makeRequest('GET', 'CUSTOMERS?$filter=PHONE eq \''.$phone .'\'' , [], true);
                        if($response_phone['code'] == 200) {
                            $body_phone =   json_decode($response_phone['body'])->value;
                            if(!empty($body_phone)){
                                $custname = $body_phone[0]->CUSTNAME;
                            }
                        }
                        else{
                            //$custname = null;
                            $subj = 'Error when searching for a customer by phone in sync to priority';
                            wp_mail( get_option('admin_email'), $subj, $response_phone['body'] );
                        }
                    }
                    else{
                        $custname = $body_email[0]->CUSTNAME;
                    }
                } else{
                    //$custname = null;
                    $subj = 'Error when searching for a customer by email in sync to priority';
                    wp_mail( get_option('admin_email'), $subj, $response_email['body'] );
                }
                // } 
                
    
                if (!empty($custname)) {
                    update_user_meta($id, 'priority_customer_number', $custname);
                }
                
                $birthday = get_user_meta( $id, 'birth_date', true );
                $timezone = new DateTimeZone('Asia/Jerusalem'); 
                $date = DateTime::createFromFormat('Y-m-d', $birthday, $timezone); 
                $date->setTime(0, 0, 0);
                $birthday_date = $date->format('Y-m-d\TH:i:sP');

                $gender = get_user_meta( $id, 'sex_selection', true );
                $user_arrived_choice = get_user_meta($id, 'user_arrived_choice', true);
                $club = get_user_meta($id, 'checkbox_club', true);
         
    
                $request = [
                    'CUSTNAMEPATNAME' => 'DW',
                    //'CUSTNAME' => $priority_customer_number,
                    'CUSTDES' => empty($meta['first_name'][0]) ? $meta['nickname'][0] : $meta['first_name'][0].' '.$meta['last_name'][0],
                    'EMAIL' => $user->data->user_email,
                    'ZYOU_BIRTHDATE10' => !empty($birthday_date) ? $birthday_date : '',
                    'PHONE' => isset($meta['billing_phone']) ? $meta['billing_phone'][0] : '',
                    'NSFLAG' => 'Y',
                    'SPEC4' => !empty( $user_arrived_choice ) ? $user_arrived_choice : '',
                    'CTYPECODE' => !empty($club) ? '02' : '03',
                    'SPEC3' => !empty( $gender ) ? $gender : '',
                    'ZYOU_MAILAPP' => !empty($club) ? 'Y' : '',
                    'SPEC20' => strval($id),
                    'SPEC19' => 'נרשם באתר'
                ];
                
                $method = !empty($custname) ? 'PATCH' : 'POST';
                // $method = 'POST';
                $url_eddition = 'CUSTOMERS';
                if ($method == 'PATCH') {
                    $url_eddition = 'CUSTOMERS(\'' . $custname . '\')';
                    unset($request['CUSTNAMEPATNAME']);
                }

                $json_request = json_encode($request);
                $response = WooAPI::instance()->makeRequest($method, $url_eddition, ['body' => $json_request], true);
                if ($method == 'POST' && $response['code'] == '201' || $method == 'PATCH' && $response['code'] == '200') {
                    $data = json_decode($response['body']);
                    $priority_customer_number = $data->CUSTNAME;
                    update_user_meta($id, 'priority_customer_number', $priority_customer_number);
                } // set priority customer id
                else {
                    WooAPI::instance()->sendEmailError(
                        [WooAPI::instance()->option('email_error_sync_customers_web')],
                        'Error Sync Customers',
                        $response['body']
                    );
                }
                
                // add timestamp
                //$this->updateOption('post_customers', time());
            }
        }
       
    }
}

add_filter('simply_modify_customer_number','simply_modify_customer_number');
function simply_modify_customer_number($data){  
    $order = $data['order'];
    
    if ($order->get_user_id() != 0) {
        $cust_number = get_user_meta($order->get_user_id(), 'priority_customer_number', true);
    }
    else{
        $cust_number = WooAPI::instance()->option('walkin_number');
    }
    $data['CUSTNAME'] = $cust_number;
    return $data;
}

// update data and fields in syncorder to priority
add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
	$data['AGENTCODE'] = '01';    
	$data['TYPECODE'] = '006';
    $agent_note = $data['ORDERSTEXT_SUBFORM']['TEXT'];
    if (strlen($agent_note) > 120) {
        $agent_note = substr($agent_note, 0, 115) . '...'; 
    }
    $data['ESTR_NOTES'] = $agent_note;
	
	//Update payment code for the query
    $data['PAYMENTDEF_SUBFORM']['PAYMENTCODE'] = '15';

    $order_id = $data['orderId'];
    $order = new \WC_Order($order_id);

    //set coupon to vprice instead vatprice
	$coupon = $order->get_coupon_codes();
	$items = [];
    foreach($data['ORDERITEMS_SUBFORM'] as $item ){
        //coupon
        if($item['PARTNAME'] == '004' ){
            if(!empty($coupon) && $item['TQUANT'] == -1){
                $item['PDES'] = $coupon[0];
            }
        }
        $items[] = $item;
    }
    $data['ORDERITEMS_SUBFORM'] = $items;


    return $data;
}

// update data and fields in receipt - sync to priority
add_filter('simply_request_data_receipt', 'simply_receipt_func');
function simply_receipt_func($data)
{
	$data['AGENTCODE'] = '01';    
	$data['TYPECODE'] = '006';
	$data['CASHNAME'] = '005';

    //ger the number order and update in Receipt
    $order_id = $data['orderId'];
    $order = wc_get_order( $order_id );
    $ord_number = $order->get_meta( 'priority_order_number', true );
	$data['ORDNAME'] = $ord_number;
    unset($data['REFERENCE']);
	
	//Update payment code for the query
    $data['TPAYMENT2_SUBFORM'][0]['PAYMENTCODE'] = '15';

    return $data;
}

//get priority customer number by user email
// add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority_func');
/*function simply_search_customer_in_priority_func($data){
    $order = $data['order'];
    $order_id = $order->id;
    $order = wc_get_order($order_id);
    if ($order) {
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
    }
    if ($email || $phone) {
        $url_addition = 'CUSTOMERS?$filter=(EMAIL eq \''.$email.'\' or PHONE eq \''.$phone .'\')';
        $response =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
        if($response['code'] == 200) {
            $body =   json_decode($response['body']);
            if(!empty($body)){
                $value = $body->value[0];
                $custname = $value->CUSTNAME;
            }
        } else{
            $custname = null;
            $subj = 'Error when searching for a customer in sync to priority';
            wp_mail( get_option('admin_email'), $subj, $response['body'] );
        }
    } else{
        $custname = null;
    }
    $data['CUSTNAME'] = $custname;
    return $data;
}*/
	
//add_filter('simply_syncCustomer', 'simply_syncCustomer_func');
/*function simply_syncCustomer_func($request)
{
    unset($request['EDOCUMENTS']);
	$request['CTYPECODE'] = '03';

    //get the data from user and update in customer
    $user_id = $request["id"];
    $birth_date = get_user_meta($user_id, 'birth_date', true);
    $user_arrived_choice = get_user_meta($user_id, 'user_arrived_choice', true);
	$request['SPEC2'] = !empty( $birth_date ) ? $birth_date : '';
	$request['SPEC4'] = !empty( $user_arrived_choice ) ? $user_arrived_choice : '';

    return $request; 
}*/
?>