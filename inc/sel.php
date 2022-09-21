<?php
function sync_product_attachemtns_pdf()
{
    $raw_option = $this->option('sync_items_priority_config');
    $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
    $config = json_decode(stripslashes($raw_option));
    $search_field = (!empty($config->search_by) ? $config->search_by : 'PARTNAME');
    $search_field_web = (!empty($config->search_field_web) ? $config->search_field_web : '_sku');
    $search_field_select = $search_field == 'PARTNAME' ? $search_field : $search_field . ',PARTNAME';
    $daysback = 9;
    $stamp = mktime(0 - $daysback * 24, 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    ob_start();
    $sufix = 'pdf';
    $response = $this->makeRequest('GET', 'LOGPART?$filter=UDATE ge ' . urlencode($bod) . ' and EXTFILEFLAG eq \'Y\' &$select=' . $search_field_select . '&$expand=PARTEXTFILE_SUBFORM($select=EXTFILENAME;$filter=SUFFIX eq \'pdf\')');
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
            //$id = $this->simply_check_file_exists($file_name);
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
                $pdf_url = 'https://' . $this->option('url') . '/pri/primail';
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
            $content .= $text["TEXT"];
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
    return $item;
}

add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
function simply_syncItemsPriority_data_func($data)
{
    $data['select'] .= ',SELK_MARKETINGDES';
    $data['expand'] .= ',INTERNALDIALOGTEXT_SUBFORM,SELK_WEBTEXT_SUBFORM';
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
add_filter( 'cron_schedules', 'example_add_cron_interval' );
function example_add_cron_interval( $schedules ) {

    $schedules['one_hour'] = array(

        'interval' => 3600,

        'display'  => esc_html__( 'Every Hour One' ), );

    return $schedules;

}
add_action( 'simply_cron_hook', 'sync_product_attachemtns_pdf' );

if ( ! wp_next_scheduled( 'simply_cron_hook' ) ) {

    $res = wp_schedule_event( time(), 'one_hour', 'simply_cron_hook' );

}
