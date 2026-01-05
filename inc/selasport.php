<?php
use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_ItemsAtrrVariation', 'simply_ItemsAtrrVariation_func');
function simply_ItemsAtrrVariation_func($item)
{
    /*if( empty($item['SPEC6']) ) {
        $item['SPEC6'] = $item['PARTDES'];
    }

    if( !empty($item['SPEC3']) ) {
        $attributes['color'] = $item['SPEC3'];
    }*/
    if( !empty($item['SPEC4']) ) {
        $attributes['size'] = $item['SPEC4'];
    }

    $item['attributes']= $attributes;
    
    return $item;
}


add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
function simply_syncItemsPriority_data_func($data)
{
    $data['select'] .= ',BARCODE';
    return $data;
}


add_action('simply_update_product_price', 'simply_update_product_price_func');
function simply_update_product_price_func($item)
{
	//$stop_processing = true;
    $product_id = $item['product_id'];
	
    $image_base_url = '';
    $priority_version = 23;
    $search_field = 'BARCODE';
    $sku = $item[ $search_field ];
    if ( ! get_the_post_thumbnail_url( $product_id ) ) {
        $file_     = WooAPI::instance()->load_image( $item['EXTFILENAME'] ?? '', $image_base_url, $priority_version, $sku, $search_field );
        $attach_id = $file_[0];
        $file      = $file_[1];
        if ( empty( $file ) ) {
            return;
        }
        //include $file;
        require_once( ABSPATH . '/wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, get_attached_file($attach_id) );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        set_post_thumbnail( $product_id, $attach_id );
    }
}


add_filter('simplyct_brand_tax', 'simplyct_brand_tax_func');
function simplyct_brand_tax_func()
{
    return 'product_brand';
}


/*add_action('simply_update_parent_data', 'simply_update_parent_data_func');
function simply_update_parent_data_func($data)
{
    $product = wc_get_product( $data['id'] );
    if( !is_a($product, 'WC_Product_Variable') ) {
        return;
    }

    $children = $product->get_children();
    foreach( $children as $variation_id ) {
        $variation = wc_get_product( $variation_id );
        if( empty($variation) ) {
            return;
        }
        $image_id = $variation->get_image_id();
        if( !empty( $image_id ) ) {
            $product->set_image_id( $image_id );
            $product->save();
            return;
        }
    }
    
}*/


add_action('simply_update_variation_data','simply_update_variation_data_func');
function simply_update_variation_data_func($variation_data) {
    if($variation_data['show_in_web'] != 'Y'){
        return;
    }

    $variation_id = $variation_data['variation_id'];
    $variation = wc_get_product( $variation_id );
    if( empty($variation) && !is_a($variation, 'WC_Product_Variation') ) {
        return;
    }
    $variation = new WC_Product_Variation( $variation_id );
    $sku = $variation->get_sku();

    $url_addition = 'LOGPART?$select=PARTNAME&$filter=PARTNAME eq \''. $sku .'\' and EXTFILEFLAG eq \'Y\'&$expand=PARTEXTFILE_SUBFORM($select=EXTFILENAME,EXTFILEDES,SUFFIX;$filter=(SUFFIX eq \'png\' or SUFFIX eq \'jpeg\' or SUFFIX eq \'jpg\'))';
    $response_gallery =  WooAPI::instance()->makeRequest('GET', $url_addition, [], WooAPI::instance()->option('log_items_priority_variation', true));
    if( $response_gallery['code'] != 200 ) {
        return;
    }

    $data_gallery = json_decode($response_gallery['body']);
    $data_gallery_item = $data_gallery->value[0];

    $attachments = [];
    foreach ($data_gallery_item->PARTEXTFILE_SUBFORM as $attachment) {
        $file_path = $attachment->EXTFILENAME;
        if (!empty($file_path)) {
            $file_ext = $attachment->SUFFIX;
                
            $ar = explode(',', $file_path);
            $image_data = $ar[0]; //data:image/jpeg;base64
            $file_type = explode(';', explode(':', $image_data)[1])[0]; //image/jpeg
            $extension = explode('/', $file_type)[1];  //jpeg
            
            $file_n = 'simplyCT/' . $sku . $attachment->EXTFILEDES . '.' . $file_ext; //simplyCT/0523805238-5.jpg
            $file_n2 = 'simplyCT/' . $sku . $attachment->EXTFILEDES . '.' . $extension; //simplyCT/0523805238-5.jpeg
            $file_name = $attachment->EXTFILEDES . '.' . $extension; //05238-5.jpg

            $upload_path = wp_get_upload_dir()['basedir'] . '/' . $file_n; 
            $upload_path_2 = wp_get_upload_dir()['basedir'] . '/' . $file_n2;
            if ( ! function_exists( 'wp_crop_image' ) ) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }
            // check if the item exists in media
            //in the past we uploaded image like this: 05238-5.jpg
            $id = WooAPI::instance()->simply_check_file_exists($file_name);
            global $wpdb;
            $id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_name' AND meta_key = '_wp_attached_file'" );
            if($id){
                //echo $file_path . ' already exists in media, add to product... <br>';
                array_push( $attachments,  (int)$id );
                continue;
            }
            elseif (file_exists($upload_path) == true) {
                global $wpdb;
                $id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_n' AND meta_key = '_wp_attached_file'");
                if ($id) {

                    // Generate the metadata for the attachment, and update the database record.
                    $attach_data = wp_generate_attachment_metadata( $id, $upload_path);
                    wp_update_attachment_metadata( $id, $attach_data );

                    //echo $file_path . ' already exists in media, add to product... <br>';
                    $is_existing_file = true;
                    array_push($attachments, (int)$id);
                    continue;
                }
            }
            elseif(file_exists($upload_path_2) == true){
                global $wpdb;
                $id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_n2' AND meta_key = '_wp_attached_file'");
                if ($id) {

                    // Generate the metadata for the attachment, and update the database record.
                    $attach_data = wp_generate_attachment_metadata( $id, $upload_path_2);
                    wp_update_attachment_metadata( $id, $attach_data );

                    //echo $file_path . ' already exists in media, add to product... <br>';
                    $is_existing_file = true;
                    array_push($attachments, (int)$id);
                    continue;
                }

            }
            else {
                //echo 'File ' . $file_path . ' not exsits, downloading from ' . $images_url, '<br>';
                $file = WooAPI::instance()->save_uri_as_image($file_path, $sku . $attachment->EXTFILEDES);
                $attach_id = $file[0];
        
                // $file_name = $file[1];
            }
            if ($attach_id == null) {
                continue;
            }
            if ($attach_id != 0) {
                array_push($attachments, (int)$attach_id);
            }

        }
    }

    $parent_product = wc_get_product( $variation->get_parent_id() );

    $changed = false;
    if( $variation->get_image_id() && $variation->get_image_id() != $parent_product->get_image_id() ) {
        $parent_product->set_image_id( $variation->get_image_id() );
        $changed = true;
    }
    
    if( !empty($attachments) ) {
        $product_media = $parent_product->get_gallery_image_ids();
        $image_id_array = array_merge( $product_media, $attachments );
        $parent_product->set_gallery_image_ids( $image_id_array );
        $changed = true;
    }

    if( $changed ) {
        $parent_product->save();
    }
}


add_filter('simply_update_parent_status', function($post_data) {
    // Add a custom field before product creation
    $_product = wc_get_product( $post_data['ID'] );
    $current_status        = $_product->get_status(); 
    $post_data['post_status'] = $current_status;
	$post_data['post_name'] = $_product->get_name();
	$post_data['post_title'] = $_product->get_name(); 
	
	/*$children = $_product->get_children();
    foreach( $children as $variation_id ) {
        $variation = wc_get_product( $variation_id );
        if( empty($variation) ) {
            continue;
        }
        $image_id = $variation->get_image_id();
        if( !empty( $image_id ) ) {
            $variation->set_image_id( '' );
            $variation->save();
        }
    }*/
	
	echo 'current_status ' . $current_status . '<br>';

    return $post_data;
}, 10, 1 );