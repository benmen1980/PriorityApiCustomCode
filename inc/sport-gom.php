<?php

// ///SimplyCT.co.il
// Add custom Theme Functions here
function ui_new_role()
{

    //add the new user role
    add_role(
        'AGENT',
        'סוכן',
        array(
            'read' => true,
            'delete_posts' => false,
            'delete_published_posts' => false,
            'edit_posts' => false,
            'publish_posts' => false,
            'upload_files' => false,
            'edit_pages' => false,
            'edit_published_pages' => false,
            'publish_pages' => false,
            'delete_published_pages' => false,
        )
    );

}

add_action('admin_init', 'ui_new_role');


add_filter('simply_priority_customer_number_obligo', 'simply_priority_customer_number_obligo');
function simply_priority_customer_number_obligo($current_user)
{
    $current_user_id = $current_user->ID;
    if ($current_user->roles[0] == 'AGENT') {
        if (!empty($_SESSION['user_id'])) {
            $id = (int)$_SESSION['user_id'];
        } else {
            $id = (int)get_user_meta($current_user_id, 'select_users', true)[0];
        }
    } else {
        $id = $current_user_id;
    }

    return get_user_meta($id, 'priority_customer_number', true);

}

function my_wc_hide_in_stock_message($html, $product)
{
    if (wp_get_current_user()->roles[0] != 'AGENT') {
        return '';
    }

    return $html;
}

add_filter('woocommerce_get_stock_html', 'my_wc_hide_in_stock_message', 10, 2);
function cheque_disable_manager($available_gateways)

{
    global $woocommerce;
    if (wp_get_current_user()->roles[0] != 'AGENT') {
        {

            unset($available_gateways['cheque']);

        }
    }


    return $available_gateways;

}

add_filter('woocommerce_available_payment_gateways', 'cheque_disable_manager');
function my_custom_checkout_field_update_order_meta($order_id)
{
    $current_user_id = get_post_meta($order_id, '_customer_user', true);
    $current_user = get_user_by('id', $current_user_id);
    if ($current_user->roles[0] == 'AGENT') {
        if (!empty($_SESSION['user_id'])) {
            $id = (int)$_SESSION['user_id'];
        } else {
            $id = (int)get_user_meta($current_user_id, 'select_users', true)[0];
        }
        $userid = $id;
        update_post_meta($order_id, '_customer_user', $userid);
    }
}

add_action('woocommerce_checkout_update_order_meta', 'my_custom_checkout_field_update_order_meta');

// agents


// add the dropdown to the menu
//add_filter( 'wp_nav_menu_items', 'simply_add_menu_item_html', 10, 2 );
//function simply_add_menu_item_html( $items, $args ) {
// $items .= '<li>'.do_shortcode('[select_users add_agent_to_drop_down=false]').'</li>';
// return $items;
//}


// sync variations
add_filter('simply_ItemsAtrrVariation', 'simply_ItemsAtrrVariation_func');
function simply_ItemsAtrrVariation_func($item)
{
    $attributes['color'] = $item['SPEC2'];
    $attributes['size'] = $item['SPEC3'];
    $item['attributes'] = $attributes;
    return $item;
}

// update order item by cart item data
add_action('woocommerce_checkout_create_order_line_item', 'simply_field_update_order_item_meta', 20, 4);
function simply_field_update_order_item_meta($item, $cart_item_key, $values, $order)
{
    if (!isset($values['names']))
        return;
    $custom_data = $values['names'];
    $item->update_meta_data('sportgom_names', $custom_data);
}


// add names to post to Priority
add_filter('simply_modify_orderitem','simply_modify_orderitem');
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
    return $data;
}

function simply_code_after_sync_inventory($product_id,$item){

    $stock = $item['LOGCOUNTERS_SUBFORM'][0]['BALANCE'];
    $item['stock'] = $stock;
    $item = apply_filters('simply_sync_inventory_priority', $item);
    $stock = $item['stock'];
    update_post_meta($product_id, '_stock', $stock);

// update the ACF with the item data
    $purchase_order = $item['LOGCOUNTERS_SUBFORM'][0]['PORDERS'];
    $sales_order = $item['LOGCOUNTERS_SUBFORM'][0]['ORDERS'];
    if ( isset( $purchase_order ) ) {
        update_post_meta($product_id, 'order_priority', esc_attr($purchase_order));
    }
    if ( isset( $sales_order ) ) update_post_meta( $product_id, 'more_order_priority', esc_attr( $sales_order ) );
// here you need to update the data
    return null;
    }