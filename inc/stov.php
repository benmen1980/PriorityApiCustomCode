<?php
use PriorityWoocommerceAPI\WooAPI;

//sync item is coming now from sync plugin and run inserver job 
//add_action('syncItemsPriority_hook', 'syncItemsPriority');

if (!wp_next_scheduled('syncItemsPriority_hook')) {

   $res = wp_schedule_event(time(), 'daily', 'syncItemsPriority_hook');

}

function syncItemsPriority_old() {

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
    $data['expand'] = '$expand=ECPARTCATEGORIES_SUBFORM($select=ECATCODE,REUT_SUBECATCODE,REUT_SUBSUBCATCODE),REUT_AUTHORS_SUBFORM($select=NAME_AUTH),REUT_TRAN_SUBFORM($select=NAME_TRAN),PARTTEXT_SUBFORM($select=TEXT),,REUT_LABELSFORBOOK_SUBFORM($select=REUT_BOOKLABEL),REUT_PUBLISHERS_SUBFORM($select=PUBLISHER)';

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
		// echo "<pre>";
		// print_r($response);
		// echo "</pre>";
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
                    //old code
                    // if(isset($item['PARTUNSPECS_SUBFORM'])){
                    //     foreach ( $item['PARTUNSPECS_SUBFORM'] as $attribute ) {
                    //         $attr_name  = 'תגיות מוצר נוספות';
                    //         $attr_slug  = 'product-tag-badge';
                    //         $attr_value = $attribute['VALUE'];
                    //         if ( ! WooAPI::instance()->is_attribute_exists( $attr_slug ) ) {
                    //             $attribute_id = wc_create_attribute(
                    //                 array(
                    //                     'name'         => $attr_name,
                    //                     'slug'         => $attr_slug,
                    //                     'type'         => 'select',
                    //                     'order_by'     => 'menu_order',
                    //                     'has_archives' => 0,
                    //                 )
                    //             );
                    //         }
                    //         wp_set_object_terms( $id, $attr_value, 'pa_' . $attr_slug, false );
					// 		$thedata['pa_' . $attr_slug] = array(
                    //             'name' => 'pa_' . $attr_slug,
                    //             'value' => '',
                    //             'is_visible' => '1',
                    //             'is_taxonomy' => '1'
                    //         );
                            
                    //     }
                    // }
                    if (isset($item['REUT_LABELSFORBOOK_SUBFORM'])) {
						$attr_name  = 'תגיות מוצר נוספות';
						$attr_slug  = 'product-tag-badge';

						// Ensure the attribute exists before setting terms
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

						// Collect all tag values into an array
						$tag_values = array_map(function($attribute) {
							return $attribute['REUT_BOOKLABEL'];
						}, $item['REUT_LABELSFORBOOK_SUBFORM']);

						// Set all tag terms at once, replacing any existing terms
						wp_set_object_terms($id, $tag_values, 'pa_' . $attr_slug, false);

						// Update the product attributes data
						$thedata['pa_' . $attr_slug] = array(
							'name'        => 'pa_' . $attr_slug,
							'value'       => '',
							'is_visible'  => '1',
							'is_taxonomy' => '1',
						);
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
                    // הוצאה לאור
                    //replace spec1 change when reut will update us
                    if (isset($item['REUT_PUBLISHERS_SUBFORM'])) {
                        $attr_name  = 'הוצאה לאור';
                        $attr_slug  = 'הוצאה-לאור';

                       // Ensure the attribute exists before setting terms
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

                       // Collect all tag values into an array
                       $publisher_values = array_map(function($attribute) {
                           return $attribute['PUBLISHER'];
                       }, $item['REUT_PUBLISHERS_SUBFORM']);

                       // Set all tag terms at once, replacing any existing terms
                       wp_set_object_terms($id, $publisher_values, 'pa_' . $attr_slug, false);

                       // Update the product attributes data
                       $thedata['pa_' . $attr_slug] = array(
                           'name'        => 'pa_' . $attr_slug,
                           'value'       => '',
                           'is_visible'  => '1',
                           'is_taxonomy' => '1',
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
                    // if(isset($item['REUT_AUTHORS_SUBFORM'])){
                    //     foreach ( $item['REUT_AUTHORS_SUBFORM'] as $attribute ) {
                    //         $attr_name  = 'סופר';
                    //         $attr_slug  = 'book-author';
                    //         $attr_value = $attribute['NAME_AUTH'];
                    //         if ( ! WooAPI::instance()->is_attribute_exists( $attr_slug ) ) {
                    //             $attribute_id = wc_create_attribute(
                    //                 array(
                    //                     'name'         => $attr_name,
                    //                     'slug'         => $attr_slug,
                    //                     'type'         => 'select',
                    //                     'order_by'     => 'menu_order',
                    //                     'has_archives' => 0,
                    //                 )
                    //             );
                    //         }
                    //         wp_set_object_terms( $id, $attr_value, 'pa_' . $attr_slug, false );
					// 		$thedata['pa_' . $attr_slug] = array(
                    //             'name' => 'pa_' . $attr_slug,
                    //             'value' => '',
                    //             'is_visible' => '1',
                    //             'is_taxonomy' => '1'
                    //         );
                            
                    //     }
                    // }
                    if (isset($item['REUT_AUTHORS_SUBFORM'])) {
                        $attr_name  = 'סופר';
                        $attr_slug  = 'book-author';
                        
                        // Ensure the attribute exists before setting terms
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
                    
                        // Collect author names into an array
                        $author_names = array_map(function($attribute) {
                            return $attribute['NAME_AUTH'];
                        }, $item['REUT_AUTHORS_SUBFORM']);
                    
                        // Set all author names at once, replacing any existing terms
                        wp_set_object_terms($id, $author_names, 'pa_' . $attr_slug, false);
                    
                        // Update the product attributes data
                        $thedata['pa_' . $attr_slug] = array(
                            'name'        => 'pa_' . $attr_slug,
                            'value'       => '',
                            'is_visible'  => '1',
                            'is_taxonomy' => '1',
                        );
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

function syncItemsPriority() {
    // config
    $index = 0;
    $step  = 2;
    //		$step  = 100;
            
        $response_data       = [ 0 ];
        $raw_option          = str_replace( [
            "\n",
            "\t",
            "\r"
        ], '', WooAPI::instance()->option( 'sync_items_priority_config' ) );
        $config              = json_decode( stripslashes( $raw_option ) );
        $daysback            = ( ! empty( (int) $config->days_back ) ? $config->days_back : 1 );
        $is_load_image = json_decode( $config->is_load_image );
        $stamp          = mktime( 0 - $daysback * 24, 0, 0 );
        $bod            = date( DATE_ATOM, $stamp );
        $date_filter    = 'UDATE ge ' . urlencode( $bod );
        $url_addition_config = ( ! empty( $config->additional_url ) ? $config->additional_url : '' );
        //$log_enable          = WooAPI::instance()->option( 'log_items_priority', true );
        $total_products = 0;
        $stats_total    = [];
        $show_in_web         = ( ! empty( $config->show_in_web ) ? $config->show_in_web : 'SHOWINWEB' );
        
        $data_select['select'] = 'PARTNAME,PARTDES,REUT_SUBTITLE,REUT_ISBN,REUT_DANACODE,VATPRICE,BASEPLPRICE,SPEC1,SPEC5,EXTFILENAME,INVFLAG,SHOWINWEB,SERNFLAG';
        $data_expand['expand'] = '$expand=ECPARTCATEGORIES_SUBFORM($select=ECATCODE,REUT_SUBECATCODE,REUT_SUBSUBCATCODE),REUT_AUTHORS_SUBFORM($select=NAME_AUTH),REUT_TRAN_SUBFORM($select=NAME_TRAN),PARTTEXT_SUBFORM($select=TEXT),REUT_LABELSFORBOOK_SUBFORM($select=REUT_BOOKLABEL),REUT_PUBLISHERS_SUBFORM($select=PUBLISHER)';
        
        import_start();
        $check1 = date('Y-m-d H:i:s');
        // todo: change this while to proper finish logic
        while ( sizeof( $response_data ) > 0 ) {
            //$request = 'LOGPART?$select=' . $data_select['select'] . '&$filter= ISMPART ne \'Y\' and SHOWINWEB eq \'Y\' &$skip=' . $index . '&$top=' . $step . '' . $url_addition_config . '&' . $data_expand['expand'] . '';
            $request = 'LOGPART?$select=' . $data_select['select'] . '&$filter=' . $date_filter . ' and STATDES eq \'מאושר\' and ISMPART ne \'Y\' &$skip=' . $index . '&$top=' . $step . '' . $url_addition_config . '&' . $data_expand['expand'] . '';
            //\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . "Sending Request to priority - start at line " . number_format( $index ) . " | batch of " . number_format( $step ) . " products" );
            $response = WooAPI::instance()->makeRequest( 'GET', $request, [], true );
            //\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . 'Request finished' );
            
            if ( $response['status'] ) {
                $products      = [];
                $response_data = json_decode( $response['body_raw'], true )['value'];
                //\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . count( $response_data['value'] ) . ' Products received, starting sync' );
                
                foreach ( $response_data as $item ) {
                    $sku = trim( $item['PARTNAME'] ?? '' );
                    if ( ! $sku ) {
                        continue;
                    }
                    // set visibility hidden and continue to next item if showinweb = N
                    if ( $item[ $show_in_web ] != 'Y' ) {
                        $pdt_id = wc_get_product_id_by_sku($sku);
                        if($pdt_id == 0)
                            continue;
                        else{
                            $existing_product = wc_get_product($pdt_id);
                            //$_product->set_status( 'draft' );
                            $existing_product->set_catalog_visibility( 'hidden' );
                            $existing_product->save();
                            continue;
                        }
                    
                    }
                    $content = [];
                    if ( ! empty( $item['PARTTEXT_SUBFORM'] ) ) {
                        foreach ( $item['PARTTEXT_SUBFORM'] as $text ) {
                            $clean_text = preg_replace( '/<style>.*?<\/style>/s', '', $text );
                            $content[]  = html_entity_decode( $clean_text );
                        }
                    }
                    
                    $product = [
                        'post_type'    => 'product',
                        'post_title'   => trim( $item['PARTDES'] ?? '' ),
                        'post_excerpt' => $item['REUT_SUBTITLE'] ?? '',
                        'post_content' => ! empty( $content ) ? implode( ' ', $content ) : '',
                        'post_status'  => 'publish',
                        'tax_input'    => [
                            'product_cat' => [],
                        ],
                        'meta_input'   => [
                            '_sku'                   => $sku,
                            'isbn_product_field'     => $item['REUT_ISBN'],
                            'danacode_product_field' => $item['REUT_DANACODE'],
                            '_price'                 => 25.00,
                            //'_stock_status' => 'instock',
                        
                        ],
                        'attributes'   => [
                            'הוצאה-לאור'        => $item['SPEC1'],
                            'שנת-הוצאה'         => $item['SPEC5'],
                            'book-author'       => array_map( function ( $attribute ) {
                                return $attribute['NAME_AUTH'];
                            }, $item['REUT_AUTHORS_SUBFORM'] ),
                            'product-tag-badge' => array_map( function ( $attribute ) {
                                return $attribute['REUT_BOOKLABEL'];
                            }, $item['REUT_LABELSFORBOOK_SUBFORM'] ),
                        ],
                        //'image'        => ($is_load_image == true) ? $item['EXTFILENAME'] : ''
                        //'image' =>  ($is_load_image == true) ? "https://www.w3schools.com/tags/img_girl.jpg" : ""
                    ];
                    if ( isset( $item['ECPARTCATEGORIES_SUBFORM'] ) ) {
                        foreach ( $item['ECPARTCATEGORIES_SUBFORM'] as $cats ) {
                            foreach ( $cats as $v ) {
                                if ( ! empty( $v ) ) {
                                    $product['tax_input']['product_cat'][] = $v;
                                }
                            }
                        }
                    }
                    
                    $products[] = $product;
                }
                
                $stats    = [];
                //$progress = make_progress_bar( 'Syncing products', count( $products ) );
                if ( ! empty( $products ) ) {
                    foreach ( $products as $product ) {
                        $attributes = $product['attributes'];
                        unset( $product['attributes'] );
                        
                        if ( ! $id = wc_get_product_id_by_sku( $product['meta_input']['_sku'] ) ) {
                            $stats['new'] ++;
                            $stats_total['new'] ++;
                            $post_id = wp_insert_post( $product ); //3852
                            if ( $post_id && ! is_wp_error( $post_id ) ) {
                                set_product_data( $product, $post_id, $attributes );
                            }
                        } else {
                            $stats['updated'] ++;
                            $stats_total['updated'] ++;
                            $product['ID'] = $id;
                            $post_id       = wp_update_post( $product );
                            if ( ! is_wp_error( $post_id ) ) {
                                set_product_data( $product, $post_id, $attributes );
                            }
                        }
                        //$progress->tick( 1, 'sku: ' . $product['meta_input']['_sku'] );
                    }
                }
                //$progress->finish();
                //\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . 'Batch Completed' );
                //\WP_CLI::log( 'New products: ' . $stats['new'] ?? '0' );
                //\WP_CLI::log( 'Updated products: ' . $stats['updated'] ?? '0' );
                
                delete_option( "product_cat_children" ); // clear wordpress taxonomy cache
                
                $index          += $step;
                $total_products += $step;
                //break;
            } else {
                //\WP_CLI::error( 'Failed request at skip=' . ( $index ) . ' with error: ' . print_r( $response, 1 ) ); //$response['body'] );
                break;
            }
        }
        

        import_finish();
        $check2 = date('Y-m-d H:i:s');
        
        // \WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . 'Sync Complete =)' );
        // \WP_CLI::log( $total_products . ' Products in Total.' );
        // \WP_CLI::log( 'New products: ' . $stats_total['new'] ?? '0' );
        // \WP_CLI::log( 'Updated products: ' . $stats_total['updated'] ?? '0' );
        // \WP_CLI::success( 'Finished.' );
}

function import_start() {
//		add_filter( 'terms_clauses', function ( $clauses ) {
//			$clauses['fields'] = 'ids'; // Avoid fetching full term data for each insert
//
//			return $clauses;
//		} );
    wp_suspend_cache_invalidation( true );
    wp_defer_term_counting( true );
    wp_defer_comment_counting( true );
}
        
function import_finish() {
    wp_suspend_cache_invalidation( false );
    wp_cache_flush();
    wp_defer_term_counting( false );
    wp_defer_comment_counting( false );
    delete_option( "product_cat_children" );
    //wp_update_term_count_now(get_terms(['taxonomy' => 'product_cat', 'fields' => 'ids']), 'product_cat'); // Updates counts.
    wc_update_product_lookup_tables();
}

function recursive_add_categories( $categories ) {
    $category_ids = [];
    $parent_id    = 0;
    
    foreach ( $categories as $category ) {
        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'name'       => $category,
            'parent'     => $parent_id,
            'hide_empty' => false,
        ] );
        //if ( ! $term = term_exists( $category, 'product_cat', (int) $parent_id ) ) {
        if ( empty( $terms ) ) {
            
            $term = wp_insert_term( $category, 'product_cat', [ 'parent' => $parent_id ] );
            delete_option( "product_cat_children" ); // WTF?!?!?~~~ without this child cat duplicates
        } else {
            $term = [ 'term_id' => $terms[0]->term_id ];
        }
        if ( ! is_wp_error( $term ) ) {
            $category_ids[] = $parent_id = (int) $term['term_id'];
        }
    }
    
    return $category_ids;
}

function upload_image( $url, $title = null ) {
    require_once( ABSPATH . "/wp-load.php" );
    require_once( ABSPATH . "/wp-admin/includes/image.php" );
    require_once( ABSPATH . "/wp-admin/includes/file.php" );
    require_once( ABSPATH . "/wp-admin/includes/media.php" );
    
    // Download url to a temp file
    $tmp = download_url( $url );
    if ( is_wp_error( $tmp ) ) {
        return false;
    }
    
    // Get the filename and extension ("photo.png" => "photo", "png")
    $filename  = pathinfo( $url, PATHINFO_FILENAME );
    $extension = pathinfo( $url, PATHINFO_EXTENSION );
    
    // An extension is required or else WordPress will reject the upload
    if ( ! $extension ) {
        // Look up mime type, example: "/photo.png" -> "image/png"
        $mime = mime_content_type( $tmp );
        $mime = is_string( $mime ) ? sanitize_mime_type( $mime ) : false;
        
        // Only allow certain mime types because mime types do not always end in a valid extension (see the .doc example below)
        $mime_extensions = [
            'text/plain'         => 'txt',
            'text/csv'           => 'csv',
            'application/msword' => 'doc',
            'image/jpg'          => 'jpg',
            'image/jpeg'         => 'jpeg',
            'image/gif'          => 'gif',
            'image/png'          => 'png',
            'video/mp4'          => 'mp4',
        ];
        
        if ( isset( $mime_extensions[ $mime ] ) ) {
            // Use the mapped extension
            $extension = $mime_extensions[ $mime ];
        } else {
            // Could not identify extension
            @unlink( $tmp );
            
            return false;
        }
    }
    
    
    // Upload by "sideloading": "the same way as an uploaded file is handled by media_handle_upload"
    $args = [
        'name'     => "$filename.$extension",
        'tmp_name' => $tmp,
    ];
    
    // Do the upload
    $attachment_id = media_handle_sideload( $args, 0, $title );
    
    // Cleanup temp file
    @unlink( $tmp );
    
    // Error uploading
    if ( is_wp_error( $attachment_id ) ) {
        return false;
    }
    
    // Success, return attachment ID (int)
    return (int) $attachment_id;
}
function set_product_data( $product, $product_id, $attributes = [] ) {
    $wc_product = wc_get_product( $product_id );
    
    // attributes
    if ( ! empty( $attributes ) ) {
        $product_attributes_data = [];
        foreach ( $attributes as $k => $v ) {
            $taxonomy = wc_attribute_taxonomy_name( $k );
            if ( is_array( $v ) ) {
                foreach ( $v as $vv ) {
                    $brand_term = get_term_by( 'name', $vv, $taxonomy ) ?: wp_insert_term( $vv, $taxonomy );
                    wp_set_post_terms( $product_id, $vv, $taxonomy, true );
                    $product_attributes_data[ $taxonomy ] = [
                        'name'         => $taxonomy,
                        'value'        => $vv,
                        'is_visible'   => '1',
                        'is_variation' => '0',
                        'is_taxonomy'  => '1'
                    ];
                }
            } else {
                $brand_term = get_term_by( 'name', $v, $taxonomy ) ?: wp_insert_term( $v, $taxonomy );
                wp_set_post_terms( $product_id, $v, $taxonomy, true );
                $product_attributes_data[ $taxonomy ] = [
                    'name'         => $taxonomy,
                    'value'        => $v,
                    'is_visible'   => '1',
                    'is_variation' => '0',
                    'is_taxonomy'  => '1'
                ];
            }
        }
        $wc_product->update_meta_data( '_product_attributes', $product_attributes_data );
    }
    
    // categories
    $category_ids = recursive_add_categories( $product['tax_input']['product_cat'] );
    $wc_product->set_category_ids( $category_ids );
    
    // price
    $wc_product->set_regular_price( $product['meta_input']['_price'] );

    //update from hidden to visible
    if ( $wc_product->get_catalog_visibility() === 'hidden' ) {
        // Set visibility to 'visible' (Shop and Search)
        $wc_product->set_catalog_visibility( 'visible' );
    }
    
    //image
    if ( WooAPI::instance()->option( 'update_image' ) == true || ! get_the_post_thumbnail_url( $product_id ) ) {
        //product image not from priority but phototag
        // if ( $product['image'] ) {
        //     if ( $attachment_id = upload_image( $product['image'] ) ) {
        //         $wc_product->set_image_id( $attachment_id );
        //     }
        // }
        $sku = $wc_product->get_sku();
        //check if the image already in media library - added in phototag sync but not attached because product was not yet created
        $attachment = get_page_by_title( $sku, OBJECT, 'attachment' );
        if ( $attachment ) {
            $attachment_id = $attachment->ID;
            update_post_meta($product_id, '_thumbnail_id', $attachment_id);
            set_post_thumbnail($product_id, $attachment_id);
        }
    }
    
    
// save product
    $wc_product->save();
}

add_action('syncInventoryPriority_2704_hook', 'syncInventoryPriority2704');

if (!wp_next_scheduled('syncInventoryPriority_2704_hook')) {

   //$res = wp_schedule_event(time(), 'every_five_minutes', 'syncInventoryPriority_hook');
   wp_schedule_event(time(), 'daily', 'syncInventoryPriority_2704_hook');

}

function syncInventoryPriority2704() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . 'Sync Started' );
		\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . 'Downloading data from priority' );
	}
	
	$index = 0;
	$step = 2000;
	
	$response_data = [ 0 ];
	
	$stamp = strtotime('2025-04-27');
    $bod = date(DATE_ATOM, $stamp);

    //$query        = "WARHSTRANSDATE eq '2025-04-27T00:00:00Z' or PURTRANSDATE eq '2025-04-27T00:00:00Z' or SALETRANSDATE eq '2025-04-27T00:00:00Z' or UDATE ge '2025-04-27T00:00:00Z' ";
	$query = "WARHSTRANSDATE eq $bod or PURTRANSDATE eq $bod or SALETRANSDATE eq $bod or UDATE eq $bod";
    $url_addition = '(' . rawurlencode( $query ) . ')';
	
	$data['select'] = 'PARTNAME';
	
	
	$expand = '$expand=YARD_WARHSBAL_SUBFORM($select=WARHSNAME,TQUANT,WEBBALANCE)';
	
	file_put_contents( dirname( __FILE__ ) . '/$sync-stock-log.txt', date_i18n( 'H:i:s' ) . "\r\n" );
	
	$data['expand'] = $expand;
	while ( sizeof( $response_data ) > 0 ) {
		//$response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter='.$url_addition.' and INVFLAG eq \'Y\' &' . $data['expand'], [], WooAPI::instance()->option('log_inventory_priority', true));
		$response = WooAPI::instance()->makeRequest( 'GET', 'LOGPART?$select=' . $data['select'] . '&$filter=' . $url_addition . ' and INVFLAG eq \'Y\' &$skip=' . $index . '&$top=' . $step . '&' . $data['expand'], [], true );
		// check response status
        echo 'custom sync inventory 27/04/25';
        echo "<pre>";
        print_r($response);
        echo "</pre>";
        die();
		if ( $response['status'] ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$time = time();
				\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . "Index: $index | Step: $step" );
			}
			$response_data = json_decode( $response['body_raw'], true )['value'];
			foreach ( $response_data as $item ) {
				// if product exsits, update
				$field = 'PARTNAME';
				$product_id = wc_get_product_id_by_sku( $item[ $field ] );
				
				//if ($id = wc_get_product_id_by_sku($item['PARTNAME'])) {
				if ( ! $product_id == 0 ) {
					// update_post_meta($product_id, '_sku', $item['PARTNAME']);
					// get the stock by part availability
					
					// get the stock by specific warehouse
					
					foreach ( $item['YARD_WARHSBAL_SUBFORM'] as $wh_stock ) {
						$store = $wh_stock['WARHSNAME'];
						if ( function_exists( 'simplyct_set_stock' ) ) {
							simplyct_set_stock( $item[ $field ], $store, $wh_stock['WEBBALANCE'] );
						}
					}
					simplyct_set_stock_availability( $product_id );
				}

	         if ( defined( 'WP_CLI' ) && WP_CLI ) {
		         \WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . " Product Stock Updated - " . get_the_title($product_id) . " ($product_id)" );
		         file_put_contents( dirname( __FILE__ ) . '/$sync-stock-log.txt', print_r(  date_i18n( 'H:i:s' ) . ' - ' . " Product Stock Updated - " . get_the_title($product_id) . " ($product_id)", true ), FILE_APPEND );
	         }
			}
			
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$took = date_i18n( 'H:i:s', time() - $time );
				\WP_CLI::log( 'Batch process time: ' . $took );
			}
			
			$index += $step;
			// add timestamp
			//WooAPI::instance()->updateOption('inventory_priority_update', time());
		} else {
			/**
			 * t149
			 */
			WooAPI::instance()->sendEmailError(
				WooAPI::instance()->option( 'email_error_sync_inventory_priority' ),
				'Error Sync Inventory Priority',
				$response['body']
			);
			break;
		}
	}
}

add_action('syncInventoryPriority_hook', 'syncInventoryPriority');

if (!wp_next_scheduled('syncInventoryPriority_hook')) {

   //$res = wp_schedule_event(time(), 'every_five_minutes', 'syncInventoryPriority_hook');
   wp_schedule_event(time(), 'ten_minutes', 'syncInventoryPriority_hook');

}

/**
 * sync inventory from priority
 */

 function syncInventoryPriority_old()
 {
     $index = 0;
     $step  = 100;
  
     $response_data       = [ 0 ];
      // get the items simply by time stamp of today
      $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
      //$daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
      $daysback = 60;
     $stamp = mktime(1 - ($daysback * 24), 0, 0);
      $bod = date(DATE_ATOM, $stamp);
      $url_addition = '('. rawurlencode('WARHSTRANSDATE ge ' . $bod . ' or PURTRANSDATE ge ' . $bod . ' or SALETRANSDATE ge ' . $bod) . ')';
  
      
      $data['select'] = 'PARTNAME';
  
  
      
      $expand = '$expand=PARTBALANCE_SUBFORM($select=WARHSNAME,TBALANCE)';
      
      
      $data['expand'] = $expand;
      while ( sizeof( $response_data ) > 0 ) {
         //     $response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter='.$url_addition.' and INVFLAG eq \'Y\' &' . $data['expand'], [], WooAPI::instance()->option('log_inventory_priority', true));
      $response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter='.$url_addition.' and INVFLAG eq \'Y\' &$skip=' . $index . '&$top=' . $step . '&' . $data['expand'], [], true);
      // check response status
      echo 'custom sync inventory';
      echo "<pre>";
      print_r($response);
      echo "</pre>";
      die();
      if ($response['status']) {
          $response_data = json_decode($response['body_raw'], true)['value'];
          foreach ($response_data as $item) {
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
          $index          += $step;
          //break;
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
          break;
      }
     }
 }


if ( ! wp_next_scheduled( 'syncInventoryPriority_hook' ) ) {
//	$res = wp_schedule_event( time(), 'daily', 'syncInventoryPriority_hook' );
	$res = wp_schedule_event( time(), 'priority_custom_schedule', 'syncInventoryPriority_hook' );
}

add_filter( 'cron_schedules', 'custom_cron_schedules' );
function custom_cron_schedules( $schedules ) {
	$minutes = stov_get_minutes();
	$schedules['priority_custom_schedule'] = [
		'interval' => $minutes * 60,
		'display'  => sprintf( __( 'Every %d Minutes' ), $minutes ),
	];
	
	return $schedules;
}

add_action( 'syncInventoryPriority_hook', 'syncInventoryPriority' );
function syncInventoryPriority() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . 'Sync Started' );
		\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . 'Downloading data from priority' );
	}
	
	$index = 0;
	$step = 2000;
	
	$response_data = [ 0 ];
	// get the items simply by time stamp of today
	// $daysback_options = explode(',', WooAPI::instance()->option('sync_inventory_warhsname'))[3];
	//$daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
	
	// custom code
	//	$daysback = 100;
	//	 $stamp = mktime(1 - ($daysback * 24), 0, 0);
	
	// sync inventory 30 minutes back
	date_default_timezone_set( 'Asia/Jerusalem' );
	
	// 37 minutes - cron runs every 30 minutes + 5 minutes server cron + 2 minutes safty
	$server_cron = 5;
	$minutes = stov_get_minutes() + $server_cron + 2;
	$stamp = time() - ( $minutes * 60 );
	
	//$step = 2000;
	//$stamp = time() - ( 48 * 60 * 60 );
	
	$bod          = date( DATE_ATOM, $stamp );
	//$query        = "WARHSTRANSDATE ge $bod or PURTRANSDATE ge $bod or SALETRANSDATE ge $bod or UDATE ge $bod";
	//update 06/05/25 new reut field:
	$query        = "REUT_INVENTORYDATE ge $bod";
	$url_addition = '(' . rawurlencode( $query ) . ')';
	
	$data['select'] = 'PARTNAME';
	
	
	$expand = '$expand=YARD_WARHSBAL_SUBFORM($select=WARHSNAME,TQUANT,WEBBALANCE)';
	
	//file_put_contents( dirname( __FILE__ ) . '/$sync-stock-log.txt', date_i18n( 'H:i:s' ) . "\r\n" );
	
	$data['expand'] = $expand;
	while ( sizeof( $response_data ) > 0 ) {
		//     $response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter='.$url_addition.' and INVFLAG eq \'Y\' &' . $data['expand'], [], WooAPI::instance()->option('log_inventory_priority', true));
		$response = WooAPI::instance()->makeRequest( 'GET', 'LOGPART?$select=' . $data['select'] . '&$filter=' . $url_addition . ' and INVFLAG eq \'Y\' &$skip=' . $index . '&$top=' . $step . '&' . $data['expand'], [], true );
		// check response status
	//      echo 'custom sync inventory';
	//      echo "<pre>";
	//      print_r($response);
	//      echo "</pre>";
	//      die();
		if ( $response['status'] ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$time = time();
				\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . "Index: $index | Step: $step" );
			}
			$response_data = json_decode( $response['body_raw'], true )['value'];
			foreach ( $response_data as $item ) {
				// if product exsits, update
				$field = 'PARTNAME';
	//             $args = array(
	//                 'post_type' => array('product', 'product_variation'),
	//                 'meta_query' => array(
	//                     array(
	//                         'key' => '_sku',
	//                         'value' => $item[$field]
	//                     )
	//                 )
	//             );
	//             $my_query = new \WP_Query($args);
	//             if ($my_query->have_posts()) {
	//                 while ($my_query->have_posts()) {
	//                     $my_query->the_post();
	//                     $product_id = get_the_ID();
	//                 }
	//             } else {
	//                 $product_id = 0;
	//             }
	//				if ($item[ $field ] != 'NRB-006231') {
	//					continue;
	//				}
				$product_id = wc_get_product_id_by_sku( $item[ $field ] );
				
				//if ($id = wc_get_product_id_by_sku($item['PARTNAME'])) {
				if ( ! $product_id == 0 ) {
					// update_post_meta($product_id, '_sku', $item['PARTNAME']);
					// get the stock by part availability
					
					// get the stock by specific warehouse
					
					$total_stock = 0;
					foreach ( $item['YARD_WARHSBAL_SUBFORM'] as $wh_stock ) {
						$store = $wh_stock['WARHSNAME'];
						if ( function_exists( 'simplyct_set_stock' ) ) {
							$total_stock += max( (int) $wh_stock['WEBBALANCE'], 0 );
							simplyct_set_stock( $item[ $field ], $store, $wh_stock['WEBBALANCE'] );
						}
					}
					$product = wc_get_product( $product_id );
					$product->set_manage_stock( true );
					$product->set_stock_status(  $total_stock > 0 ? 'instock' : 'outofstock' );
					$product->set_stock_quantity( $total_stock );
					$product->save();
				}
				
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . " Product Stock Updated - " . get_the_title($product_id) . " ($product_id)" );
					file_put_contents( dirname( __FILE__ ) . '/$sync-stock-log.txt', print_r(  date_i18n( 'H:i:s' ) . ' - ' . " Product Stock Updated - " . get_the_title($product_id) . " ($product_id)", true ), FILE_APPEND );
				}
			}
			
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$took = date_i18n( 'H:i:s', time() - $time );
				\WP_CLI::log( 'Batch process time: ' . $took );
			}
			
			$index += $step;
			// add timestamp
			//WooAPI::instance()->updateOption('inventory_priority_update', time());
		} else {
			/**
			 * t149
			 */
			WooAPI::instance()->sendEmailError(
				WooAPI::instance()->option( 'email_error_sync_inventory_priority' ),
				'Error Sync Inventory Priority',
				$response['body']
			);
			break;
		}
	}
}

add_action( 'syncInventoryPriority_april_hook', 'syncInventoryPriorityApril' );
function syncInventoryPriorityApril() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . 'Sync Started' );
		\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . 'Downloading data from priority' );
	}
	
	$index = 0;
	$step = 2000;
	
	$response_data = [ 0 ];

	$daysback = 50;
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = '('. rawurlencode('WARHSTRANSDATE ge ' . $bod . ' or PURTRANSDATE ge ' . $bod . ' or SALETRANSDATE ge ' . $bod) . ')';

	$data['select'] = 'PARTNAME';
	$expand = '$expand=YARD_WARHSBAL_SUBFORM($select=WARHSNAME,TQUANT,WEBBALANCE)';
	
	//file_put_contents( dirname( __FILE__ ) . '/$sync-stock-log.txt', date_i18n( 'H:i:s' ) . "\r\n" );
	
	$data['expand'] = $expand;
	while ( sizeof( $response_data ) > 0 ) {
		$response = WooAPI::instance()->makeRequest( 'GET', 'LOGPART?$select=' . $data['select'] . '&$filter=' . $url_addition . ' and INVFLAG eq \'Y\' &$skip=' . $index . '&$top=' . $step . '&' . $data['expand'], [], true );
		// check response status
        // echo 'custom sync inventory';
        // echo "<pre>";
        // print_r($response);
        // echo "</pre>";
        // die();
		if ( $response['status'] ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$time = time();
				\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . "Index: $index | Step: $step" );
			}
			$response_data = json_decode( $response['body_raw'], true )['value'];
			foreach ( $response_data as $item ) {
				// if product exsits, update
				$field = 'PARTNAME';

				$product_id = wc_get_product_id_by_sku( $item[ $field ] );
				
				//if ($id = wc_get_product_id_by_sku($item['PARTNAME'])) {
				if ( ! $product_id == 0 ) {
					// update_post_meta($product_id, '_sku', $item['PARTNAME']);
					// get the stock by part availability
					
					// get the stock by specific warehouse
					
					$total_stock = 0;
					foreach ( $item['YARD_WARHSBAL_SUBFORM'] as $wh_stock ) {
						$store = $wh_stock['WARHSNAME'];
						if ( function_exists( 'simplyct_set_stock' ) ) {
							$total_stock += max( (int) $wh_stock['WEBBALANCE'], 0 );
							simplyct_set_stock( $item[ $field ], $store, $wh_stock['WEBBALANCE'] );
						}
					}
					$product = wc_get_product( $product_id );
					$product->set_manage_stock( true );
					$product->set_stock_status(  $total_stock > 0 ? 'instock' : 'outofstock' );
					$product->set_stock_quantity( $total_stock );
					$product->save();
				}
				
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					\WP_CLI::log( date_i18n( 'H:i:s' ) . ' - ' . " Product Stock Updated - " . get_the_title($product_id) . " ($product_id)" );
					file_put_contents( dirname( __FILE__ ) . '/$sync-stock-log.txt', print_r(  date_i18n( 'H:i:s' ) . ' - ' . " Product Stock Updated - " . get_the_title($product_id) . " ($product_id)", true ), FILE_APPEND );
				}
			}
			
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$took = date_i18n( 'H:i:s', time() - $time );
				\WP_CLI::log( 'Batch process time: ' . $took );
			}
			
			$index += $step;
			// add timestamp
			//WooAPI::instance()->updateOption('inventory_priority_update', time());
		} else {
			/**
			 * t149
			 */
			WooAPI::instance()->sendEmailError(
				WooAPI::instance()->option( 'email_error_sync_inventory_priority' ),
				'Error Sync Inventory Priority',
				$response['body']
			);
			break;
		}
	}
}



add_filter('simply_request_data', 'simply_func');
function simply_func($data){

	$order_id = $data['orderId'];

    $order = wc_get_order($order_id);

    $branch_id = get_post_meta($order_id, 'pickup_branch', true);
    $branch = get_term($branch_id);
    $branch_code = $branch->slug;

    $pickup_branch = !empty(get_post_meta($order_id, 'store_switcher', true)) ? get_post_meta($order_id, 'store_switcher', true) : $branch_code;
    $shipping_methods = $order->get_shipping_methods();
	foreach ($shipping_methods as $shipping_method) {
        if ($shipping_method->get_method_id() === 'local_pickup') {
            $data['WARHSNAME'] = $pickup_branch;
            $shipping_code = "1";
        }
        if ($shipping_method->get_method_id() === 'woo-baldarp-pickup') {
            $shipping_code = "2";
            $data['REUT_PICKUPPOINT'] = $order->get_meta('cargo_DistributionPointID');
        }
        if ($shipping_method->get_method_id() === 'flat_rate') {
            $shipping_code = "3";
        }
        $data['STCODE'] = $shipping_code;
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

    $items = [];
	foreach($data['ORDERITEMS_SUBFORM'] as $item ){
		//if coupon
		if($item['PARTNAME'] == '000' ){
			$vatprice = $item['VATPRICE'];
			unset($item['VATPRICE']);
			$item['VPRICE'] =  $vatprice;
		}
		
        $items[] = $item;
    }
	$data['ORDERITEMS_SUBFORM'] = $items;
   
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
        $fname = $field_values['first_name'];
        $lname = $field_values['last_name'];
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


function makeRequestPhototag($log = true)
{
    $raw_option          = str_replace( [
        "\n",
        "\t",
        "\r"
    ], '', WooAPI::instance()->option( 'sync_items_priority_config' ) );
    $config              = json_decode( stripslashes( $raw_option ) );
    $daysback = (!empty((int)$config->images_days_back) ? $config->images_days_back : 3);
    $datedaysback = date('Y-m-d', strtotime('-'.$daysback.' day'));
    
    $dateOneHourAgo = gmdate('Y-m-d\TH:i:s\Z', strtotime('-1 hour')); //2024-12-22T10:19:18Z

    $token            = $config->token;
    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'timeout'   => 45,
        'method'    => "GET",
        'sslverify' => 1
    ];

    $url = "https://rebook.phototag.app/api/images?after=".$datedaysback."";

    $response = wp_remote_request($url, $args);
    //$response = $this->makeRequestWithRetry($url, $args);

    $response_code    = wp_remote_retrieve_response_code($response);
    $response_message = wp_remote_retrieve_response_message($response);
    $response_body    = wp_remote_retrieve_body($response);

    if ($response_code >= 400) {
        $response_body = strip_tags($response_body);
    }

    // decode hebrew
    //$response_body_decoded = $this->decodeHebrew($response_body);

    // log request
    if ($log) {
        $GLOBALS['wpdb']->insert($GLOBALS['wpdb']->prefix . 'p18a_logs', [
            'blog_id'        => get_current_blog_id(),
            'timestamp'      => current_time('mysql'),
            'url'            => $url,
            'request_method' => "GET",
            'json_request'   => '',
            'json_response'  => ($response_body ? $response_body : $response_message.' '.$response_code),
            'json_status'    => ($response_code >= 200 && $response_code < 300) ? 1 : 0
        ]);
    }

    return [ 
        'url'      => $url,
        'args'     => $args,
        'method'   => strtoupper($method),
        'body'     => $response_body_decoded,
        'body_raw' => $response_body,
        'code'     => $response_code,
        'status'   => ($response_code >= 200 && $response_code < 300) ? 1 : 0,
        'message'  => ($response_message ? $response_message : $response->get_error_message())
    ];
    
}

function media_exists_by_meta($sku, $created_day) {
    $args = [
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'inherit',
        'meta_query'     => [
            [
                'key'   => '_uploaded_image_sku',
                'value' => $sku
            ],
        ]
    ];

    $attachments = get_posts($args);

    foreach ($attachments as $attachment) {
        $stored_created = get_post_meta($attachment->ID, '_uploaded_image_created', true);
        if ($stored_created === $created_day) {
            return $attachment;
        }
    }

    return false;
}

add_action('syncImagesPhototag_cron_hook', 'syncImagesPhototag');

if (!wp_next_scheduled('syncImagesPhototag_cron_hook')) {

   $res = wp_schedule_event(time(), 'hourly', 'syncImagesPhototag_cron_hook');

}

function syncImagesPhototag(){
    $raw_option          = str_replace( [
        "\n",
        "\t",
        "\r"
    ], '', WooAPI::instance()->option( 'sync_items_priority_config' ) );
    $config              = json_decode( stripslashes( $raw_option ) );
    $token            = $config->token;
    $response = makeRequestPhototag(true);
    $response_data = json_decode($response['body_raw'], true);
    //$filtered_items = [];
    //$one_hour_ago = strtotime('-1 hour'); 
    foreach ($response_data as $item) {
        // $strtotime_time_created = strtotime($item['timeCreated']);
        // if ($strtotime_time_created >= $one_hour_ago) {
        //     $filtered_items[] = $item; // Add the item to the filtered array
        // }
        $item_id = $item['id'];
        $tag = $item['tags'][0];
        //$tag = "071700250545";
        $time_created = $item['timeCreated'];
        $time_updated = $item['timeUpdated'];
        $url_addition = 'SERNUMBERS?$select=SERNUM,PARTNAME&$filter=SERNUM eq \''.$tag.'\'';
        $priority_response = WooAPI::instance()->makeRequest('GET', $url_addition, null, true);
        if($priority_response['code'] == 200){
            $body_array = json_decode($priority_response['body'],true);
            if(!empty($body_array['value'])){
                $sku = $body_array['value'][0]['PARTNAME'];
                $image_url = "https://rebook.phototag.app/api/images/".$item_id."?token=".$token."";

                $created_day = date('Y-m-d H', strtotime($time_created));
                $updated_day = date('Y-m-d H', strtotime($time_updated));
                // Check if the image already exists in the media library
                // $existing_image = get_posts(array(
                //     'post_type'   => 'attachment',
                //     'name'        => sanitize_title($sku), // $image_name is the name of the file without extension
                //     'numberposts' => 1
                // ));
                $existing_image = media_exists_by_meta($sku, $created_day);
				write_custom_log( 'check if need update or upload new image for sku: ' . $sku );
                // Insert image to media only if the image does not exist in media or its updated image
                if (!$existing_image || $created_day !== $updated_day) {
                    write_custom_log('update image for sku: '.$sku);
                    $attachment_id = add_image_to_media_library( $image_url, $sku, $created_day );
                    write_custom_log('atachement id created: '.$attachment_id);
                    if (!is_wp_error($attachment_id)) {
                        $product_id = wc_get_product_id_by_sku($sku); 
                        if($product_id){
                            //delete_post_meta($product_id, '_thumbnail_id');
                            // Set the new image as the featured image
                            update_post_meta($product_id, '_thumbnail_id', $attachment_id);
                            set_post_thumbnail($product_id, $attachment_id);
                        }
                        else{
                            write_custom_log('product id: '.$product_id.' not exist, iamge only added to media!');
                        }
                    } else {
                        $attachment_id = $attachment_id->get_error_message();
                        write_custom_log('error adding image in library: '.$attachment_id);
                    }
                }
                //check if sku has already image, if yes  skip it
                //$product_id = wc_get_product_id_by_sku($sku); 
                // if($product_id){
                //     $current_thumbnail_id = get_post_thumbnail_id($product_id); 
                //     $created_day = date('Y-m-d H', strtotime($time_created));
                //     $updated_day = date('Y-m-d H', strtotime($time_updated));
                   
                //     //$image_url = "https://rebook.phototag.app/api/images/".$item_id."?token=".$token."";

                  
                //     //$success = add_image_to_media_library($image_url, $sku);
                //     // if (!is_wp_error($attachment_id)) {
                //     //     // Set the new image as the featured image
                //     //     set_post_thumbnail($product_id, $attachment_id);
                //     //     //return "Product image updated successfully!";
                //     // } else {
                //     //     $attachment_id = $attachment_id->get_error_message();
                //     // }
                //     // If days are different, update the product image
                //     if (($current_thumbnail_id && $created_day !== $updated_day) || $current_thumbnail_id == 0) {
                       
                //         $attachment_id = add_image_to_media_library($image_url, $sku);

                //         if (!is_wp_error($attachment_id)) {
                //             // Set the new image as the featured image
                //             set_post_thumbnail($product_id, $attachment_id);
                //             //return "Product image updated successfully!";
                //         } else {
                //             $attachment_id = $attachment_id->get_error_message();
                //         }
                //     }
                // }
                // else{
                //     //if product not exist, add image to media without attach to product
                //     $attachment_id = add_image_to_media_library($image_url, $sku);
                // }
            }
            else{
                write_custom_log('not find priority sku for serial number: '.$tag);
            }
            
        }
    }
    //wp_cache_flush();
}







// add_action('init', function(){
//     // $order_id = 445542;
//     // $order = wc_get_order($order_id);
//     // //$order->get_meta('cargo_DistributionPointID');
//     // if (isset($_GET['debug_check'])) {
//     //  $branch_id = get_post_meta($order_id, 'pickup_branch', true);
// 	//     $branch = get_term($branch_id);
//     //  $branch_code = $branch->slug;
//     // }
// });

function add_image_to_media_library($image_url, $product_sku, $created_day) {
    // // Check if the image already exists in the media library
    // $existing_image = get_posts(array(
    //     'post_type'   => 'attachment',
    //     'meta_key'    => '_sku', // Custom meta key to store SKU
    //     'meta_value'  => $product_sku,
    //     'numberposts' => 1
    // ));

    // // If the image already exists, return its ID
    // if (!empty($existing_image)) {
    //     return $existing_image[0]->ID;
    // }

    // Ensure WordPress functions are available
    if (!function_exists('wp_insert_attachment')) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }
    if (!function_exists('download_url')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    // Download the image from the URL
    $temp_file = download_url($image_url);

    // Check for download errors
    if (is_wp_error($temp_file)) {
        return $temp_file; // Return error if any
    }

    // Set the file name using the product SKU and preserve the extension
    $file_extension = pathinfo($image_url, PATHINFO_EXTENSION);
    if (empty($file_extension)) {
        $file_extension = 'jpeg'; // Default to 'jpeg' if no extension is found
    }

    $file_name = sanitize_file_name($product_sku . '.' . $file_extension); //ORB-5531.jpeg

    // add_filter('upload_dir', $custom_upload_dir_no_subdirs = function($dirs) {
    //     $dirs['subdir'] = '';
    //     $dirs['path'] = $dirs['basedir'];
    //     $dirs['url'] = $dirs['baseurl'];
    //     return $dirs;
    // });
    
    $upload_dir = wp_upload_dir();
    
    //remove_filter('upload_dir', $custom_upload_dir_no_subdirs);
    // Get the upload directory
    //$upload_dir = wp_upload_dir();
    //$file_path = $upload_dir['path'] . '/' . $file_name; //C:\Users\Elisheva\Desktop\wamp64\www\woocommerce/wp-content/uploads/2024/12/ORB-5531.jpeg
    $file_path = $upload_dir['basedir'] . '/sipur/' . $file_name;
    // Move the downloaded file to the upload directory
    $move_file = @rename($temp_file, $file_path);

    if (!$move_file) {
        @unlink($temp_file); // Clean up temporary file
        return new WP_Error('file_move_error', 'Could not move the downloaded file to the uploads directory.');
    }

    // Check the file type
    $file_type = wp_check_filetype($file_name, null);

    if (!$file_type['type']) {
        $file_type = array(
            'ext'  => $file_extension,
            'type' => 'image/jpeg' // Default MIME type
        );
    }

    // Prepare attachment data
    $attachment = array(
        'guid'           => $upload_dir['url'] . '/' . $file_name,
        'post_mime_type' => $file_type['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', $file_name),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    // Insert the attachment into the media library
    $attachment_id = wp_insert_attachment($attachment, $file_path); //3910
    // Save metadata to avoid duplicate re-uploads
	update_post_meta( $attachment_id, '_uploaded_image_sku', $product_sku );
	update_post_meta( $attachment_id, '_uploaded_image_created', $created_day );
	write_custom_log("Saved meta: SKU = $product_sku, created = $created_day");

    if (is_wp_error($attachment_id)) {
        return $attachment_id; // Return error if insertion fails
    }

    // Generate attachment metadata
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
    wp_update_attachment_metadata($attachment_id, $attach_data);

    // Save the SKU as a custom field for future reference
    //update_post_meta($attachment_id, '_sku', $product_sku);

    // Set the product's featured image
    // $product_id = wc_get_product_id_by_sku($product_sku);
    // if ($product_id) {
    //     // Set the product's featured image
    //     set_post_thumbnail($product_id, $attachment_id);
    //     return true; 
    // }
    // return false;
    return $attachment_id; // Return the attachment ID
}


// // Example usage
// $image_url = "https://rebook.phototag.app/api/images/6739cfffe1af093392b52cdf?token=1111";
// $product_sku = "12345SKU";

// $result = add_image_to_media_library($image_url, $product_sku);

// if (is_wp_error($result)) {
//     echo "Error: " . $result->get_error_message();
// } else {
//     echo "Image added to media library with ID: " . $result;
// }



