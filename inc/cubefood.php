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
