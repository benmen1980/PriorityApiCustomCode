<?php
add_filter('simply_ItemsAtrrVariation', 'simply_ItemsAtrrVariation_func');
function simply_ItemsAtrrVariation_func($item)
{
    $attributes['color'] = $item['SPEC14'];
    $attributes['size'] = $item['SPEC12'];
    return $attributes;
}

add_filter('simply_modify_long_text', 'simply_modify_long_text_func');
function simply_modify_long_text_func($data)
{
    $response = $this->makeRequest('GET',
        'LOGPART(\'' . $data['sku'] . '\')?$select=PARTNAME&$expand=PARTTEXT_SUBFORM',
        [], $this->option('log_items_priority_variation', true));
    $response_data = json_decode($response['body_raw'], true);
    $item = $response_data['value'][0];
    if (isset($item['content'])) {
        $item['content'] = '';
    }
    if (isset($item['PARTTEXT_SUBFORM'])) {
        foreach ($item['PARTTEXT_SUBFORM'] as $text) {
            $item['content'] .= $text;
        }
    }
    $data['text'] = $item['content'];
    return $data;
}
