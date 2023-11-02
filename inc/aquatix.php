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

        // Get the product and its current short description
        $product = wc_get_product($id);
        $short_description = $product->get_short_description();

        $searchText = 'למוצרים נוספים מחברת:';

        if (stripos($short_description, $textToAdd) === false) {

            $position = stripos($short_description, $searchText);
            if ($position !== false && !empty($short_description)) {

                $text = substr_replace($short_description, '<p>' . $textToAdd . '</p>', $position);
                $text .= $searchText;
            
                // $text = $short_description . '<p>' . $textToAdd . '</p>';
            } else {
                $text = $textToAdd;
            }
            $product->set_short_description($text);
            $product->save();
        }
        return $product;
    });
    

?>