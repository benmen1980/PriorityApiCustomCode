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
// 		$searchText = 'למוצרים נוספים מחברת:';

// 		// Get the product and its current short description
// 		$product = wc_get_product($id);
// 		if ( $product ) {
// 			$short_description = $product->get_short_description();
// 			if (stripos($short_description, $searchText) !== false) {
// 				$short_description = str_ireplace($searchText, '', $short_description);

// 				$product->set_short_description($short_description);
// 				$product->save();
// 			}

// 			if (stripos($short_description, $textToAdd) !== false) {
// 				$short_description = str_ireplace($textToAdd, '', $short_description);

// 				$product->set_short_description($short_description);
// 				$product->save();
// 			}
		update_field('product_description_language', $textToAdd, $id);
// 		}

// 		return $product;
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