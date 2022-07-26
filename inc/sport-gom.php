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

function register_my_session()
{
    if (!session_id()) {
        session_start();
    }
}

add_action('init', 'register_my_session');

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
