<?php
use PriorityWoocommerceAPI\WooAPI;

add_action('syncItemsPriority_hook', 'syncItemsPriority');

if (!wp_next_scheduled('syncItemsPriority_hook')) {

   $res = wp_schedule_event(time(), 'daily', 'syncItemsPriority_hook');

}

function syncItemsPriority() {

    $priority_version = (float) WooAPI::instance()->option( 'priority-version' );
    // config
    $raw_option     = WooAPI::instance()->option( 'sync_items_priority_config' );
    $raw_option     = str_replace( array( "\n", "\t", "\r" ), '', $raw_option );
    $config         = json_decode( stripslashes( $raw_option ) );
    $daysback            = ( ! empty( (int) $config->days_back ) ? $config->days_back : 1 );
    $url_addition_config = ( ! empty( $config->additional_url ) ? $config->additional_url : '' );
    $search_field        = ( ! empty( $config->search_by ) ? $config->search_by : 'PARTNAME' );
    $search_field_web    = ( ! empty( $config->search_field_web ) ? $config->search_field_web : '_sku' );
    $stock_status        = ( ! empty( $config->stock_status ) ? $config->stock_status : 'outofstock' );
    $is_categories       = ( ! empty( $config->categories ) ? $config->categories : null );
    $statdes             = ( ! empty( $config->statdes ) ? $config->statdes : false );
    $is_attrs            = ( ! empty( $config->attrs ) ? $config->attrs : false );
    $is_update_products  = ( ! empty( $config->is_update_products ) ? $config->is_update_products : false );
    $show_in_web         = ( ! empty( $config->show_in_web ) ? $config->show_in_web : 'SHOWINWEB' );
    // get the items simply by time stamp of today
    $stamp          = mktime( 0 - $daysback * 24, 0, 0 );
    $bod            = date( DATE_ATOM, $stamp );
    $date_filter    = 'UDATE ge ' . urlencode( $bod );
    // $date_filter    = 'PARTNAME eq \'RW-B201\'';
    $data['select'] = 'PARTNAME,PARTDES,REUT_SUBTITLE,REUT_ISBN,REUT_DANACODE,VATPRICE,BASEPLPRICE,SPEC1,SPEC5,EXTFILENAME,INVFLAG,SHOWINWEB,SERNFLAG';
    $data['expand'] = '$expand=ECPARTCATEGORIES_SUBFORM($select=ECATCODE,REUT_SUBECATCODE,REUT_SUBSUBCATCODE),REUT_AUTHORS_SUBFORM($select=NAME_AUTH),REUT_TRAN_SUBFORM($select=NAME_TRAN),PARTTEXT_SUBFORM($select=TEXT),PARTUNSPECS_SUBFORM($select=VALUE)';

    //$response = WooAPI::instance()->makeRequest( 'GET',
    //'LOGPART?$select=' . $data['select'] . '&$filter=' . $date_filter . ' and PARTNAME eq \'ORB-2558\' and ISMPART ne \'Y\' ' . $url_addition_config .
    //'&' . $data['expand'] . '', [],
   // WooAPI::instance()->option( 'log_items_priority', true ) );
   
	$response = WooAPI::instance()->makeRequest( 'GET',
    'LOGPART?$select=' . $data['select'] . '&$filter=' . $date_filter . ' and ISMPART ne \'Y\' ' . $url_addition_config .
    '&' . $data['expand'] . '', [],
    WooAPI::instance()->option( 'log_items_priority', true ) );

    

    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);
		echo "<pre>";
		print_r($response);
		echo "</pre>";
        try {
            foreach ( $response_data['value'] as $item ) {
                if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
                    error_log($item['PARTNAME']);
                }
                // add long text from Priority
                $content      = '';
                $post_content = '';
                if ( isset( $item['PARTTEXT_SUBFORM'] ) ) {
                    foreach ( $item['PARTTEXT_SUBFORM'] as $text ) {
                        $clean_text = preg_replace('/<style>.*?<\/style>/s', '', $text);
                        $content .= ' ' . html_entity_decode( $clean_text );
                    }
                }
                $data = [
                    'post_author' => 1,
                    //'post_content' =>  $content,
                    'post_status' => WooAPI::instance()->option( 'item_status' ),
                    'post_title'  => $item['PARTDES'],
                    'post_excerpt' => $item['REUT_SUBTITLE'],
                    'post_parent' => '',
                    'post_type'   => 'product',
                ];
                //if ( $synclongtext ) {
                $data['post_content'] = $content;
                //}

                // if product exsits, update
                $search_by_value = (string) $item[ $search_field ];
                $args            = array(
                    'post_type'   => array( 'product', 'product_variation' ),
                    'post_status' => array( 'publish', 'draft' ),
                    'meta_query'  => array(
                        array(
                            'key'   => $search_field_web,
                            'value' => $search_by_value
                        )
                    )
                );

                $product_id      = 0;
                $my_query        = new \WP_Query( $args );
                if ( $my_query->have_posts() ) {
                    while ( $my_query->have_posts() ) {
                        $my_query->the_post();
                        $product_id = get_the_ID();
                    }
                }
                if ( $product_id != 0 ) {
                    $_product = wc_get_product( $product_id );
                }

                // if ( $product_id == 0 ) {
                //     continue;
                // }
                // check if the item flagged as show in web, if not skip the item
                if ( isset( $show_in_web ) ) {
                    if ( $product_id == 0 && $item[ $show_in_web ] != 'Y' ) {
                        continue;
                    }
                    if ( $product_id != 0 && $item[ $show_in_web ] != 'Y' ) {
                        //$_product->set_status( 'draft' );
                        $_product->set_catalog_visibility( 'hidden' );
                        $_product->save();
                        continue;
                    }
                }
                // check if update existing products
                if ( $product_id != 0 && false == $is_update_products ) {
                    $item['product_id'] = $product_id;
                    do_action('simply_update_product_price', $item);
                    continue;
                }
                
                // update product
                if ( $product_id != 0 ) {
                    $data['ID'] = $product_id;
                    $_product->set_status(WooAPI::instance()->option('item_status'));
                  
                    $_product->save();
                    // Update post
                    $id = $product_id;
                    global $wpdb;
                    // @codingStandardsIgnoreStart
                    //if ( $synclongtext ) {
                        $wpdb->query(
                            $wpdb->prepare(
                                "
                        UPDATE $wpdb->posts
                        SET post_title = '%s',
                        post_excerpt = '%s',
                        post_content = '%s'
                        WHERE ID = '%s'
                        ",
                                $item['PARTDES'],
                                $item['REUT_SUBTITLE'],
                                $content,
                                $id
                            )
                        );
                        $content1 = apply_filters('the_content', get_post_field('post_content', $id));
                    // } else {
                    //     $wpdb->query(
                    //         $wpdb->prepare(
                    //             "
                    //     UPDATE $wpdb->posts
                    //     SET post_title = '%s',
                    //     post_excerpt = '%s',
                    //     WHERE ID = '%s'
                    //     ",
                    //             $item['PARTDES'],
                    //             $item['REUT_SUBTITLE'],
                    //             $id
                    //         )
                    //     );
                    // }
                } else {
                    // Insert product
                    $id = wp_insert_post( $data );
                    if ( $id ) {
                        update_post_meta( $id, '_sku', $search_by_value );
                        update_post_meta( $id, '_stock_status', $stock_status );
                        if ( $stock_status == 'outofstock' ) {
                            update_post_meta( $id, '_stock', 0 );
                            wp_set_post_terms( $id, 'outofstock', 'product_visibility', true );
                        }
                        // if ( ! empty( $item['INVFLAG'] ) ) {
                        //     update_post_meta( $id, '_manage_stock', ( $item['INVFLAG'] == 'Y' ) ? 'yes' : 'no' );
                        // }
                    }
                }
                //update price
                $pri_price = (wc_prices_include_tax() == true || $set_tax == 'no') ? $item['VATPRICE'] : $item['BASEPLPRICE'];
                if ( $id ) {
                    $my_product = new \WC_Product( $id );
                    if ( ! empty( $show_in_web ) && $item[ $show_in_web ] != 'Y' ) {
                        $my_product->set_catalog_visibility( 'hidden' );
                        $my_product->save();
                        continue;
                    }
                    // price
                    $my_product->set_regular_price( $pri_price );
                    $my_product->save();
                
                   
                }
                //update isbn meta field
                update_post_meta($id, 'isbn_product_field', $item['REUT_ISBN']);
                update_post_meta($id, 'danacode_product_field', $item['REUT_DANACODE']);
                
                //update categories
                if ( isset( $item['ECPARTCATEGORIES_SUBFORM'] ) ) {
                    foreach ( $item['ECPARTCATEGORIES_SUBFORM'] as $cat ) {
                        $cat1 = (!empty($cat["ECATCODE"])) ? $cat["ECATCODE"] : '';
                        $cat2 = (!empty($cat["REUT_SUBECATCODE"])) ? $cat["REUT_SUBECATCODE"] : '';
                        $cat3 = (!empty($cat["REUT_SUBSUBCATCODE"])) ? $cat["REUT_SUBSUBCATCODE"] : '';
                        simplyct_set_categories($item['PARTNAME'],$cat1,$cat2,$cat3);
                    }
                }
                // update attributes
                if ( $is_attrs != false ) {
					unset($thedata);
                    //תגיות מוצר נוספות
                    if(isset($item['PARTUNSPECS_SUBFORM'])){
                        foreach ( $item['PARTUNSPECS_SUBFORM'] as $attribute ) {
                            $attr_name  = 'תגיות מוצר נוספות';
                            $attr_slug  = 'product-tag-badge';
                            $attr_value = $attribute['VALUE'];
                            if ( ! WooAPI::instance()->is_attribute_exists( $attr_slug ) ) {
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
                            wp_set_object_terms( $id, $attr_value, 'pa_' . $attr_slug, false );
							$thedata['pa_' . $attr_slug] = array(
                                'name' => 'pa_' . $attr_slug,
                                'value' => '',
                                'is_visible' => '1',
                                'is_taxonomy' => '1'
                            );
                            
                        }
                    }
                    
                    //הוצאה לאור
                    if(isset($item['SPEC1'])){
                        $attr_name  = 'הוצאה לאור';
                        $attr_slug  = 'הוצאה-לאור';
                        $attr_value = $item['SPEC1'];
                        if ( ! WooAPI::instance()->is_attribute_exists( $attr_slug ) ) {
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
                        wp_set_object_terms( $id, $attr_value, 'pa_' . $attr_slug, false );
						$thedata['pa_' . $attr_slug] = array(
                                'name' => 'pa_' . $attr_slug,
                                'value' => '',
                                'is_visible' => '1',
                                'is_taxonomy' => '1'
                            );
                    }

                    //שנת הוצאה
                    if(isset($item['SPEC5'])){
                        $attr_name  = 'שנת הוצאה';
                        $attr_slug  = 'שנת-הוצאה';
                        $attr_value = $item['SPEC5'];
                        if ( ! WooAPI::instance()->is_attribute_exists( $attr_slug ) ) {
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
                        wp_set_object_terms( $id, $attr_value, 'pa_' . $attr_slug, false );
						$thedata['pa_' . $attr_slug] = array(
                                'name' => 'pa_' . $attr_slug,
                                'value' => '',
                                'is_visible' => '1',
                                'is_taxonomy' => '1'
                            );
                    }

                    //סופר
                    if(isset($item['REUT_AUTHORS_SUBFORM'])){
                        foreach ( $item['REUT_AUTHORS_SUBFORM'] as $attribute ) {
                            $attr_name  = 'סופר';
                            $attr_slug  = 'book-author';
                            $attr_value = $attribute['NAME_AUTH'];
                            if ( ! WooAPI::instance()->is_attribute_exists( $attr_slug ) ) {
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
                            wp_set_object_terms( $id, $attr_value, 'pa_' . $attr_slug, false );
							$thedata['pa_' . $attr_slug] = array(
                                'name' => 'pa_' . $attr_slug,
                                'value' => '',
                                'is_visible' => '1',
                                'is_taxonomy' => '1'
                            );
                            
                        }
                    }
					 if ( ! empty( ( $thedata ) ) ) {
                        update_post_meta($id, '_product_attributes', $thedata);
                    }
                }

                // sync image
                $is_load_image = json_decode( $config->is_load_image );
                if ( false == $is_load_image ) {
                    continue;
                }
                $sku          = $item[ $search_field ];
                $is_has_image = get_the_post_thumbnail_url( $id );
                $image_url = $item['EXTFILENAME'];
                //for test
                //$image_url = "https://www.w3schools.com/tags/img_girl.jpg";
                if ( WooAPI::instance()->option( 'update_image' ) == true || ! get_the_post_thumbnail_url( $id ) ) {
                    $file_name = basename($image_url); //img_girl.jpg

                    // Download image data
                    $image_data = wp_remote_get($image_url);
                    
                    if (is_wp_error($image_data)) {
                        return false;
                    }

                    // Get the image body
                    $image_body = wp_remote_retrieve_body($image_data);
                    
                    // Get the upload directory
                    $upload_dir = wp_upload_dir();
                    $file_path = $upload_dir['path'] . '/' . $file_name;

                    // Save the image file
                    file_put_contents($file_path, $image_body);

                    // Check the file type and upload to WordPress
                    $file_type = wp_check_filetype($file_name, null);
                    $attachment = array(
                        'post_mime_type' => $file_type['type'],
                        'post_title'     => sanitize_file_name($file_name),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );

                    $attach_id = wp_insert_attachment($attachment, $file_path); //418489
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    set_post_thumbnail( $id, $attach_id );
                }

            }
        }
        catch (Exception $e) {
            // Exception handling code
            echo "Exception caught: " . $e->getMessage();
            // add timestamp
            WooAPI::instance()->updateOption('items_priority_update', time());
        }
    }
    else {
        WooAPI::instance()->sendEmailError(
            WooAPI::instance()->option('email_error_sync_items_priority'),
            'Error Sync Items Priority',
            $response['body']
        );
    }
 
    return $response;


}

add_action('syncInventoryPriority_hook', 'syncInventoryPriority');

if (!wp_next_scheduled('syncInventoryPriority_hook')) {

   $res = wp_schedule_event(time(), 'every_five_minutes', 'syncInventoryPriority_hook');

}
/**
 * sync inventory from priority
 */

function syncInventoryPriority()
{

    // get the items simply by time stamp of today
    $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
    $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = '('. rawurlencode('WARHSTRANSDATE ge ' . $bod . ' or PURTRANSDATE ge ' . $bod . ' or SALETRANSDATE ge ' . $bod) . ')';

    
    $data['select'] = 'PARTNAME';


    
    $expand = '$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM($select=WARHSNAME,TBALANCE)';
        
    
    $data['expand'] = $expand;
    $response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter='.$url_addition.' and INVFLAG eq \'Y\' &' . $data['expand'], [], WooAPI::instance()->option('log_inventory_priority', false));
    //$response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter='.$url_addition.' or PARTNAME eq \'ORB-1023769\' and INVFLAG eq \'Y\' &' . $data['expand'], [], WooAPI::instance()->option('log_inventory_priority', false));
    // check response status       
    echo 'custom sync inventory';
    echo "<pre>";
    print_r($response);
    echo "</pre>";
    if ($response['status']) {
        $data = json_decode($response['body_raw'], true);
        foreach ($data['value'] as $item) {
            // if product exsits, update
            $field =  'PARTNAME';
            $args = array(
                'post_type' => array('product', 'product_variation'),
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'value' => $item[$field]
                    )
                )
            );
            $my_query = new \WP_Query($args);
            if ($my_query->have_posts()) {
                while ($my_query->have_posts()) {
                    $my_query->the_post();
                    $product_id = get_the_ID();
                }
            } else {
                $product_id = 0;
            }
            //if ($id = wc_get_product_id_by_sku($item['PARTNAME'])) {
            if (!$product_id == 0) {
                // update_post_meta($product_id, '_sku', $item['PARTNAME']);
                // get the stock by part availability
                
                // get the stock by specific warehouse
                
                foreach ($item['PARTBALANCE_SUBFORM'] as $wh_stock) {
                    $store = $wh_stock['WARHSNAME'];
                    if (function_exists('simplyct_set_stock')) {
                        simplyct_set_stock( $item[$field], $store, $wh_stock['TBALANCE'] );
                    }
                   
                }
                
      
         
   
            }
        }
        // add timestamp
        //WooAPI::instance()->updateOption('inventory_priority_update', time());
    } else {
        /**
         * t149
         */
        WooAPI::instance()->sendEmailError(
            WooAPI::instance()->option('email_error_sync_inventory_priority'),
            'Error Sync Inventory Priority',
            $response['body']
        );
    }
}

add_filter('simply_request_data', 'simply_func');
function simply_func($data){

	$order_id = $data['orderId'];

    $order = wc_get_order($order_id);

    $store_switcher = get_post_meta($order_id, 'store_switcher', true);
    $shipping_methods = $order->get_shipping_methods();
	foreach ($shipping_methods as $shipping_method) {
        if ($shipping_method->get_method_id() === 'local_pickup') {
            $data['WARHSNAME'] = $store_switcher;
        }
    }

    $billing_address_floor = get_post_meta($order_id, '_billing_address_floor', true);
	$billing_address_apartment = get_post_meta($order_id, '_billing_address_apartment', true);
	$billing_address_entrance = get_post_meta($order_id, '_billing_address_entrance', true);

    $shipping_address_apartment = get_post_meta( $order_id, '_shipping_address_apartment', true ) ? get_post_meta( $order_id, '_shipping_address_apartment', true ) : $billing_address_apartment;
    $shipping_address_floor = get_post_meta( $order_id, 'shipping_address_floor', true ) ? get_post_meta( $order_id, '_shipping_address_floor', true ) : $billing_address_floor;
    $shipping_address_entrance = get_post_meta( $order_id, 'shipping_address_entrance', true ) ? get_post_meta( $order_id, ')shipping_address_entrance', true ) : $billing_address_entrance;

    
    $data['ORDERSCONT_SUBFORM'][0]['ADRS3'] = __( 'floor:', 'woodmart' ).$billing_address_floor.' '.__( 'apartment:', 'woodmart' ).$billing_address_apartment.' '.__( 'entrance:', 'woodmart' ).$billing_address_entrance;
    $data['SHIPTO2_SUBFORM']['ADDRESS3'] = __( 'floor:', 'woodmart' ).$shipping_address_floor.' '.__( 'apartment:', 'woodmart' ).$shipping_address_apartment.' '.__( 'entrance:', 'woodmart' ).$shipping_address_entrance;

    //$token =  $order->get_meta('CardcomToken');
    //$data['PAYMENTDEF_SUBFORM']['CCUID'] = $token;
    $order_cc_meta = $order->get_meta('_transaction_data');
    $idnum = $order_cc_meta['CardHolderID'];
    $data['PAYMENTDEF_SUBFORM']['IDNUM'] = $idnum;
    $ccuid = $order_cc_meta['Token'];
	//append tk578 to ccuid number
	$data['PAYMENTDEF_SUBFORM']['CCUID'] = 'tk578'.$ccuid;
	//remove confnum
	unset($data['PAYMENTDEF_SUBFORM']['CONFNUM']);
   
    return $data;
}

function example_add_cron_interval($schedules) {
    // Adds every 5 minutes to the existing schedules
    $schedules['every_five_minutes'] = array(
        'interval' => 300, // 300 seconds = 5 minutes
        'display'  => __('Every Five Minutes')
    );
    return $schedules;
}
add_filter('cron_schedules', 'example_add_cron_interval');


function send_custom_webhook( $record, $handler ) {

	$form_name = $record->get_form_settings('form_id');
	// Replace MY_FORM_NAME with the name you gave your form
	if ( 'contact' !== $form_name ) {
		return;
	}
    
	$raw_fields = $record->get( 'fields' );
	$fields = [];
	$headers = array('Content-Type: text/html; charset=UTF-8');
	foreach ( $raw_fields as $id => $field ) {
		$field_value  = $field['value'];
        //write_custom_log('Field Value: ' . $field_value);
        $field_values[ $field['id'] ] = $field_value;
        $fname = $field_values['name'];
        $lname = $field_values['lname'];
        $email = $field_values['email'];
        $phone = $field_values['phone'];
        $order_id = $field_values['order_id'];
        $subject = $field_values['subject'];
        $message = $field_values['message'];
	}

    $data = [
        //'CUSTNOTETYPEDES' => $subject,
        'SAPL_BOOKNUM' => $order_id,
        'SAPL_EMAIL' => $email,
        'SAPL_NAME' => $fname.' '.$lname,
        'SAPL_PHONE' => $phone,
        'SUBJECT' =>  $subject,
    ];
    $data['CUSTNOTESTEXT_SUBFORM'] = [
        'TEXT' => $message
    ];

    $response = WooAPI::instance()->makeRequest('POST', 'CUSTNOTESA', ['body' => json_encode($data)],true);
   

    if ($response['code'] <= 201 && $response['code'] >= 200) {
        $body_array = json_decode($response["body"], true);
        $custnote = $body_array["CUSTNOTE"];
    }
    else {
        $mes_arr = json_decode($response['body']);
        if(isset($mes_arr->FORM->InterfaceErrors->text)){
            $submittedFail = $mes_arr->FORM->InterfaceErrors->text;
            $multiple_recipients = array(
                get_bloginfo('admin_email')
            );
            $subj = 'Error post contact to priority';
            wp_mail( $multiple_recipients, $subj, $submittedFail );
        }
    }
    
}
add_action( 'elementor_pro/forms/new_record', 'send_custom_webhook', 10, 2 );

function write_custom_log($log_msg)
{

    $uploads = wp_upload_dir(null, false);
    $log_filename = $uploads['basedir'] . '/logs';
    if (!file_exists($log_filename)) {
        // create directory/folder uploads.
        mkdir($log_filename, 0777, true);
    }

    $log_file_data = $log_filename . '/' . date('d-M-Y') . '.log';
    // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
    file_put_contents($log_file_data, date('H:i:s') . ' ' . $log_msg . "\n", FILE_APPEND);
}