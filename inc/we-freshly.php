<?php
// add the dropdown to the menu
add_filter( 'wp_nav_menu_items', 'simply_add_menu_item_html', 10, 2 );
function simply_add_menu_item_html( $items, $args ) {
   $items .= '<li>'.do_shortcode('[select_users add_agent_to_drop_down="true"]').'</li>';
   return $items;
}

// add agent details to order meta (if needed)
add_action( 'woocommerce_checkout_update_order_meta', function( $order_id ) {
   if( empty( $_SESSION['agent_id'] ) ) {
      return;
   }

   $priority_agent_number = get_user_meta( $_SESSION['agent_id'], 'priority_agent_number', true );
   if( empty( $priority_agent_number ) ) {
      return;
   }

   $order = wc_get_order($order_id);
   $order_user_id = $order->get_customer_id();

   if( $order_user_id === $_SESSION['agent_id'] ) {
      return;
   }

   update_post_meta( $order_id, 'agent_user_id', $_SESSION['agent_id'] );
   update_post_meta( $order_id, 'priority_agent_number', $priority_agent_number );   
} );

// add agent code to Shipping and change Shipping delivery date if necessary
add_filter( 'simply_request_data', function( $data ) {
   if( empty( $data['doctype'] ) || 'DOCUMENTS_D' !== $data['doctype'] || empty( $data['orderId'] ) ) {
      return $data;
   }

   $order = wc_get_order( $data['orderId'] );
   $priority_agent_number = $order->get_meta('priority_agent_number');

   if( !empty( $priority_agent_number ) ) {
      $data['AGENTCODE'] = $priority_agent_number;
   }

   $delivery_date = $order->get_meta('_delivery_date');
   if( !empty( $delivery_date ) ) {
      $delivery_date = DateTime::createFromFormat( 'd/m/Y', $delivery_date );
      if( false !== $delivery_date ) {
         $data['CURDATE'] = $delivery_date->format('Y-m-d');
      }
   }

   return $data;
} );