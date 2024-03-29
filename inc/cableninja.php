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
                'value' => $product_sku
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
        'title' => 'הצעת מחיר: ' . $product_sku . ' ל: ' . $item['CDES'],
        'content' => $cleanedText,
        'excerpt' => '',
        'regular_price' => '', // product regular price
        'sale_price' => '', // product sale price (optional)
        'stock' => 'Y', // Set a minimal stock quantity
        'sku' => $product_sku, // optional
        'image_id' => (!empty($attach_id) && $attach_id != 0) ? $attach_id : '', // optional
        'image_file' => (!empty($image_quote)) ? $image_quote : '', // optional
        // For NEW attributes/values use NAMES (not slugs)
        'attributes' => $attributes,
        'categories' => [
            $item[$is_categories]
        ],
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
        "0" => "12975",
        "1" => "12977",
        "2" => "12978",
        "3" => "12979"
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
    
    // Add the category to the product
    wp_set_object_terms($id, 'מבצעי ממוספרים ללקוחות', 'product_cat', true);

    $_quote = wc_get_product($id);
    $_quote->set_catalog_visibility('hidden');
    $_quote->save();
        
    $quote_link = get_permalink($id);
         
    //create childrens products in site
    $childrens[$item[$product_sku]][$product_sku] = [
        'sku' => $product_sku,
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

    // }
        
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

            update_field('parent_id', $product_sku, $product_id);
            update_field('manufacturer_sku', $product['BARCODE'], $product_id);

            // Set the attribute name and term value
            $_attributes = array();

            $comment_text = $product['CPROFITEMSTEXT_SUBFORM']['TEXT'];
            $comment  = ' ' . html_entity_decode( $comment_text );

            $_attributes['pa_סידרה'] = 'standart';
            $_attributes['pa_coil'] = 'PQ';
            $_attributes['pa_יחידת-מידה'] = $product['TUNITNAME'];
            $quant = '' . (int)$product['TQUANT'];
            $_attributes['pa_כמות'] = $quant;
            $_attributes['pa_מחיר-ליחידה'] = $product['PRICE'];
            $_attributes['pa_זמן-אספקה'] = $back_order;
            $_attributes['pa_הערה'] = $comment;

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
            
        }
        $_product = new \WC_Product($product_id);
        $_product->set_catalog_visibility('hidden');
        $_product->save();
            
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
    //  $quote_token = null) {

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
    // $quote_token = null;
    // $quote_token =  ' and ROYY_RAND eq \'' . $quote_token . '\'';
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
                
                // if (CheckExistingProduct($item['CPROFNUM'],  $item)) {
                //     continue; // Product already exists, skip to the next iteration
                // }

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
        redirect($link_quote);
        // exit; // Make sure to exit after executing the code.       
    }
    // return $link_p;

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

// Hook into the filter used by the original plugin
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

// Hook into the filter used by the original plugin
add_filter('add_button_shopping_cart', 'add_button_shopping_cart_func');

// function add_attache_priority_quote_func($values) {
//     $manufacturer_sku = $values['value1'];
//     $parent_id = $values['value2'];
    
//     $query_args = array(
//         'post_type' => array( 'product', 'product_variation' ),
//         'post_status' => 'publish',
//         'meta_query' => array(
//             '0' => array(
//                 'key' => 'manufacturer_sku',
//                 'value' =>  $manufacturer_sku,
//                 'compare' => '=',
//             ),
//             '1' => array(
//                 'key' => 'parent_id',
//                 'value' => $parent_id,
//                 'compare' => '=',
//             ),
//             'relation' => 'AND',
//         ),
//     );
    
//     // The Query
//     $the_query = new WP_Query( $query_args );
    
//     // The Loop
//     if ( $the_query->have_posts() ) {
//         while ( $the_query->have_posts() ) {
//             $the_query->the_post();

//             $product_url = get_permalink();

//             //get the acf field 'link_array'
//             $link = get_field('spec_link');
//             if( !empty($link) ) { 
//                 $link_url = $link['url'];
//                 $link_title = $link['title'];
//                 $link_target = $link['target'] ? $link['target'] : '_blank';
//             }
//         }
//     }
    
//     if( !empty($link_url) ) {
//         $attache = "<td style='white-space: normal!important;'><a href='".$link_url."' target='_blank' >
//                             <img src='".get_stylesheet_directory_uri()."/assets/images/spec.svg' alt='Spec' style= 'width: 20px!important; max-width: 250%;'>
//                             <span></span>
//                     </a></td>";
//     } 
//     return $attache;
// }

// add_filter('add_attache_priority_quote', 'add_attache_priority_quote_func');

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

add_action('add_message_front_priorityQuotes', function(){?>
    <p><?php echo 'ברירת המחדל להצגה היא חצי שנה אחורה. ניתן לעדכן מתאריך ועד תאריך ועל ידי כך להביא הצעות בטווח תאריכים שונה.'?></p>      
<?php }); 

add_filter('add_attache_priority', 'add_attache_priority_func');

add_action('add_message_front_priorityOrders', function(){?>
    <p><?php echo 'ברירת המחדל להצגה היא חצי שנה אחורה. ניתן לעדכן מתאריך ועד תאריך ועל ידי כך להביא הזמנות בטווח תאריכים שונה.'?></p>      
<?php }); 


add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
    if (empty($data['CUSTNAME'])) {
        $cust_name = WooAPI::instance()->option('walkin_number');
        $data['CUSTNAME'] =$cust_name;
    }

    if (isset($data['CDES'])){
        unset($data['CDES']);
    };
    return $data;   
}

add_filter('simply_modify_orderitem', 'my_custom_orderitem_modifier');

function my_custom_orderitem_modifier($args) {
    // Access the data and item from the filter
    $data = $args['data'];
    $item = $args['item'];

    // unset($data['ORDERITEMS_SUBFORM']['PARTNAME']);
    $product = $item->get_product();
    if ($product) {
        $product_id = $product->get_id();
        $barcode = get_field('manufacturer_sku', $product_id);
        $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['BARCODE'] = $barcode;
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


add_filter('simplyct_sendEmail', 'simplyct_sendEmail_func');
function simplyct_sendEmail_func($emails)
{
    array_push($emails, 'Yoav@arrowcables.com');
    return $emails;
}