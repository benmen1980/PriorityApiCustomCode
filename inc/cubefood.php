<?php
add_filter('simply_ItemsAtrrVariation', 'simply_ItemsAtrrVariation_func');
function simply_ItemsAtrrVariation_func($item)
{
    $attributes['color'] = $item['SPEC14'];
    $attributes['size'] = $item['SPEC12'];
    return $attributes;
}

use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_modify_long_text', 'simply_modify_long_text_func');
function simply_modify_long_text_func($data)
{

    $response = WooAPI::instance()->makeRequest('GET', 'LOGPART(\'' . $data['sku'] . '\')?$select=PARTNAME&$expand=PARTTEXT_SUBFORM',
        [], true);
    $response_data = json_decode($response['body_raw'], true);

    if (isset($response_data['PARTTEXT_SUBFORM'])) {
        foreach ($response_data['PARTTEXT_SUBFORM'] as $text) {
            $data['text'] .= $text;
        }
    }
    return $data;
}

if(!function_exists('simply_set_ship_class')) {
    function simply_set_ship_class($product_id, $class_name)
    {
        // To get all the shipping classes
        $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));
        foreach ($shipping_classes as $shipping_class) {
            if ($class_name == $shipping_class->name) {
                // assign class to product
                $product = wc_get_product($product_id); // Get an instance of the WC_Product Object
                $product->set_shipping_class_id($shipping_class->term_id); // Set the shipping class ID
                $product->save(); // Save the product data to database
            }
        }
    }
}

add_action('simply_update_product_data',function($item){
    simply_set_ship_class($item['product_id'],$item['SPEC19']);
});