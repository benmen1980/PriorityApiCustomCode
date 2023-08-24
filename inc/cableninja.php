<?php

use PriorityWoocommerceAPI\WooAPI;

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

    $data['expand'] = '$expand=CPROFITEMS_SUBFORM($select=PARTNAME,TQUANT,PRICE,PDES)';

    $response = WooAPI::instance()->makeRequest('GET',
        'CPROF?$filter=' . $date_filter . '&' . $data['expand']. '', [],
        WooAPI::instance()->option( 'log_items_priority', true ) );

    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);

        foreach ($response_data['value'] as $item) {

            if (CheckExistingProduct($item['CPROFNUM'])) {
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
    //$product_id      = 0;
    $my_query = new \WP_Query( $args );
    
    if ( $my_query->have_posts() ) {
        while ( $my_query->have_posts() ) {
            $my_query->the_post();
            $product_id = get_the_ID();

            $sku_product = get_post_meta($product_id, '_sku', true);
            
            return true; // Product exists
            
        }
    }
    // Product does not exist 

    //create the parent product in site 
    $parent = [];
    $childrens = []; 

    $product_sku = (string)$product_sku;
    
    $attributes = [];
    //add coil attribute
    $attributes['Pieces/lenght'] = ['100'];
    $attributes['Coil'] = ['PQ'];
    $item['attributes'] = $attributes;

    $parent = array(
        'author' => '', // optional
        'title' => 'הצעת מחיר: ' . $product_sku,
        'content' => $item['content'],
        'excerpt' => '',
        'regular_price' => '', // product regular price
        'sale_price' => '', // product sale price (optional)
        'stock' => 'Y', // Set a minimal stock quantity
        'sku' => $product_sku, // optional
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
        "0" => "38",
        "1" => "12971"
    );
    update_field('product_main_attributes', $product_attributes, $id);

    // Add the category to the product
    wp_set_object_terms($id, 'מבצעי ממוספרים ללקוחות', 'product_cat', true);    
          
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
            //'post_content' =>  $content,
            'post_status' => WooAPI::instance()->option( 'item_status' ),
            'post_title'  => $product['PDES'],
            'post_parent' => '',
            'post_type'   => 'product',
            'post_category' => array( 4923 )
        ];

        // Insert product
        $product_id = wp_insert_post( $data );
        if ( $product_id ) {
            
            update_post_meta( $product_id, '_regular_price', $product['PRICE'] );
            // update_post_meta( $post_id, '_sale_price', "1" );

            // Update the stock quantity
            update_post_meta($product_id, '_stock', $product['TQUANT']);
            update_post_meta($product_id, '_stock_status', 'onbackorder');

            // Update the product's "manage stock" option
            update_post_meta($product_id, '_manage_stock', 'yes');

            // Update the product's Possibility of pre-orders
            update_post_meta($product_id, '_backorders', 'yes');

            // Add the category to the product
            wp_set_object_terms($product_id, 'מבצעי ממוספרים ללקוחות', 'product_cat', true);

            update_field('parent_id', $product_sku, $product_id);
            update_field('manufacturer_sku', $partname, $product_id);

            $_attributes = [];

            $_attributes['pa_סידרה'] = $product['PDES'];
            $_attributes['pa_pieces-lenght'] = (string)$product['PRICE'];
            $_attributes['pa_coil'] = 'PQ';
            
            foreach ($_attributes as $name => $value) {
                wp_set_object_terms($product_id, $value, $name, true);
                $children_attributes[$name] = array (
                    'name' => $name, // set attribute name
                    'value' => $value, // set attribute value
                    'is_visible' => 0,
                    'is_variation' => 1,
                    'is_taxonomy' => 1
                );
            }

            update_post_meta($product_id, '_product_attributes', $children_attributes);
            
        }
            
        // And finally (optionally if needed)
        wc_delete_product_transients( $product_id ); // Clear refresh the variation cache
    }     
 
};

/**
* sync CPRof to the offer number
*
*/

function syncCPRofByNumber($sku) {

    $priority_version = (float)WooAPI::instance()->option('priority-version');
    $is_categories = (!empty($config->categories) ? $config->categories : 'מבצעי ממוספרים ללקוחות');
    $res = WooAPI::instance()->option('sync_variations_priority_config');
    $res = str_replace(array('.', "\n", "\t", "\r"), '', $res);
    $config_v = json_decode(stripslashes($res));
    $show_in_web = (!empty($config_v->show_in_web) ? $config_v->show_in_web :  null);
    $show_front = !empty($config_v->show_front) ? $config_v->show_front : null;
    $config = json_decode(stripslashes(WooAPI::instance()->option('setting-config')));
    $chetzdaysback = $config->chetz_days_back;
    $stamp = mktime(0 - $chetzdaysback * 24, 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = 'UDATE ge ' . $bod;
    $search_field = 'CPROFITEMS_SUBFORM($select=PARTNAME)'; //מקט של מוצר בן
    $data['expand'] = '&$expand=CPROFITEMS_SUBFORM($select=PARTNAME,TQUANT,PRICE,PDES)';

    $url_addition_config = (!empty($config_v->additional_url) ? $config_v->additional_url : '');
    $filter = urlencode($url_addition) . ' ' . $url_addition_config;

    //  $sku = 'PQ23000015';

    // get all CPRof from priority
    $response = WooAPI::instance()->makeRequest('GET', 
    'CPROF?$filter=CPROFNUM eq \'' . $sku . '\'' . '&' . $filter . $data['expand']. '', [],
    WooAPI::instance()->option( 'log_items_priority', true ) );


     // check response status
     if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);
        // $product_cross_sells = []; //?
        
        if ($response_data['value'][0] > 0) {
            foreach ($response_data['value'] as $item) {

                if (CheckExistingProduct($item['CPROFNUM'],  $item)) {
                    continue; // Product already exists, skip to the next iteration
                }

            }
        
            // add timestamp
            // WooAPI::instance()->updateOption('items_priority_variation_update', time());
        } else {
            WooAPI::instance()->sendEmailError(
                WooAPI::instance()->option('email_error_sync_items_priority_variation'),
                'Error Sync Items Priority Variation',
                $response['body']
            );
            exit(json_encode(['status' => 0, 'msg' => 'Error Sync Items Priority Variation']));
            $subj = 'check sync item';
            wp_mail( 'margalit.t@simplyct.co.il', $subj, implode(" ",$response) );
        }
    }

};

function custom_add_endpoint($sku) {
    add_rewrite_endpoint( 'open-quote-num', EP_ALL );
    // $sku = 'PQ23000015'; // Replace with the desired SKU value
    $endpoint_url = add_query_arg( 'open-quote-num', $sku, home_url('/open-quote' . '/') );
    wp_redirect($endpoint_url); // Redirect to the URL with the query variable set

    exit;
}
add_action( 'init', 'custom_add_endpoint', 10);

function custom_process_product_endpoint() {
    if ( $_GET['open-quote-num' ] ) {
        $quote_param = $_GET['open-quote-num'];
                
        // Call the fixed function with the SKU parameter
        syncCPRofByNumber($quote_param);
        exit; // Make sure to exit after executing the code.
    }
}
add_action( 'template_redirect', 'custom_process_product_endpoint', 1 );


/*add_action( 'a_sync_document_price_cron_hook', 'syncCPRofPriority');

if ( ! wp_next_scheduled( 'a_sync_document_price_cron_hook' ) ) {

    $res = wp_schedule_event( time(), 'none', 'a_sync_document_price_cron_hook' );

}*/