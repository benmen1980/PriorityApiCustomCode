<?php 
use PriorityWoocommerceAPI\WooAPI;
/**
 * sync items with variation from priority
 */
function syncItemsPriorityVariationGant()
{
    $priority_version = WooAPI::instance()->option('priority-version');
    // config
    $res =  WooAPI::instance()->option('sync_items_priority_pos_config');
    $res = str_replace(array('.', "\n", "\t", "\r"), '', $res);
    $config_v = json_decode(stripslashes($res));
    $show_in_web = (!empty($config->show_in_web) ? $config->show_in_web : null);
    $show_front = !empty($config_v->show_front) ? $config_v->show_front : null;
    $daysback = !empty((int)$config_v->days_back) ? $config_v->days_back : (!empty((int)$config->days_back) ? $config->days_back : 1);
    $stamp = mktime(0 - $daysback * 24, 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = 'UDATE ge ' . $bod;
    $search_field = 'PARTNAME';
    $data['select'] = 'PARTNAME,PARTDES,MPARTNAME,BARCODE,VATPRICE,FAMILYDES,ROYY_EFAMILYDES,SPEC2,EDE_SPECDES2,ROYY_SPECEDES2,SPEC3,EDE_SPECDES3,EDE_SPECDES4,SPEC5,SPEC6,EDE_SPECDES9,SPEC10,ROYY_SPECEDES11,SPEC12,EDE_SPECDES12,ROYY_SPECEDES12,SPEC14,EDE_SPECDES16,EDE_SPECDES18';
    $data['expand'] = '$expand=POS_INTERNETPARTSPEC_SUBFORM($select=SPEC1;),POS_PARTWEBDES_SUBFORM($select=PARTDES1;)';
    $url_addition_config = (!empty($config_v->additional_url) ? $config_v->additional_url : '');
    $response = WooAPI::instance()->makeRequest('GET','LOGPART?$select=' . $data['select'] . '&$filter=' . $filter . '&' . $data['expand'] . '',
            [], WooAPI::instance()->option('log_items_priority_variation', true));
    // check response status
    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);
        
        $parents = [];
        $childrens = [];
        //if ($response_data['value'][0] > 0) {
            foreach ($response_data['value'] as $item) {
                $variation_field = $item['MPARTNAME'].'-'.$item['SPEC2']; //2201-5
                if ($variation_field !== '-') {
                    $search_by_value = (string)$item[$search_field];  //220152XL
                    $attributes = [];
                    //add size attribute
                    $attributes['size'] = $item['SPEC3']; //2XL
                    
                    $item['attributes'] = $attributes;
                    
                    if ($attributes) {
                        $price = $item['VATPRICE'];
                        $parents[$variation_field] = [
                            'sku' => $variation_field,
                            'title' => (!empty($item['POS_INTERNETPARTSPEC_SUBFORM']['SPEC1'])) ? $item['POS_INTERNETPARTSPEC_SUBFORM']['SPEC1'] : $item['PARTDES'] ,
                            'stock' => 'Y',
                            'variation' => [],
                            'regular_price' => $price,
                            'parent_category' => $item['ROYY_EFAMILYDES'], //גברים
                            'categories' => [ // i need to have sub category in hebrew
                                $item['ROYY_SPECEDES11']
                            ],
                            'categories-slug' => [
                                $item['ROYY_SPECEDES11'] .'-'.$item['ROYY_EFAMILYDES']
                            ]
                        ];
                        if (!empty($show_in_web)) {
                            $parents[$variation_field][$show_in_web] = $item[$show_in_web];
                        }
                        $childrens[$variation_field][$search_by_value] = [
                            'sku' => $search_by_value,
                            'regular_price' => $price,
                            'stock' => 'Y',
                            'parent_title' => $item['POS_INTERNETPARTSPEC_SUBFORM']['SPEC1'],
                            'title' => $item['POS_INTERNETPARTSPEC_SUBFORM']['SPEC1'],
                            'stock' => 'outofstock',
                            'attributes' => $attributes,
                            'barcode' => $item['BARCODE'],
                            'model' =>  $item['MPARTNAME'],
                            'color' =>  $item['EDE_SPECDES2'],
                            'grouped_color' =>  $item['ROYY_SPECEDES2'],
                            'color_code' => $item['SPEC2'],
                            'measure_bar_code' =>  $item['SIZEBARCODE'], 
                            'brand_desc' =>  $item['EDE_SPECDES4'],
                            'year' => $item['SPEC5'],
                            'season' => $item['SPEC6'],
                            'concept' => $item['EDE_SPECDES9'],
                            'cut' => $item['SPEC10'],
                            'sub_cat' => $item['ROYY_SPECEDES11'], //missing it's parameter 11- i need it in hebrew
                            'cat' => $item['ROYY_EFAMILYDES'], //גברים
                            'fabric' => $item['SPEC12'],
                            'fabric_desc' => $item['EDE_SPECDES12'],
                            'made_in' => $item['SPEC14'],
                            'sleeve_type' => $item['EDE_SPECDES18'],
                            'sub_group_jersey' => $item['EDE_SPECDES16'],
                            'fabric_composition' => $item['ROYY_SPECEDES12'],
                            'child_size' => $item['EDE_SPECDES3']


                        ];
                    }
                }
            }
            foreach ($parents as $partname => $value) {
                if (count($childrens[$partname])) {
                    $parents[$partname]['variation'] = $childrens[$partname];
                    $parents[$partname]['title'] = $parents[$partname]['title'];
                    // $parents[$partname]['post_content'] = $parents[$partname]['post_content'];
                    foreach ($childrens[$partname] as $children) {
                        foreach ($children['attributes'] as $attribute => $attribute_value) {
                            if ($attributes) {
                                if (!empty($parents[$partname]['attributes'][$attribute])) {
                                    if (!in_array($attribute_value, $parents[$partname]['attributes'][$attribute]))
                                        $parents[$partname]['attributes'][$attribute][] = $attribute_value;
                                } else {
                                    $parents[$partname]['attributes'][$attribute][] = $attribute_value;
                                }
                            }
                        }
                    }
                } else {
                    unset($parents[$partname]);
                }
            }
            if ($parents) {
                foreach ($parents as $sku_parent => $parent) {

                    $id = create_product_variable(array(
                        'author' => '', // optional
                        'title' => $parent['title'],
                        'content' => '',
                        'excerpt' => '',
                        'regular_price' => '', // product regular price
                        'sale_price' => '', // product sale price (optional)
                        'stock' => $parent['stock'], // Set a minimal stock quantity

                        'sku' => $sku_parent, // optional
                        'tax_class' => '', // optional
                        'weight' => '', // optional
                        // For NEW attributes/values use NAMES (not slugs)
                        'parent_category'  => $parent['parent_category'],
                        'attributes' => $parent['attributes'],
                        'categories' => $parent['categories'],
                        'categories-slug' => $parent['categories-slug'],
                        'status' => 'publish'
                    ));

                    $parents[$sku_parent]['product_id'] = $id;
                    foreach ($parent['variation'] as $sku_children => $children) {
                        // The variation data
                        $variation_data = array(
                            'attributes' => $children['attributes'],
                            'sku' => $sku_children,
                            'regular_price' => !empty($children['regular_price']) ? ($children['regular_price']) : $parent[$sku_children]['regular_price'],
                            'product_code' => $children['sku'],
                            'sale_price' => '',
                            'stock' => $children['stock'],
                            'show_front' => $children['show_front']
                        );
                        // The function to be run
                        create_product_variation($id, $variation_data);
                        // update ACFs
                        update_field('barcode', $children['barcode'], $id);
                        update_field('model', $children['model'], $id);
                        update_field('grouped_color', $children['grouped_color'], $id);
                        update_field('color', $children['color'], $id);
                        update_field('color_code', $children['color_code'], $id);
                        update_field('measure_bar_code', $children['measure_bar_code'], $id);
                        update_field('brand_desc', $children['brand_desc'], $id);
                        update_field('year', $children['year'], $id);
                        update_field('season', $children['season'], $id);
                        update_field('concept', $children['concept'], $id);
                        update_field('cut', $children['cut'], $id);
                        update_field('sub_cat', $children['sub_cat'], $id);
                        update_field('fabric', $children['fabric'], $id);
                        update_field('fabric_desc', $children['fabric_desc'], $id);
                        update_field('fabric_composition', $children['fabric_composition'], $id);
                        update_field('sleeve_type', $children['sleeve_type'], $id);
                        update_field('sub_group_jersey', $children['sub_group_jersey'], $id);
                        update_field('made_in', $children['made_in'], $id);

                        $term = get_term_by('name', $attributes['size'], 'pa_size')->term_id;
                       
                        update_field('child_size', $children['child_size'], 'pa_size_'.$term);
                    }
                    unset($parents[$sku_parent]['variation']);
                }

            }
        //}
        // add timestamp
        //WooAPI::instance()->updateOption('items_priority_variation_update', time());
    } else {
        $subj = 'check sync item';
        wp_mail( 'elisheva.g@simplyct.co.il', $subj, implode(" ",$response) );
    }
    
}

add_action('syncItemsPriorityVariationGant_cron_hook', 'syncItemsPriorityVariationGant');

if (!wp_next_scheduled('syncItemsPriorityVariationGant_cron_hook')) {

    $res = wp_schedule_event(time(), 'daily', 'syncItemsPriorityVariationGant_cron_hook');

}