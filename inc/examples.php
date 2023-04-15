<?php

add_action('simply_update_variation_data','simply_update_variation_data');
function simply_update_variation_data($variation){
	$id = $variation['variation_id'];
	$meta = get_post_meta($id,'my_custom_meta',true);
	$res = update_post_meta($id,'my_custom_meta','my value');
}
//add_filter('simply_request_data','manipulate_data2');
use PriorityWoocommerceAPI\WooAPI;

function manipulate_data2($data){
    $items = [];
    foreach($data['ORDERITEMS_SUBFORM'] as $item ){
        if($item['PARTNAME']==''){
            $item['PARTNAME'] =  '000';
        }
        $items[] = $item;
    }
    $data['ORDERITEMS_SUBFORM'] = $items;
    return $data;
}
// search CUSTNAME by email or phone, input is array user_id or  order object
//add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority');
function simply_search_customer_in_priority($order){
    $url_addition = 'CUSTOMERS?$filter=CUSTNAME eq \'050654848797\'';
    $res =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
    if($res['code']==200){
        $body =   json_decode($res['body']);
        $value = $body->value[0];
        $custname =$value->CUSTNAME;
    }else{
        $custname = null;
    }
    return $custname;
}

// add names to post to Priority
//add_filter('simply_modify_orderitem','simply_modify_orderitem');
function simply_modify_orderitem($array){
	$data = $array['data'];
	$item = $array['item'];
	$text = '';
	$index = 1;
	foreach ($item->get_meta('sportgom_names') as $name){
		$text .= '['.$index.'] מספר: '. $name['number'].' שם: '.$name['name'].'<br>';
		$index++;
	}
// $text = $item->get_meta('sportgom_names');
	$data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PARTNAME'] = '000'; // debug only
	$data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['ORDERITEMSTEXT_SUBFORM'] = ['TEXT' => $text];
	$array['data'] = $data;
	return $array;
}

// add attributes
add_filter('simply_ItemsAtrrVariation', 'simply_ItemsAtrrVariation_func');
function simply_ItemsAtrrVariation_func($item)
{
	unset($item['attributes'] );
	$attributes['color'] = $item['SPEC2'];
	$attributes['size'] = $item['SPEC3'];
	$item['attributes'] = $attributes;
	return $item;
}

