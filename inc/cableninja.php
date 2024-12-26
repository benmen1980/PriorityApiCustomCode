<?php
use PriorityWoocommerceAPI\WooAPI;

function ajax_enqueue() {
    global $wp_query; 
	// Enqueue javascript on the frontend.
    wp_enqueue_script('ajax-scripts', P18AW_ASSET_URL.'ajax-script.js', array('jquery'));
    // The wp_localize_script allows us to output the ajax_url path for our script to use.
	wp_localize_script('ajax-scripts', 'ajax_obj', array( 
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
    ));

    wp_enqueue_style( 'priority-woo-api-style', P18AW_ASSET_URL.'style.css');
}

add_action( 'wp_enqueue_scripts', 'ajax_enqueue' );

add_action('sync_cprof', 'syncCPRofPriority');

// Clear any existing scheduled events for 'sync_cprof'
wp_clear_scheduled_hook('sync_cprof');

if (!wp_next_scheduled('sync_cprof')) {

    $res = wp_schedule_event(time(), 'hourly', 'sync_cprof');

}

/**
* Change email for errors from the site manager
*
*/
add_filter('simplyct_sendEmail', 'simplyct_sendEmail_func');
function simplyct_sendEmail_func($emails)
{
    array_push($emails, 'Yoav@arrowcables.com');
    return $emails;
}

/**
* sync CPRof from priority to web
*
*/
function syncCPRofPriority() {

    $currentDate = new DateTime();
    $bod = $currentDate->format('Y-m-d\TH:i:s\Z');

    $pdate       = $currentDate->modify('-6 months');
    $sixMonthsAgo = $pdate->format('Y-m-d\TH:i:s\Z');

    $date_filter    = 'PDATE ge ' . urlencode($sixMonthsAgo) . ' and PDATE le ' . urlencode($bod);

    $data['expand'] = '$expand=CPROFTEXT_SUBFORM,CPROFITEMS_SUBFORM($expand=CPROFITEMSTEXT_SUBFORM)';

    $response = WooAPI::instance()->makeRequest('GET',
        'CPROF?$filter=' . $date_filter . '&' . $data['expand']. '', [],
        WooAPI::instance()->option( 'log_items_priority', true ) );

    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);

        foreach ($response_data['value'] as $item) {

            if (CheckExistingProduct($item['CPROFNUM'], $item)) {
                continue; // Product already exists, skip to the next iteration
            }
              
        }

    }
};

function CheckExistingProduct($product_sku, $item) {

    $args = array(
        'post_type'   => array( 'product', 'product_variation' ),
        'post_status' => array( 'publish', 'draft' ),
        'meta_query'  => array(
            array(
                'key'   =>'_sku',
                'value' => $product_sku.'-'.$item['ROYY_RAND'],
            )
        )
    );
    $my_query = new \WP_Query( $args );
    
    if ( $my_query->have_posts() ) {
        while ( $my_query->have_posts() ) {
            $my_query->the_post();
            $product_id = get_the_ID();

            $sku_product = get_post_meta($product_id, '_sku', true);

            $quote_link = get_permalink($product_id);

            return $quote_link;
         
            exit(); // Product exists
            
        }
    }
    // Product does not exist 

    //create the parent product in site 
    $parent = [];
    $childrens = []; 

    $product_sku = (string)$product_sku;
	$token = $item['ROYY_RAND'];
    
    $attributes = [];
    $attributes['כמות'] = ['100'];
    $attributes['Coil'] = ['PQ'];
    $attributes['יחידת מידה'] = ['יח\'', 'מטר'];
    $item['attributes'] = $attributes;

    //sync content
    $description = $item['CPROFTEXT_SUBFORM']['TEXT'];
   
    // Use a regular expression to remove content between <style> and </style>
    $cleanedText = preg_replace('/<style>.*?<\/style>/s', '', $description);

    //sync image
    $image_quote = get_site_url().'/wp-content/uploads/2023/11/spechial-sale-logo.png';
    $attach_id = attachment_url_to_postid($image_quote);
    
    $parent = array(
        'author' => '', // optional
        'title' => 'הצעת מחיר: ' . $product_sku . ' ל: ' . $item['CDES'] . ' (בתוקף עד ' . date( 'd/m/y',strtotime($item['EXPIRYDATE'])) . ')</br> תנאי תשלום: ' . $item['PAYDES'],
        'content' => $cleanedText,
        'excerpt' => '',
        'regular_price' => '', // product regular price
        'sale_price' => '', // product sale price (optional)
        'stock' => 'Y', // Set a minimal stock quantity
        'sku' => $product_sku.'-'.$token, // optional
        'image_id' => (!empty($attach_id) && $attach_id != 0) ? $attach_id : '', // optional
        'image_file' => (!empty($image_quote)) ? $image_quote : '', // optional
        // For NEW attributes/values use NAMES (not slugs)
        'attributes' => $attributes,
        'categories' => [ ],
        'status' => WooAPI::instance()->option('item_status')
    );

    $id = create_product_variable($parent);
  
    // Update the stock quantity
    update_post_meta($id, '_stock', '0');
    update_post_meta($id, '_stock_status', 'onbackorder');

    // Update the product's "manage stock" option
    update_post_meta($id, '_manage_stock', 'yes');

    // Update the product's Possibility of pre-orders
    update_post_meta($id, '_backorders', 'yes');

    // Update ACF field Product attributes
    $product_attributes = array(
        "0" => "12981",
        "1" => "12975",
        "2" => "12977",
        "3" => "12978",
        "4" => "12979"
    );
    update_field('product_main_attributes', $product_attributes, $id);

    $product_attributes_dropdown = array(
        "0" => "12980"
    );
    update_field('product_attributes_dropdown', $product_attributes_dropdown, $id);

    update_field('manufacturer_sku', $item['CUSTNAME'], $id);

    $array_url = array(
                'url' => 'https://products.lappgroup.com/online-catalogue.html',
                'target' => "_blank",
            );
    update_field('spec_link', $array_url, $id);

    //add the customer contact details to the fields
    $priority_customer_number = $item['CUSTNAME'];
    $contact = $item['NAME'];
    $paydes= $item['PAYDES'];

    $response = WooAPI::instance()->makeRequest('GET', 
    'CUSTOMERS(\'' . $priority_customer_number . '\')?$select=CUSTNAME,CUSTDES,WTAXNUM,ADDRESS,ADDRESS2,STATEA&$expand=CUSTPERSONNEL_SUBFORM($filter=NAME eq \'' .  $contact . '\')', [],
    WooAPI::instance()->option( 'log_customer_priority', true ) );

     // check response status
     if ($response['code'] == '200' || $response['code'] == '201') {
        $_data = json_decode($response['body_raw'], true);
        
        foreach($_data['CUSTPERSONNEL_SUBFORM'] as $contact_priority) {
            update_field('email', $contact_priority['EMAIL'], $id);
            update_field('first_name', $contact_priority['FIRSTNAME'], $id);
            update_field('last_name', (!empty($contact_priority['LASTNAME'])) ? $contact_priority['LASTNAME'] : '', $id);
            update_field('phone', $contact_priority['CELLPHONE'], $id);
        }
        update_field('company_name', $_data['CUSTDES'], $id);
        update_field('company_id', $_data['WTAXNUM'], $id);
        update_field('street_name', $_data['ADDRESS'], $id);
        update_field('house_number', $_data['ADDRESS2'], $id);
        update_field('city_name', $_data['STATEA'], $id);
        update_field('payment_condition', $paydes, $id);
        update_field('customer_number', $priority_customer_number, $id);
        
    }
    
    // Add the category to the product
    wp_set_object_terms($id, 'מבצעי ממוספרים ללקוחות', 'product_cat', true);

    $_quote = wc_get_product($id);
    $_quote->set_catalog_visibility('hidden');
    $_quote->save();
        
    $quote_link = get_permalink($id);
         
    //create childrens products in site
    $childrens[$item[$product_sku]][$product_sku] = [
        'sku' => $product_sku.'-'.$token,
        'stock' => $item['INVFLAG'],
        'parent_title' => $item['MPARTDES'],
        'title' => $item['PARTDES'],
        'stock' => ($item['INVFLAG'] == 'Y') ? 'instock' : 'outofstock',
        'categories' => [
            $item[$is_categories]
        ],
        'attributes' => $attributes,
        'show_in_web' => $item[$show_in_web]
    ];
        
    foreach ($item['CPROFITEMS_SUBFORM'] as $product) {
                            
        $partname = $product['PARTNAME'];
        
        $data = [
            'post_author' => 1,
            'post_content' => '',
            'post_status' => WooAPI::instance()->option( 'item_status' ),
            'post_title'  => $product['PDES'],
            'post_parent' => '',
            'post_type'   => 'product',
            'post_category' => array( 4923 )
        ];

        // Insert product
        $product_id = wp_insert_post( $data );

        if ( $product_id ) {

            update_post_meta( $product_id, '_price', $product['QPRICE'] );
            update_post_meta( $product_id, '_regular_price', $product['QPRICE'] );
            // update_post_meta( $post_id, '_sale_price', "1" );

            // Update the stock quantity
            $in_stock = $product['SUPTIME'];
            if($in_stock <= '3') {
                $in_stock = '1';
                update_post_meta($product_id, '_stock_status', 'instock');
            } else {
                $in_stock = '0';
                update_post_meta($product_id, '_stock_status', 'onbackorder');
            }
            update_post_meta($product_id, '_stock', $in_stock);

            // Update the product's "manage stock" option
            update_post_meta($product_id, '_manage_stock', 'yes');

            // Update the product's Possibility of pre-orders
            update_post_meta($product_id, '_backorders', 'yes');

            $back_order = $product['SUPTIME'];
            if ($back_order <= '3') {
                $back_order = 'במלאי';
            } else {
                $back_order =  'כ- ' . $back_order . ' ימים';
            }

            update_post_meta($product_id, '_back_order', $back_order);

            // Add the category to the product
            wp_set_object_terms($product_id, 'מבצעי ממוספרים ללקוחות', 'product_cat', true);

            update_field('parent_id', $product_sku.'-'.$token, $product_id);
            update_field('manufacturer_sku', $product['BARCODE'], $product_id);

            // Set the attribute name and term value
            $_attributes = array();

            $comment_text = $product['CPROFITEMSTEXT_SUBFORM']['TEXT'];
            // $comment  = ' ' . html_entity_decode( $comment_text );
            $comment = preg_replace('/<style>.*?<\/style>/s', '', $comment_text);

            $wordToSearch = 'מפרט';

            if (strpos($comment, $wordToSearch) !== false) {

                $comment_without_specif = preg_replace('/<a\b[^>]*>.*?' . preg_quote($wordToSearch, '/') . '.*?<\/a>/', '', $comment);

                $pattern = '/<A\b[^>]*>.*?' . preg_quote($wordToSearch, '/') . '.*?<\/A>/';

                if (preg_match($pattern, $comment_without_specif)) {
                    // If it is, set the content to an empty string
                    $comment_without_specif = '';
                }

                // Remove empty HTML tags and non-breaking spaces
                $clean_comment = preg_replace('/<[^>]+>(\s|&nbsp;)*<\/[^>]+>/', '', $comment_without_specif);

                // Remove any remaining non-breaking spaces
                $clean_comment = str_replace('&nbsp;', '', $clean_comment);

                // Check if the comment is empty or contains only HTML tags
                if (trim(strip_tags($clean_comment)) === '') {
                    // Do not set the attribute if the content is empty
                    $comment_without_specif = '';
                } else {
                    $comment_without_specif = $clean_comment;
                }
            } else {
                // If the word is not present, output the original text
                $comment_without_specif =  $comment;
            }
            
            $percent = $product['PERCENT'];

            $_attributes['pa_סידרה'] = $product['Y_24022_5_ESHB'];
            $_attributes['pa_coil'] = 'PQ';
            $_attributes['pa_יחידת-מידה'] = $product['TUNITNAME'];
            $quant = '' . (string)$product['TQUANT'];
            if($quant == "-1") {
                $_attributes['pa_הנחת-לקוח'] = " 0";
            }
            $_attributes['pa_כמות'] = $quant;    
            $_attributes['pa_מחיר-ליחידה'] = ($percent !== 0.00) ? '₪'.$product['PERCENTPRICE'].' אחרי '.$percent.'% הנחה' : '₪'.$product['PERCENTPRICE'];
            $_attributes['pa_מחיר-ליחידה-נטו'] = $product['PERCENTPRICE'];
            $_attributes['pa_זמן-אספקה'] = $back_order;
            $_attributes['pa_הערה'] = $comment_without_specif;
			$line = '' . (int)$product['LINE'];
            $_attributes['pa_שורה'] = $line;
            if($product['AROW_INSTOCK'] == 'Y') {
                $arow_instock = 'במלאי';
            } elseif ($product['AROW_BYAIR'] == 'Y') {
                $arow_instock = 'באוויר';
            } elseif ($product['AROW_BYSEA'] == 'Y') {
                $arow_instock = 'בים';
            } else {
                $arow_instock = 'במלאי';
            }
            $_attributes['pa_מצב-מלאי'] = $arow_instock;

            $children_attributes = [];
            // Check if the attribute exists
            foreach ($_attributes as $attribute_name => $term_value){
                $attribute = get_taxonomy($attribute_name);
                if ($attribute) {
                    $existing_term = get_term_by('name', $term_value, $attribute_name);
                    if (empty($existing_term)) {
                        // Create a new term
                        $term_result = wp_insert_term($term_value, $attribute_name);
                        if (!is_wp_error($term_result)) {
                            $existing_term = get_term_by('name', $term_value, $attribute_name);
                        }
                    }
                    if ($existing_term) {
                            wp_set_object_terms($product_id, $term_value, $attribute_name, true);
                            $children_attributes[$attribute_name] = array(
                                'name' => $attribute_name, // set attribute name
                                'value' => $term_value, // set attribute value
                                'is_visible' => 1,
                                'is_variation' => 0,
                                'is_taxonomy' => 1
                            );
                    }
                }
            }
            update_post_meta($product_id, '_product_attributes', $children_attributes);          
            $product_attributes = get_post_meta($product_id, '_product_attributes');
                    
            $post_id = $product['Y_17934_5_ESHB'];
            if ($post_id  !== null) {
                $query_args = array(
                    'post_type' => array( 'product', 'product_variation' ),
                    'post_status' => 'publish',
                    'post__in' => array( $post_id ),
                );
                
                // The Query
                $the_query = new WP_Query( $query_args );
                
                // The Loop
                if ( $the_query->have_posts() ) {
                    while ( $the_query->have_posts() ) {
                        $the_query->the_post();
    
                        // $product_url = get_permalink();
    
                        //get the acf field 'link_array'
                        $link = get_field('spec_link');
    
                       // Get the ID of the featured image
                        $image_id = get_post_thumbnail_id($post_id);
    
                        // Get the image URL
                        $image_url = wp_get_attachment_url($image_id);
                    }
    
                    /* Restore original Post Data */
                    wp_reset_postdata();
                }
                
                if( is_array($link) ) { 
                    $link_url = $link['url'];
                    $link_title = $link['title'];
                    $link_target = $link['target'] ? $link['target'] : '_blank';
    
                    $array_url = array(
                        'url' => $link_url,
                        'target' => $link_target,
                    );
                } else {
                    $array_url = array(
                        'url' => $link,
                        'target' => "_blank",
                    );
                }
              
                update_field('spec_link', $array_url, $product_id);
    
                if ($image_id) {
                    // Get the attachment ID of the new image file
                    set_post_thumbnail($product_id, $image_id);            
                }
                unset ($post_id);   
            }
        }
        // Get the current product data
        $product = wc_get_product($product_id);
        $product->set_catalog_visibility('hidden');
        $product->save();
           
        // And finally (optionally if needed)
        wc_delete_product_transients( $product_id ); // Clear refresh the variation cache
    } 
    
    return $quote_link;
};

/**
* sync CPRof to the offer number
*
*/
add_action( 'wp_ajax_syncCPRofByNumber', 'syncCPRofByNumber' );
add_action( 'wp_ajax_nopriv_syncCPRofByNumber', 'syncCPRofByNumber' );
function syncCPRofByNumber($sku, $quote_token = null ) {
    if ($quote_token !== null) {      
        $quote_token = ' and ROYY_RAND eq \'' . $quote_token . '\'';
    } else {
        if(!empty($_POST['CPROFNUM'])) {
            $sku = $_POST['CPROFNUM'];
            $send_json = true;
        } else {
            $send_json = false;
        };
    };
   
    $priority_version = (float)WooAPI::instance()->option('priority-version');
    
    $res = WooAPI::instance()->option('sync_variations_priority_config');
    $res = str_replace(array('.', "\n", "\t", "\r"), '', $res);
    $config_v = json_decode(stripslashes($res));
    $show_in_web = (!empty($config_v->show_in_web) ? $config_v->show_in_web :  null);
    $show_front = !empty($config_v->show_front) ? $config_v->show_front : null;
    $config = json_decode(stripslashes(WooAPI::instance()->option('setting-config')));
    $chetzdaysback = $config->chetz_days_back;
    $is_categories = (!empty($config->categories) ? $config->categories : 'מבצעי ממוספרים ללקוחות');
    $stamp = mktime(0 - $chetzdaysback * 24, 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = 'UDATE ge ' . $bod;
    $search_field = 'CPROFITEMS_SUBFORM($select=PARTNAME)'; //מקט של מוצר בן
    $data['expand'] = '&$expand=CPROFTEXT_SUBFORM,CPROFITEMS_SUBFORM($expand=CPROFITEMSTEXT_SUBFORM)';
    $url_addition_config = (!empty($config_v->additional_url) ? $config_v->additional_url : '');
    $filter = urlencode($url_addition) . ' ' . $url_addition_config;
    // get all CPRof from priority
    $response = WooAPI::instance()->makeRequest('GET', 
    'CPROF?$filter=CPROFNUM eq \'' . $sku . '\'' . $quote_token . '&' . $filter . $data['expand']. '', [],
    WooAPI::instance()->option( 'log_items_priority', true ) );

    // check response status
    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);
        
        if ($response_data['value'][0] > 0) {
            foreach ($response_data['value'] as $item) {
                $response = CheckExistingProduct($item['CPROFNUM'],  $item);    
            }
        } else {
            exit(json_encode(['status' => 0, 'msg' => 'Error Sync quotes Priority ']));
            $subj = 'check sync quote';
            wp_mail( 'Yoav@arrowcables.com', $subj, implode(" ",$response) );
        }
    }
    if($send_json == 'true') {
        wp_send_json($response);
    };
    
    return $response;
};

function custom_add_endpoint($sku, $quote_token) {
    add_rewrite_endpoint( 'q', EP_ALL );
    // $endpoint_url = add_query_arg( 'q', $sku, home_url('/?') );
    $endpoint_url = add_query_arg(array('q' => $sku, 'r' => $quote_token), home_url('/'));
    wp_redirect($endpoint_url); // Redirect to the URL with the query variable set
    exit;
}
add_action( 'init', 'custom_add_endpoint', 10);

function custom_process_product_endpoint() {
    if (isset( $_GET['q']) && isset($_GET['r'])) {
        $quote_param = $_GET['q'];
        $quote_token = $_GET['r'];               
        // Call the fixed function with the SKU parameter
        $link_quote = syncCPRofByNumber($quote_param , $quote_token);
        
        //send email to yoav for each click
        $ip_address = $_SERVER['REMOTE_ADDR'];
        if($ip_address !== "194.90.125.117") {
            
            $cprofnum = $quote_param;
            $response = WooAPI::instance()->makeRequest('GET',
            'CPROF?$filter=CPROFNUM eq \'' . $cprofnum . '\' ', [], true );

            if ($response['status']) {
                $response_data = json_decode($response['body_raw'], true);
                foreach($response_data['value'] as $item) {
                    $company = $item['CDES'];
                    $agent = $item['DOERLOGIN'];
                    $contact = $item['NAME'];
                    $date = $item['PDATE'];
                    $status = $item['STATDES'];
                    $payment = $item['PAYDES'];
                    $price = $item['TOTPRICE'];

                }

                $date_time = new DateTime($date);
                $new_date = $date_time->format('d.m.Y');
                $to = 'yoav@arrowcables.com';
                // $to = 'margalit.t@simplyct.co.il';
                $subject = 'צפיה בהצעה ' . $cprofnum . 'של חברה: ' .  $company . 'סוכן: ' . $agent;
                $message = '<div style="direction: rtl;">היי ' . $agent . ', <br/> הלקוח ' . $company . ' לחץ כעת על קישור ליצירת הצעת מחיר: ' . $cprofnum . ' מכתובת IP: ' . $ip_address . '
                            <br/> שם איש הקשר: ' . $contact . '
                            <br/> תאריך פתיחת ההזמנה: ' . $new_date . '
                            <br/> סטטוס ההזמנה: ' . $status . '
                            <br/> תנאי התשלום: ' . $payment . '
                            <br/> סה"כ מחיר ההצעה (כולל מע"מ): ' . $price . '</div>';
                $headers = array('Content-Type: text/html; charset=UTF-8');

                // Send email
                $result = wp_mail($to, $subject, $message, $headers); 
            }
        }

        redirect($link_quote);
    }
}
add_action( 'template_redirect', 'custom_process_product_endpoint', 1 );

function redirect($url) {
    header('Location: '.$url);
    die();
}

 // Define a custom function to modify the value of $begindate
 function report_priority_quote_sixmonth($begindate) {
    // Change $begindate to six months ago
    $modified_begindate = date(DATE_ATOM, strtotime('-6 month'));
    return urlencode($modified_begindate);
};
add_filter('simply_excel_reports', 'report_priority_quote_sixmonth');

// Define a custom function to modify the value of $begindate
function add_button_shopping_cart_func($value) {
    $currentDate = new DateTime();
    $data = $currentDate->format('Y-m-d');
    $expirationDate = new DateTime($value->EXPIRYDATE);
    $data2 = $expirationDate->format('Y-m-d');

    if ($data < $data2) { 
        $add_button = "<td><button style='font-size: 13px!important;' data-num='".$value->CPROFNUM."' data-num='".$value->CPROFNUM."'type='button' class='btn_quote'>לרכישה
            <div class='loader_wrap'>
                <div class='loader_spinner'>
                    
                    <div class='line'></div>
                    <div class='line'></div>
                    <div class='line'></div>
                </div>
            </div>
        </button></td>"; 
        return $add_button;
    }       
};
add_filter('add_button_shopping_cart', 'add_button_shopping_cart_func');

/*function add_attache_priority_quote_func($values) {
    $manufacturer_sku = $values['value1'];
    $parent_id = $values['value2'];
    
    $query_args = array(
        'post_type' => array( 'product', 'product_variation' ),
        'post_status' => 'publish',
        'meta_query' => array(
            '0' => array(
                'key' => 'manufacturer_sku',
                'value' =>  $manufacturer_sku,
                'compare' => '=',
            ),
            '1' => array(
                'key' => 'parent_id',
                'value' => $parent_id,
                'compare' => '=',
            ),
            'relation' => 'AND',
        ),
    );
    
    // The Query
    $the_query = new WP_Query( $query_args );
    
    // The Loop
    if ( $the_query->have_posts() ) {
        while ( $the_query->have_posts() ) {
            $the_query->the_post();
            $product_url = get_permalink();
            //get the acf field 'link_array'
            $link = get_field('spec_link');
            if( !empty($link) ) { 
                $link_url = $link['url'];
                $link_title = $link['title'];
                $link_target = $link['target'] ? $link['target'] : '_blank';
            }
        }
    }
    if( !empty($link_url) ) {
        $attache = "<td style='white-space: normal!important;'><a href='".$link_url."' target='_blank' >
                            <img src='".get_stylesheet_directory_uri()."/assets/images/spec.svg' alt='Spec' style= 'width: 20px!important; max-width: 250%;'>
                            <span></span>
                    </a></td>";
    } 
    return $attache;
}
add_filter('add_attache_priority_quote', 'add_attache_priority_quote_func');*/

function add_attache_priority_func($post_id) {
    $query_args = array(
        'post_type' => array( 'product', 'product_variation' ),
        'post_status' => 'publish',
        'post__in' => array( $post_id ),
    );
    
    // The Query
    $the_query = new WP_Query( $query_args );
    
    // The Loop
    if ( $the_query->have_posts() ) {
        while ( $the_query->have_posts() ) {
            $the_query->the_post();
            //get the acf field 'link_array'
            $link = get_field('spec_link');
            /*if( !empty($link) ) { 
                $link_url = $link['url'];
                $link_title = $link['title'];
                $link_target = $link['target'] ? $link['target'] : '_blank';
            }*/
        }
    }

    if( is_array($link) ) { 
        $link_url = $link['url'];
        $link_title = $link['title'];
        $link_target = $link['target'] ? $link['target'] : '_blank';

        $attache = "<td style='white-space: normal!important;'><a href='".$link_url."' target='".$link_target."' >
                            <img src='".get_stylesheet_directory_uri()."/assets/images/spec.svg' alt='Spec' style= 'width: 20px!important; max-width: 500%;'>
                            <span></span>
                    </a></td>";
    } else {
        $attache = "<td style='white-space: normal!important;'><a href='".$link."' target='_blank' >
                            <img src='".get_stylesheet_directory_uri()."/assets/images/spec.svg' alt='Spec' style= 'width: 20px!important; max-width: 500%;'>
                            <span></span>
                    </a></td>";
    }
    return $attache;
}
add_filter('add_attache_priority', 'add_attache_priority_func');

add_action('add_message_front_priorityQuotes', function(){ ?>
    <p><?php echo 'ברירת המחדל להצגה היא חצי שנה אחורה. ניתן לעדכן מתאריך ועד תאריך ועל ידי כך להביא הצעות בטווח תאריכים שונה.'?></p>      
<?php 
}); 

add_action('add_message_front_priorityOrders', function(){ ?>
    <p><?php echo 'ברירת המחדל להצגה היא חצי שנה אחורה. ניתן לעדכן מתאריך ועד תאריך ועל ידי כך להביא הזמנות בטווח תאריכים שונה.'?></p>      
<?php 
}); 

// search CUSTNAME by email or vat num, input is array user_id or  order object
add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority');
function simply_search_customer_in_priority($data){  
    $order = $data['order'];
    $order_meta_data = $order->get_meta_data();
    $custname = WooAPI::instance()->option('walkin_number');

    foreach($order_meta_data as $meta) {
        // Access meta data values
        $meta_key = $meta->key;
        $meta_value = $meta->value;

        if ($meta->key == '_customer_number') {
            if(!empty($meta_value)) {
                $custname = $meta_value;
                break;
            } 
        }    
        
    } 
    $data['CUSTNAME'] = $custname;
    return $data;
}

add_filter('simply_modify_customer_number','simply_modify_customer_number');
function simply_modify_customer_number($data){  
    $order = $data['order'];
    $order_meta_data = $order->get_meta_data();

    foreach($order_meta_data as $meta) {
        // Access meta data values
        $meta_key = $meta->key;
        $meta_value = $meta->value;

        if ($meta->key == '_customer_number') {
            if(!empty($meta_value)) {
                $custname = $meta_value;
                break;
            } 
        }    
        
    } 
    if (empty($custname)) {
        return null;
    }
    $data['CUSTNAME'] = $custname;
    return $data;
}

add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    if (empty($data['CUSTNAME'])) {
        $cust_name = WooAPI::instance()->option('walkin_number');
        $data['CUSTNAME'] =$cust_name;
    };

    if (isset($data['CDES'])){
        unset($data['CDES']);
    };

    $order_id = $data['orderId'];
    $order = wc_get_order($order_id);
    $details = $order->get_customer_note();
    if(!empty($details)) {
        $limited_details = wordwrap($details, 50, "\n");
        $limited_details = explode("\n", $limited_details)[0];
    } else {
        $limited_details = $details;
    }
    $data['DETAILS'] = $limited_details;
    
    //sync  delivery codes for the order 
    $shipping_method = $order->get_shipping_methods();
    $shipping_method = array_shift($shipping_method);
    if (isset($shipping_method)) {
        $data_shipping = $shipping_method->get_data();
        $method_id = $data_shipping['method_id'];
        if($method_id == "free_shipping" || $method_id == "flat_rate") {
            $shipping_code = "200";
        }
        if ($method_id == "local_pickup") {
            $shipping_code = "20";
        }
        $data['STCODE'] = $shipping_code;
    }

    //sync pay-code to orders
    $paycode = WooAPI::instance()->option('payment_' . $order->get_payment_method(), $order->get_payment_method());
    $data['PAYCODE'] = $paycode;

    $order_data_meta = $order->get_meta_data();
    foreach($order_data_meta as $meta) {
        if ($meta->key == '_purchase_order_number') {
            $reference = $meta->value;
            if(!empty($reference)) {
                $data['REFERENCE'] = $reference;
            }
        }
        if ($meta->key == '_PQ_parent_sku') {
            $cprofnum = $meta->value;
            if(!empty($cprofnum)) {
                $data['SHIPREMARK'] = $cprofnum;
                update_status_cprofnum($cprofnum);
            }
        }
    }
    $data['ORDSTATUSDES'] = "חדש מהפורטל";

    return $data;
}

add_filter('simply_modify_orderitem', 'my_custom_orderitem_modifier');
function my_custom_orderitem_modifier($args) {
    // Access the data and item from the filter
    $data = $args['data'];
    $item = $args['item'];
	
	//unset the fiels partname of the sync
    unset($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PARTNAME']);
    // unset($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VATPRICE']);

    $product = $item->get_product();
    if ($product) {
        $product_id = $product->get_id();
        $barcode = get_field('manufacturer_sku', $product_id);
        $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['BARCODE'] = $barcode;
            
        $field = get_field('parent_id' ,$product_id);
        if (!(substr($field, 0, 2) === "PQ")) {  
            //update quant
            unset($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['TQUANT']);

            $popup_type = $item->get_meta( 'Popup Type' );
            if ($popup_type === 'length') {
                $popup_unit = (int)$item->get_meta( 'Popup Unit' );
                $price_subtoatl = (float)$item->get_subtotal();
                $price_unit = $price_subtoatl / $popup_unit;

                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['TQUANT'] = $popup_unit;
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PRICE'] = $price_unit;    
            }
            elseif ($popup_type === 'piece') {
                $popup_qty = (int)$item->get_meta( 'Popup Qty' );
                $price_subtoatl = (float)$item->get_subtotal();
                $price_unit = $price_subtoatl / $popup_qty;

                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['TQUANT'] = $popup_qty;
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PRICE'] = $price_unit; 

            } else {
                $attribute_name = 'pa_אורך'; 
                // Get the product attribute value (term) for the specified attribute
                $attribute_value = (int)$product->get_attribute($attribute_name);
                if (!empty ($attribute_value)) {
                    $quant = (int)$item->get_quantity();
                    $tquant = $attribute_value * $quant;
                    $regular_price = (float)$product->get_regular_price();
                    $sale_price = (float)$product->get_sale_price();
                    $price = ($sale_price && $sale_price > 0) ? $sale_price : $regular_price;                  
                    $price_unit = $price / $attribute_value;

    
                    $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['TQUANT'] = $tquant;
                    $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PRICE'] = $price_unit;

                }  
                else {
                    $attribute_name = 'pa_כמות-בחבילה';
                    $attribute_value = (int)$product->get_attribute($attribute_name);
                    if (!empty ($attribute_value)) {
                        $quant = (int)$item->get_quantity();
                        $tquant = $attribute_value * $quant;
                        $regular_price = (float)$product->get_regular_price();
                        $sale_price = (float)$product->get_sale_price();
                        $price = ($sale_price && $sale_price > 0) ? $sale_price : $regular_price; 
                        $price_unit = $price / $attribute_value;

                        $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['TQUANT'] = $tquant;
                        $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PRICE'] = $price_unit;

                    }
                }
            }

            /*//update price
            $attribute_name = 'pa_אורך'; 
            // Get the product attribute value (term) for the specified attribute
            $attribute_value = (int)$product->get_attribute($attribute_name);
            if (!empty ($attribute_value)) {
                $regular_price = (float)$product->get_regular_price();
                $price_unit = $regular_price / $attribute_value;
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PRICE'] = $price_unit;
    
            }  
            else {
                $attribute_name = 'pa_כמות-בחבילה';
                $attribute_value = (int)$product->get_attribute($attribute_name);
                if (!empty ($attribute_value)) {
                    $regular_price = (float)$product->get_regular_price();
                    $price_unit = $regular_price / $attribute_value;
                    $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PRICE'] = $price_unit;
                }
            }*/
 
            $eta_stock = $item->get_meta( 'ETA' );
            if ($eta_stock === "" || $eta_stock === "In Stock") {
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_INSTOCK'] ='Y';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYSEA'] ='null';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYAIR'] ='null';
            } else {
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYSEA'] ='Y';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_INSTOCK'] ='null';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYAIR'] ='null';
            }   
        }
        else {
            unset($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['TQUANT']);
            
            $quant_attribute = 'pa_כמות'; 
            $price_attribute = 'pa_מחיר-ליחידה-נטו'; 
            $assumption_attribute = 'pa_הנחת-לקוח ';
            $arow_instock_attribute = 'pa_מצב-מלאי';

            // Get the product attribute value (term) for the specified attribute
            $quant = (int)$product->get_attribute($quant_attribute);
            $customer_assum = $product->get_attribute($assumption_attribute);
            if ($customer_assum === "0") {
                $quant = -1;
                $unit_price = 1 * (float)$product->get_attribute($price_attribute);
            } else {
                $unit_price = (float)$product->get_attribute($price_attribute);
            }   
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['TQUANT'] = $quant;
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PRICE'] = $unit_price;

            $arow_instock = (string)$product->get_attribute($arow_instock_attribute);
            if ($arow_instock == 'במלאי') {
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_INSTOCK'] ='Y';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYSEA'] ='null';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYAIR'] ='null';
            } elseif( $arow_instock == 'באוויר') {
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYAIR'] ='Y';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYSEA'] ='null';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_INSTOCK'] ='null';
            } elseif( $arow_instock == 'בים') {
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYSEA'] ='Y';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_INSTOCK'] ='null';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYAIR'] ='null';
            } else {
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_INSTOCK'] ='Y';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYSEA'] ='null';
                $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYAIR'] ='null';
            } 

        }

        if ($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_INSTOCK'] == 'Y' ) {
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_MITKABEL'] = date('Y-m-d', strtotime('+3 days'));
        }
        elseif ($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYAIR'] == 'Y' ) {
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_MITKABEL'] = date('Y-m-d', strtotime('+10 days'));
        }
        elseif ($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_BYSEA'] == 'Y' ) {
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_MITKABEL'] = date('Y-m-d', strtotime('+30 days'));
        }
    }
    // Get the product meta data
    $product_meta_data = $item->get_meta_data();

    // Loop through product meta data
    foreach ($product_meta_data as $meta) {
        // Access meta data values
        $meta_key = $meta->key;
        $meta_value = $meta->value;

        if ($meta->key === 'Extra Info') {
            $extra_info = $meta->value;
            if(!empty($extra_info)) {
                $meta_extra_info = wordwrap($extra_info, 50, "\n");
                $meta_extra_info = explode("\n", $meta_extra_info)[0];
            } 
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['REMARK2'] = $meta_extra_info;
        }

        if ($meta->key === 'ETA') {
            $meta_eta = $meta->value;
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['AROW_SOURCE'] = $meta_eta;
        }
    }
    // Return the modified data
    return ['data' => $data, 'item' => $item];
}

add_filter('simply_syncCustomer', 'simply_syncCustomer_func');
function simply_syncCustomer_func($request)
{
    unset($request['EDOCUMENTS']);
    return $request; 
}

function update_status_cprofnum($cprofnum)
{
    $cprofnum = $cprofnum;
    $data = ['STATDES' => "הוזמן מהחנות"];

    $response = WooAPI::instance()->makeRequest('PATCH',
    'CPROF(\' ' . $cprofnum . ' \')', ['body' => json_encode($data)], true );

    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);
        // updte_post_meta($cprof_id'cprof_status', 'הוזמן מהחנות');
    }
}

// Send email in open the quote
add_action('simply_open_quote_request', function($quotemun) {   
    $response = WooAPI::instance()->makeRequest('GET',
    'CPROF?$filter=CPROFNUM eq \'' . $quotemun . '\' ', [], true );

    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);
        foreach($response_data['value'] as $quote) {
            $company = $quote['CDES'];
            $contact = $quote['NAME'];
            $date = date( 'd/m/y',strtotime($quote['PDATE']));
            $price = $quote['TOTPRICE'];
        }
        $to = 'info@arrowcables.com';
        // $to = 'margalit.t@simplyct.co.il';
        $subject = 'לקוח  ' . $company . ' נכנס להצעת מחיר ' . $quotemun . ' באזור האישי';
        $message = '<div style="direction: rtl;">היי, 
                    <br/> תאריך הצעה: ' . $date . '
                    <br/> סך המחיר: ' . $price . '
                    <br/> איש קשר: ' . $contact . '. </div>';
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send email
        $result = wp_mail($to, $subject, $message, $headers); 
    }
});

/**
 * sync inventory from priority
 */

function syncInventoryPriority()
{
    // get the items simply by time stamp of today
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[0];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = '('. rawurlencode('WARHSTRANSDATE ge ' . $bod . ' or PURTRANSDATE ge ' . $bod . ' or SALETRANSDATE ge ' . $bod . ' or YAEL_DATEUPDORDERS ge ' . $bod . ' or YAEL_DATEUPDPORDERS ge ' . $bod) . ')';
    $wh_name = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[0];
 
    $expand = '$expand=LOGCOUNTERS_SUBFORM($expand=PARTAVAIL_SUBFORM($select=AROW_DUEDATE,TQUANT,TITLE))';
    
    $response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select=BARCODE,PARTNAME,YAEL_PLEADTIME,BASEPLPRICE&$filter=SPEC14 ne \'\' and '.$url_addition.' and INVFLAG eq \'Y\' &' . $expand, [], 
                WooAPI::instance()->option('log_inventory_priority', false));

    // check response status
    if ($response['status']) {
        $data = json_decode($response['body_raw'], true);
        foreach ($data['value'] as $item) {
            // if product exsits, update
            $args = array(
                'post_type' => array('product', 'product_variation'),
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'manufacturer_sku',
                        'value' => $item['BARCODE'],
                        'compare' => '='
                    ),
                    array(
                        'key' => '_sku',
                        'value' => '',
                        'compare' => '!=' 
                    )
                )
            );
            $my_query = new \WP_Query($args);
            if ($my_query->have_posts()) {
                while ($my_query->have_posts()) {
                    $my_query->the_post();
                    $product_id = get_the_ID();           
                    $parent_id = get_field('parent_id' ,$product_id);
                    if (!empty($parent_id) && !(substr($parent_id, 0, 2) === "PQ")) {
                        // get the stock by part availability
                        $stock = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE'];
                        $delivery_days = $item['YAEL_PLEADTIME'];
                        $is_orders =  $item['LOGCOUNTERS_SUBFORM'][0]['ORDERS'];
                        
                        foreach($item['LOGCOUNTERS_SUBFORM'][0]['PARTAVAIL_SUBFORM'] as $line_available) {
                            $type_transaction = $line_available['TITLE'];
                            if($type_transaction == 'הזמנות רכש'){
                                if(!empty($line_available['AROW_DUEDATE'])) {
                                    //check that the required date is within 2 days of today
                                    $today = new DateTime();
                                    $date_time = new DateTime($line_available['AROW_DUEDATE']);
                                    $due_date = $date_time->format('d.m.Y');
                                    $due_date = DateTime::createFromFormat('d.m.Y', $due_date);
                                    if ($due_date !== false) {
                                        $interval = $today->diff($due_date)->days;
                                        if ($interval <= 2 ) {
                                            $stock += $line_available['TQUANT'];
                                        }
                                        else {
                                            continue;
                                        }
                                    }
                                } 
                                else {
                                    //required date field is empty
                                    $stock += $line_available['TQUANT'];
                                }
                                continue;
                            }

                            if($type_transaction == 'הזמנות לקוח'){
                                if($delivery_days !== 0) {
                                    $today = new DateTime();
                                    $date_time = new DateTime($line_available['AROW_DUEDATE']);
                                    $due_date = $date_time->format('d.m.Y');
                                    $due_date = DateTime::createFromFormat('d.m.Y', $due_date);
                                    $delivery_date = date('d.m.Y', strtotime("+$delivery_days days"));
                                    $delivery_date = DateTime::createFromFormat('d.m.Y', $delivery_date);

                                    if ($due_date !== false && $delivery_date !== false) {
                                        $interval = $today->diff($due_date)->days;
                                        $interval_delivery = $today->diff($delivery_date)->days;

                                        if ($interval <= $interval_delivery ) {
                                            $stock = $stock += $line_available['TQUANT'];
                                        }
                                        else {
                                            continue;
                                        }
                                    }

                                }
                                else {
                                    $today = new DateTime();
                                    $date_time = new DateTime($line_available['AROW_DUEDATE']);
                                    $due_date = $date_time->format('d.m.Y');
                                    $due_date = DateTime::createFromFormat('d.m.Y', $due_date);

                                    if ($due_date !== false) {
                                        $interval = $today->diff($due_date)->days;

                                        if ($interval <= 30 ) {
                                            $stock = $stock += $line_available['TQUANT'];
                                        }
                                        else {
                                            continue;
                                        }
                                    }
                                }
                                continue;
                            }
                        }
                        update_post_meta($product_id, '_stock', $stock);
                        update_post_meta($product_id, '_procurement_time', $delivery_days);

                        $expected_stock = 0;
                        $expected_stock_2 = 0;
                        $expected_stock_3 = 0;
                        $today = new DateTime();
                        foreach($item['LOGCOUNTERS_SUBFORM'][0]['PARTAVAIL_SUBFORM'] as $line_available) {                          
                            if ($line_available['TITLE'] !== 'הזמנות רכש') {
                                continue;
                            }
                                
                            $date_time = new DateTime($line_available['AROW_DUEDATE']);
                            $due_date = $date_time->format('d.m.Y');
                            $due_date = DateTime::createFromFormat('d.m.Y', $due_date);

                            if ($due_date === false) {
                                continue;
                            }

                            $interval = $today->diff($due_date)->days;
                            $tquant = $line_available['TQUANT'];
                            if ($interval >= 3 &&  $interval <= 10) {
                                if ( $expected_stock == 0 ) {
                                    $expected_stock = $stock + $tquant;
                                } else {
                                    $expected_stock += $tquant;
                                }
                            } elseif ($interval >= 11 &&  $interval <= 20) {
                                if ( $expected_stock_2 == 0 ) {
                                    $expected_stock_2 =  ($expected_stock == 0) 
                                        ? $stock + $expected_stock + $tquant 
                                        : $expected_stock + $tquant;  
                                }
                                else{
                                    $expected_stock_2 += $tquant;
                                }                                        
                            } elseif ($interval >= 21 &&  $interval <= 30) {
                                if ( $expected_stock_3 == 0 ) {
                                    $expected_stock_3 = ($expected_stock_2 == 0) 
                                    ? (
                                        ($expected_stock == 0) 
                                        ? $stock + $expected_stock + $expected_stock_2 + $tquant 
                                        : $expected_stock + $expected_stock_2 + $tquant
                                    ) 
                                    : $expected_stock_2 + $tquant;
                                } else {
                                    $expected_stock_3 += $tquant;
                                }
                            } else {
                                continue;
                            }                         
                        }
                        update_post_meta($product_id, '_stock_expected_10_days', ($expected_stock == 0 ) ? $expected_stock = $stock : $expected_stock);
                        update_post_meta($product_id, '_stock_expected_20_days', ($expected_stock_2 == 0 ) ? $expected_stock_2 = $expected_stock : $expected_stock_2);
                        update_post_meta($product_id, '_stock_expected_30_days', ($expected_stock_3 == 0 ) ? $expected_stock_3 = $expected_stock_2 : $expected_stock_3);

                        // sync price from priority to product
                        /*$_product = wc_get_product( $product_id );
                        if ( $_product->is_type( 'simple' ) ) {
                            $price = $item['BASEPLPRICE'];
                            update_post_meta($product_id, '_regular_price', $price);
                        }*/
                        
                        /*// set stock status
                        if (intval($stock) > 0) {
                            // update_post_meta($product_id, '_stock_status', 'instock');
                            $stock_status = 'instock';
                        } else {
                            // update_post_meta($product_id, '_stock_status', 'outofstock');
                            $stock_status = 'outofstock';
                        }*/
                    }
                }
            } else {
                $product_id = 0;
            }
        }

        // add timestamp
        WooAPI::instance()->updateOption('inventory_priority_update', time());
    } else {
        WooAPI::instance()->sendEmailError(
            WooAPI::instance()->option('email_error_sync_inventory_priority'),
            'Error Sync Inventory Priority',
            $response['body']
        );
    }
}
add_action('syncInventoryPriority_hook', 'syncInventoryPriority');

if (!wp_next_scheduled('syncInventoryPriority_hook')) {
    $res = wp_schedule_event(time(), 'daily', 'syncInventoryPriority_hook');
}

add_filter('simply_accounts_receivable_table', function( $priority_customer_number ) {

    $additionalurl = 'ACCOUNTS_RECEIVABLE?$select=ACCNAME,BALANCE3&$expand=ARFNCITEMS3_SUBFORM($select=BALDATE,FNCDATE,ORDNAME,IVNUM,DETAILS,SUMDEBIT,SUMCREDIT,BAL)&$filter=ACCNAME eq \'' . $priority_customer_number . '\'';
    $args = [];
    $response = WooAPI::instance()->makeRequest("GET", $additionalurl, $args, true);
    $data = json_decode($response['body']);

    if (!empty($data->value)) {
        echo "<table> <tr>";
        echo "<th></th><th>" . esc_html__('BALDATE', 'p18w') . "</th> <th>" . esc_html__('FNCDATE', 'p18w') . "</th> <th>" . esc_html__('ORDNUM', 'p18w') . "</th> <th>" . esc_html__('IVNUM', 'p18w') . "</th> <th>" . esc_html__('DETAILS', 'p18w') . "</th> <th>" . esc_html__('DEBTBAL', 'p18w') . "</th> <th>" . esc_html__('RIGHTBAL', 'p18w') . "</th> <th>" . esc_html__('BALANCE', 'p18w') . "</th><th></th>";
        echo "</tr>";
        global $woocommerce;
        $items = $woocommerce->cart->get_cart();
        $retrive_data = WC()->session->get('session_vars');
        $retrieve_ivnum = WC()->session->get('pdt_ivnum');

        $pdts_in_cart = array();
        if (!empty($retrive_data['ordertype'])) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $pdts_in_cart[] = $cart_item['_other_options']['product-ivnum'];
            }
        }

        $i = 1;  
        $todayDate = new DateTime(); 
        foreach ($data->value[0]->ARFNCITEMS3_SUBFORM as $key => $value) {
            //Checking whether the date has passed
            $fncDate = new DateTime($value->FNCDATE);
            $red = ($todayDate > $fncDate) ? 'red' : ''; 
                
            echo "<tr class='pass_date " . $red . "'>";

            //check if already pay for this ivnum
            $disabled = '';
            if (!empty($retrieve_ivnum)) {
                if (in_array($value->IVNUM, $retrieve_ivnum)) {
                    $disabled = 'disabled="disabled"';
                } else {
                    // $disabled = '';
                }
            }
            if (!empty($pdts_in_cart)) {
                if (in_array($value->IVNUM, $pdts_in_cart)) {
                    $checked = 'checked';
                } else {
                    $checked = '';
                }
            }
            $date = $value->BALDATE;
            $createDate = new DateTime($date);
            $strip = $createDate->format('d/m/y');

            if (!isset($checked)) {
                $checked = '';
            }
            echo '<td><input type="checkbox" ' . $checked . ' ' . $disabled . ' name="' . $value->SUMDEBIT . '#' . $value->IVNUM . '#' . $strip . '#' . $value->DETAILS . '" class="obligo_checkbox" data-sum=' . $value->SUMDEBIT . ' data-IVNUM=' . $value->IVNUM . ' value="obligo_chk_sum' . $i . '"></td>';
            
            // Create the array object in a new order
            $order = ['BALDATE', 'FNCDATE', 'ORDNAME', 'IVNUM', 'DETAILS', 'SUMDEBIT', 'SUMCREDIT', 'BAL'];
            $sortedValue = new stdClass();
            foreach ($order as $key) {
                $sortedValue->$key = property_exists($value, $key) ? $value->$key : null;
            }

            foreach ($sortedValue as $Fkey => $Fvalue) {
                if ($Fkey == 'BALDATE' || $Fkey == 'FNCDATE' || $Fkey == 'ORDNAME' || $Fkey == 'IVNUM' || $Fkey == 'DETAILS' || $Fkey == 'SUMDEBIT' || $Fkey == 'SUMCREDIT' || $Fkey == 'BAL') {
                    if ($Fkey == 'BALDATE') {
                        $timestamp = strtotime($Fvalue);
                        echo "<td>" . date('d/m/y', $timestamp) . "</td>";
                    } elseif ($Fkey == 'FNCDATE') {
                        $timestampFnc = strtotime($Fvalue);
                        echo "<td>" . date('d/m/y', $timestampFnc) . "</td>";
                    } else {
                        if ( $Fkey == 'SUMDEBIT' || $Fkey == 'SUMCREDIT' || $Fkey == 'BAL' )
                            $Fvalue = number_format($Fvalue, 2, '.', ',') . ' ש"ח';
                        echo "<td>" . $Fvalue . "</td>";
                    }
                }
            }
            echo "<td>
                    <button style='font-size: 13px!important;' type='button' class='open_doc btn_open_ivnum' data-ivnum='" . htmlspecialchars($value->IVNUM, ENT_QUOTES, 'UTF-8') . "'>" 
                        . __('Presentation of the invoice', 'p18w') . "
                        <div class='loader_wrap'>
                            <div class='loader_spinner'>
                                <div class='line'></div>
                                <div class='line'></div>
                                <div class='line'></div>
                            </div>
                        </div>
                    </button>
                </td>";
            echo "</tr>";
            $i++;
        }
        echo "</table>";

        echo "</form>";
    }
    return true;
});

//change translation from p18w
add_filter('gettext', 'change_priority_plugin_translation', 20, 3);
function change_priority_plugin_translation($translated_text, $text, $domain) {
    // Check if the domain is 'p18w' and the text matches
    if ($domain === 'p18w') {
        switch ($text) {
            case 'Obligo':
                $translated_text = 'חובות פתוחים'; // Replace with your desired text
                break;
        }
    }
    return $translated_text;
}