<?php
    add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
    function simply_syncItemsPriority_data_func($data)
    {
        $data['select'] .= ',EPARTDES';
        return $data;
    }

    add_filter('simply_syncCustdes', 'syncCustdes_func');
    function syncCustdes_func ($meta) {
        // $custdes = !empty($meta['first_name'][0] . ' ' . $meta['last_name'][0]);
        $custdes = !empty($meta['first_name'][0]) ? $meta['first_name'][0] . ' ' . $meta['last_name'][0] : $meta['nickname'][0];
        return $custdes;
    };

    add_action('simply_update_product_data', function($item){
        $textToAdd =  $item['EPARTDES'];
        $id = $item['product_id'];

        update_field('product_description_language', $textToAdd, $id);
    });

    add_filter('simply_request_data', 'simply_func');
    function simply_func($data)
    {
        if (isset($data['CDES'])){
            unset($data['CDES']);
        };
        return $data;
    }
?>