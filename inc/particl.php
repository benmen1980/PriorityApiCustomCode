<?php
//SimplyCT.co.il
use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $id = $data["orderId"];
    $order = new \WC_Order($id);
    $warehouseId = get_post_meta($id, 'warehouseId', true);
    if (!empty($warehouseId)) {
        switch ($warehouseId) {
            case "275357";
                $warehousename = "Ful";
                break;
            case "277427";
                $warehousename = "Gre";
                break;
            case "393335";
                $warehousename = "SbTx";
                break;
        }
        $data['WARHSNAME'] = $warehousename;
    }
    if (get_post_meta($order->get_id(), '_billing_country', true) != 'IL') {
        if ($data['doctype'] == 'ORDERS') {
            $i = 0;
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if ($product) {
                    $data['ORDERITEMS_SUBFORM'][$i++]['VATPRICE'] = (float)$item->get_subtotal();
                }

            }
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM'])] = [
                WooAPI::instance()->get_sku_prioirty_dest_field() => '998',
                'TQUANT' => (int)1,
                'DUEDATE' => date('Y-m-d'),
            ];
            $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VATPRICE'] = (float)$order->get_cart_tax();


        } else if ($data['doctype'] == 'AINVOICES') {
            $i = 0;
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if ($product) {
                    $data['AINVOICEITEMS_SUBFORM'][$i++]['TOTPRICE'] = (float)$item->get_subtotal();
                }

            }
            $data['AINVOICEITEMS_SUBFORM'][sizeof($data['AINVOICEITEMS_SUBFORM'])] = [
                WooAPI::instance()->get_sku_prioirty_dest_field() => '998',
                'TQUANT' => (int)1,
            ];
            $data['AINVOICEITEMS_SUBFORM'][sizeof($data['AINVOICEITEMS_SUBFORM']) - 1]['TOTPRICE'] = (float)$order->get_cart_tax();
        }
    }
    return $data;
}

add_filter('woocommerce_gateway_icon', 'custom_gateway_icon', 10, 2);
add_filter('simply_modify_customer_number', 'simply_modify_customer_number_func');
function simply_modify_customer_number_func($cust_data)
{
    $cust_user = '';
    $order = $cust_data['order'];
    $currency = get_post_meta($order->get_id(), '_order_currency', true);
    switch ($currency) {
        case  "USD";
            $cust_user = "77";
            break;
        case  "EUR";
            $cust_user = "78";
            break;
        case  "AUD";
            $cust_user = "79";
            break;
        case  "CAD";
            $cust_user = "292";
            break;
        case  "GBP";
            $cust_user = "291";
            break;
        case  "ILS";
            $cust_user = "76";
            break;
        case  "BRL";
            $cust_user = "100001";
            break;
        case  "MXN";
            $cust_user = "100002";
            break;
        case  "KRW";
            $cust_user = "100003";
            break;
        case  "JPX";
            $cust_user = "100004";
            break;
    }
    $cust_data['CUSTNAME'] = $cust_user;
    return $cust_data;
}

add_filter('simply_set_priority_sku_field', 'simply_set_priority_sku_field_func');
function simply_set_priority_sku_field_func($fieldname)
{
    $fieldname = 'BARCODE';
    return $fieldname;
}
add_filter('simplyct_sendEmail', 'simplyct_sendEmail_func');
function simplyct_sendEmail_func($send)
{
    array_push($send, 'rachel@particleformen.com');
    return $send;
}
add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'custom_order_query', 10, 3 );
function custom_order_query($query, $query_vars,$context){
	//delete_post_meta(2279,'priority_invoice_status');
	//delete_post_meta(2278,'priority_invoice_status');
	if ($context === 'specific_order_query') {
		$query['meta_query'][] = array(
			'relation' => 'AND',
			array(
				'key'     => 'warehouseid',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'priority_invoice_status',
				'compare' => 'NOT EXISTS',
			)
		);
		$statuses              = array( 'wc-processing', 'wc-completed' );
		$query['post_status']  = $statuses;
	}
	return $query;
}
// Create a custom action hook for cron
function custom_sync_ainvoices() {
	WooAPI::instance()->syncAinvoices();
}
add_action('particl_sync_invoices', 'custom_sync_ainvoices');
// add the invoice and status to the orders grig even if not checked.
add_filter('manage_edit-shop_order_columns',
	function ($columns) {
		// Set "Actions" column after the new colum
		$action_column = $columns['order_actions']; // Set the title in a variable


			// add the Priority invoice number
			$columns['priority_invoice_number'] = '<span>' . __( 'Priority Invoice', 'p18w' ) . '</span>'; // title
			$columns['priority_invoice_status'] = '<span>' . __( 'Priority Invoice Status', 'p18w' ) . '</span>'; // title
		$columns['warehouseid'] = '<span>' . __( 'Warehouse ID', 'p18w' ) . '</span>'; // title

return $columns;
	},999);
add_action('manage_shop_order_posts_custom_column',
	function ($column, $post_id) {

		// HERE get the data from your custom field (set the correct meta key below)


			$invoice_number = get_post_meta($post_id, 'priority_invoice_number', true);
			$invoice_status = get_post_meta($post_id, 'priority_invoice_status', true);
		    $warehouseid = get_post_meta($post_id, 'warehouseid', true);
			if (empty($invoice_status)) $invoice_status = '';
			if (strlen($invoice_status) > 15) $invoice_status = '<div class="tooltip">Error<span class="tooltiptext">' . $invoice_status . '</span></div>';
			if (empty($invoice_number)) $invoice_number = '';

		switch ($column) {
			// invoice
			case 'priority_invoice_status' :
				echo $invoice_status;
				break;
			case 'priority_invoice_number' :
				echo '<span>' . $invoice_number . '</span>'; // display the data
				break;
			case 'warehouseid' :
				echo '<span>' . $warehouseid . '</span>'; // display the data
				break;
		}
	}, 999, 2);

