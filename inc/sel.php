<?php

use PriorityWoocommerceAPI\WooAPI;

function sync_product_attachemtns()
{
    /*
    * the function pull the urls from Priority,
    * then check if the file already exists as attachemnt in WP
    * if is not exists, will download and attache
    * if exists, will pass but will keep the file attached
    * any file that exists in WP and not exists in Priority will remain
    * the function ignore other file extensions
    * you cant anyway attach files that are not images
    */
    $raw_option = WooAPI::instance()->option('sync_items_priority_config');
    $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
    $config = json_decode(stripslashes($raw_option));
    $search_field = (!empty($config->search_by) ? $config->search_by : 'PARTNAME');
    $search_field_web = (!empty($config->search_field_web) ? $config->search_field_web : '_sku');
    ob_start();
    //$allowed_sufix = ['jpg', 'jpeg', 'png'];
    $daysback = 15;
    $stamp = mktime(0 - $daysback * 24, 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $search_field_select = $search_field == 'PARTNAME' ? $search_field : $search_field . ',PARTNAME';
    $response = WooAPI::instance()->makeRequest('GET',
        'LOGPART?$filter=UDATE ge ' . urlencode($bod) . ' and EXTFILEFLAG eq \'Y\' &$select=' . $search_field_select . '&$expand=PARTEXTFILE_SUBFORM($select=EXTFILENAME,EXTFILEDES,SUFFIX;$filter=SUFFIX eq \'png\' or SUFFIX eq \'jpeg\' or SUFFIX eq \'jpg\')'
        , [], WooAPI::instance()->option('log_attachments_priority', true));
    $priority_version = (float)WooAPI::instance()->option('priority-version');
    $response_data = json_decode($response['body_raw'], true);
    foreach ($response_data['value'] as $item) {
        $search_by_value = $item[$search_field];
        $sku = $item[$search_field];
        //$product_id = wc_get_product_id_by_sku($sku);
        $args = array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => $search_field_web,
                    'value' => $search_by_value
                )
            )
        );
        $product_id = 0;
        $my_query = new \WP_Query($args);
        if ($my_query->have_posts()) {
            $my_query->the_post();
            $product_id = get_the_ID();
        } else {
            $product_id = 0;
            continue;
        }
        //**********
        $product = new \WC_Product($product_id);
        $product_media = $product->get_gallery_image_ids();
        $attachments = [];
        echo 'Starting process for product ' . $sku . '<br>';
        foreach ($item['PARTEXTFILE_SUBFORM'] as $attachment) {
            $file_path = $attachment['EXTFILENAME'];
            $is_uri = strpos('1' . $file_path, 'http') ? false : true;
            if (!empty($file_path)) {
                $file_ext = $attachment['SUFFIX'];
                $images_url = 'https://' . WooAPI::instance()->option('url') . '/zoom/primail';
                $image_base_url = $config->image_base_url;
                if (!empty($image_base_url)) {
                    $images_url = $image_base_url;
                }
                $priority_image_path = $file_path;
                $product_full_url = str_replace('../../system/mail', $images_url, $priority_image_path);
                $product_full_url = str_replace(' ', '%20', $product_full_url);
                $product_full_url = str_replace('‏‏', '%E2%80%8F%E2%80%8F', $product_full_url);
                $file_n = 'simplyCT/' . $sku . $attachment['EXTFILEDES'] . '.' . $file_ext;
                $file_name = $sku . $attachment['EXTFILEDES'] . '.' . $file_ext;

                if ($priority_version < 21.0 && $is_uri) {
                    global $wpdb;
                    $id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_n' AND meta_key = '_wp_attached_file'");
                    if ($id) {
                        echo $file_path . ' already exists in media, add to product... <br>';
                        $is_existing_file = true;
                        array_push($attachments, (int)$id);
                        continue;
                    }
                } else {
                    global $wpdb;
                    $ar = explode(',', $file_path);
                    $image_data = $ar[0];
                    $file_type = explode(';', explode(':', $image_data)[1])[0];
                    $extension = explode('/', $file_type)[1];
                    $file_name = 'simplyCT/' . $sku . $attachment['EXTFILENAME'] . '.' . $extension;
                    $id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_name' AND meta_key = '_wp_attached_file'");
                    if ($id) {
                        echo $attachment['EXTFILENAME'] . ' already exists in media, add to product... <br>';
                        $is_existing_file = true;
                        array_push($attachments, (int)$id);
                        continue;
                    }

                    echo 'File ' . $file_path . ' not exsits, downloading from ' . $images_url, '<br>';
                    $file = WooAPI::instance()->save_uri_as_image($product_full_url, $attachment['EXTFILENAME']);
                    $attach_id = $file[0];
                    $file_name = $file[1];
                }
                $attach_id = download_attachment($sku . $attachment['EXTFILEDES'], $product_full_url);
                if ($attach_id == null) {
                    continue;
                }
                if ($attach_id != 0) {
                    array_push($attachments, (int)$attach_id);
                }


            }
        };
        //  add here merge to files that exists in wp and not exists in the response from API
        $image_id_array = array_merge($product_media, $attachments);
        // https://stackoverflow.com/questions/43521429/add-multiple-images-to-woocommerce-product
        //update_post_meta($product_id, '_product_image_gallery',$image_id_array); not correct can not pass array
        update_post_meta($product_id, '_product_image_gallery', implode(',', $image_id_array));
    }
    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;

}

function sync_product_attachemtns_pdf()
{
    $raw_option = WooAPI::instance()->option('sync_items_priority_config');
    $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
    $config = json_decode(stripslashes($raw_option));
    $search_field = (!empty($config->search_by) ? $config->search_by : 'PARTNAME');
    $search_field_web = (!empty($config->search_field_web) ? $config->search_field_web : '_sku');
    $search_field_select = $search_field == 'PARTNAME' ? $search_field : $search_field . ',PARTNAME';
    $daysback = 15;
    $stamp = mktime(0 - $daysback * 24, 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    ob_start();
    $sufix = 'pdf';
    $response = WooAPI::instance()->makeRequest('GET', 'LOGPART?$filter=UDATE ge ' . urlencode($bod) . ' and EXTFILEFLAG eq \'Y\' &$select=' . $search_field_select . '&$expand=PARTEXTFILE_SUBFORM($select=EXTFILENAME;$filter=SUFFIX eq \'pdf\')');
    $response_data = json_decode($response['body_raw'], true);
    foreach ($response_data['value'] as $item) {
        $search_by_value = (string)$item[$search_field];
        $sku = (string)$item[$search_field];
        $args = array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => $search_field_web,
                    'value' => $search_by_value
                )
            )
        );
        $product_id = 0;
        $my_query = new \WP_Query($args);
        if ($my_query->have_posts()) {
            $my_query->the_post();
            $product_id = get_the_ID();
        } else {
            continue;
        }
        //**********
        //$product = new \WC_Product($product_id);
        echo 'Starting process for product ' . $sku . '<br>';
        if (!empty($item['PARTEXTFILE_SUBFORM'])) {
            $attachment = $item['PARTEXTFILE_SUBFORM'][0];
            $file_path = $item['PARTEXTFILE_SUBFORM'][0]['EXTFILENAME'];
            $file_info = pathinfo($file_path);
            $file_name = $file_info['basename'];
            $is_existing_file = false;
            // check if the item exists in media
            //$id = WooAPI::instance()->simply_check_file_exists($file_name);
            global $wpdb;
            $file_n = 'simplyCT/' . $sku . '.' . $sufix;
            $id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_n' AND meta_key = '_wp_attached_file'");
            if ($id) {
                echo $file_path . ' already exists in media, add to product... <br>';
                $is_existing_file = true;
                $attachments = (int)$id;
                update_field('instructions', $id, $product_id);

            } // if is a new file, download from Priority and push to array
            else if ($is_existing_file !== true) {
                $pdf_url = 'https://' . WooAPI::instance()->option('url') . '/pri/primail';
                echo 'File ' . $file_path . ' not exsits, downloading from ' . $pdf_url, '<br>';
                $priority_image_path = $file_path;
                $priority_image_path = str_replace('\\', '/', $priority_image_path);
                $product_full_url = str_replace('../../system/mail', $pdf_url, $priority_image_path);
                $product_full = str_replace(' ', '%20', $product_full_url);
                $thumb_id = download_attachment($sku, $product_full);
                update_field('instructions', $thumb_id, $product_id);
            };


        }
    }

    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;

}

add_filter('simply_syncItemsPriority_item', 'simply_syncItemsPriority_item_func');
function simply_syncItemsPriority_item_func($item)
{
    global $wpdb;
    $id = $item['id'];

    if (!empty($item['SELK_MARKETINGDES'])) {
        $wpdb->query($wpdb->prepare("
							UPDATE $wpdb->posts
							SET post_title = '%s'
							WHERE ID = '%s'
							",
            $item['SELK_MARKETINGDES'],
            $id
        )
        );
    }
    $content = '';
    if (isset($item['SELK_WEBTEXT_SUBFORM'])) {
        foreach ($item['SELK_WEBTEXT_SUBFORM'] as $text) {
            $content .= '<br/>'.$text["TEXT"];
        }
    }
    if (!empty($content)) {
        $wpdb->query($wpdb->prepare("
							UPDATE $wpdb->posts
							SET post_content = '%s'
							WHERE ID = '%s'
							",
            $content,
            $id
        )
        );
    }
    update_post_meta($id, 'product_barcode', $item['BARCODE']);
    update_post_meta($id, '_sku', $item['PARTNAME']);

    $content = '';
    if (isset($item['INTERNALDIALOGTEXT_SUBFORM'])) {
        foreach ($item['INTERNALDIALOGTEXT_SUBFORM'] as $text) {
            $content .= $text["TEXT"];
        }
    }
    update_post_meta($id, 'product_technical_details', $content);

    /* update video */
    $content = '';
    if (sizeof($item['SELK_PARTLINKS_SUBFORM'])>0) {
        $content = $item['SELK_PARTLINKS_SUBFORM'][0]['HOSTNAME'];
    }
    update_post_meta($id, 'product_video', $content);
    /**/
    return $item;
}

add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
function simply_syncItemsPriority_data_func($data)
{
    $data['select'] .= ',SELK_MARKETINGDES';
    $data['expand'] .= ',INTERNALDIALOGTEXT_SUBFORM,SELK_WEBTEXT_SUBFORM,SELK_PARTLINKS_SUBFORM';
    return $data;
}

add_filter('simplyct_brand_tax', 'simplyct_brand_tax_func');
function simplyct_brand_tax_func()
{
    return 'product_brand';
}

add_filter('simply_sync_inventory_priority', 'simply_sync_inventory_priority_func');
function simply_sync_inventory_priority_func($item)
{
    $item['stock'] = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE'];
    return $item;
}

add_filter('cron_schedules', 'example_add_cron_interval');
function example_add_cron_interval($schedules)
{

    $schedules['one_hour'] = array(

        'interval' => 3600,

        'display' => esc_html__('Every Hour One'),);

    return $schedules;

}

add_action('simply_cron_hook', 'sync_product_attachemtns_pdf');
add_filter('cron_schedules', 'simply_add_cron_interval');

if (!wp_next_scheduled('simply_cron_hook')) {

    $res = wp_schedule_event(time(), 'one_hour', 'simply_cron_hook');

}
function simply_add_cron_interval($schedules)
{

    $schedules['one_hour'] = array(

        'interval' => 3600,

        'display' => esc_html__('Every Hour One'),);

    return $schedules;

}

add_action('simply_add_cron', 'sync_product_attachemtns');

if (!wp_next_scheduled('simply_add_cron')) {

    $res = wp_schedule_event(time(), 'one_hour', 'simply_add_cron');

}

