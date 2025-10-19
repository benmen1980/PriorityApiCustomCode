<?php

use PriorityWoocommerceAPI\WooAPI;

add_filter('simply_syncItemsPriority_data','simply_selectPrice_func');
function simply_selectPrice_func($data)
{
    $data['select'].=',FBCN_SHORT_DES_PART,FBCC_WEBSITEIMAGES,FBCN_MODEL_NAME';
    return $data;
}


add_filter('simply_ItemsAtrrVariation', 'simply_ItemsAtrrVariation_func');
function simply_ItemsAtrrVariation_func($item)
{
	unset($item['attributes'] );
    $attributes['frame-color'] = $item['FBCN_SHORT_DES_PART'];
	$attributes['lens-color'] = $item['FBCC_WEBSITEIMAGES'];
    $attributes['material'] = $item['SPEC13'];
    $attributes['model-shape'] = $item['FBCN_MODEL_NAME'];
    $attributes['model-type'] = $item['SPEC15'];
    $attributes['nose-pads'] = $item['SPEC1'];
    $attributes['size'] = $item['SPEC9'];

	$item['attributes'] = $attributes;

	return $item;
}

add_filter('simply_ItemsVariation', 'simply_ItemsVariation_func', 10, 2);
function simply_ItemsVariation_func($childrens,$item)
{
    //add collection
    $childrens[$item['SPEC4']][$item['PARTNAME']]['product_collection'] = $item['SPEC7'];
	
	//collaboration
    $childrens[$item['SPEC4']][$item['PARTNAME']]['product_collaboration'] = $item['SPEC16'];
	
	//add acf
	$childrens[$item['SPEC4']][$item['PARTNAME']]['width'] = $item['SPEC3'];
    $childrens[$item['SPEC4']][$item['PARTNAME']]['height'] = $item['SPEC10'];
    $childrens[$item['SPEC4']][$item['PARTNAME']]['length'] = $item['SPEC20'];
	$childrens[$item['SPEC4']][$item['PARTNAME']]['showinweb'] = $item['SHOWINWEB'];

    //add color_code
    $childrens[$item['SPEC4']][$item['PARTNAME']]['color_code'] = $item['SPEC8'];

    return $childrens;
}

add_filter('custom_filter_parent_product_data', function ($parent_data, $children_data, $partname) {
    // Add a custom field
    $parent_data['product_collection'] = end($children_data)['product_collection'];
	$parent_data['product_collaboration'] = end($children_data)['product_collaboration'];
	$parent_data['width'] = end($children_data)['width'];
    $parent_data['height'] = end($children_data)['height'];
    $parent_data['length'] = end($children_data)['length'];
	$parent_data['showinweb'] = end($children_data)['showinweb'];

    return $parent_data;
}, 10, 3);


add_filter('custom_product_data_before_create', function($data, $parent) {
    // Add a custom field before product creation
    $data['product_collection'] = $parent['product_collection'] ?? '';
	$data['product_collaboration'] = $parent['product_collaboration'] ?? '';
	$data['width'] = $parent['width'] ?? '';
	$data['height'] = $parent['height'] ?? '';
	$data['length'] = $parent['length'] ?? '';
	$data['showinweb'] = $parent['showinweb'] ?? '';
	
	
    return $data;
}, 10, 3);

add_filter('custom_variation_data_before_create', function($data, $children) {
    // Add a custom field before product creation
    $data['color_code'] = $children['color_code'] ?? '';
    return $data;
}, 10, 3);


add_filter('simply_update_parent_status', function($post_data) {
    // Add a custom field before product creation
    $_product = wc_get_product( $post_data['ID'] );
    $current_status        = $_product->get_status(); 
    $post_data['post_status'] = $current_status;

    return $post_data;
}, 10, 1 );

add_action('simply_update_parent_data','simply_update_parent_data_func');
function simply_update_parent_data_func($data){
    $product_id = $data['id'];
	
	update_field('width', $data['width'], $product_id);
    update_field('height', $data['height'], $product_id);
    update_field('length', $data['length'], $product_id);
	update_field('showinweb', $data['showinweb'], $product_id);
	
    $result = wp_set_object_terms( $product_id, $data['product_collection'], 'product_collection' );
	
	$result = wp_set_object_terms( $product_id, $data['product_collaboration'], 'product_collaboration' );
	
	if ( is_wp_error( $result ) ) {
		// Failed → show error
		error_log( 'Failed to set product_collaboration: ' . $result->get_error_message() );
	} else {
		// Success → log term IDs
		error_log( 'Assigned terms to product ' . $product_id . ': ' . implode( ',', $result ) );
	}
}

add_action('simply_update_variation_data','simply_update_variation_data_func');
function simply_update_variation_data_func($variation_data){
    $id = $variation_data['variation_id'];

    $color_code = $variation_data['color_code'];
    
    update_field('color_code', $color_code, $id);
}

add_filter('simply_ItemsPriceVariation', 'simply_ItemsPriceVariation_func',10,2);
function simply_ItemsPriceVariation_func($price,$item)
{
    $price = $item['SPEC12'];

    return $price;
}

add_filter('simply_select_attr_for_variations', 'simply_select_attr_for_variations');
function simply_select_attr_for_variations($key){
    $variation_attributes = ['frame-color', 'lens-color'];
    $is_variation = in_array($key, $variation_attributes) ? 1 : 0;
    return $is_variation;
}

add_filter('simply_set_tags', 'simply_set_tags');
function simply_set_tags($data){
    if ( ! empty($data['tags']) && is_array($data['tags']) ) {
        foreach ( $data['tags'] as $tag ) {
            // Check the value of each item
            if ( $tag === 'A' ) {
                // Do something for "new"
                $data['tags'] = "New";
            } 
            // elseif ( $tag === 'sale' ) {
            //     // Do something for "sale"
            // } else {
            //     // Handle other values
            // }
        }
    }
    return $data;
}
add_filter( 'woocommerce_variation_is_active', 'filter_out_of_stock_variations', 10, 2 );
function filter_out_of_stock_variations( $active, $variation ) {
    if ( !$variation->get_price() ) {
        return false;
    }
    return $active;
}


add_action('custom_daily_variation_image_sync', 'optimized_process_new_product_images');
/**
 * Log helper
 */
function custom_log_sync_image($message) {
    $upload_dir = wp_upload_dir();
    $log_file   = $upload_dir['basedir'] . '/tmp/product_image_sync.log';

    if (!file_exists(dirname($log_file))) {
        wp_mkdir_p(dirname($log_file));
    }
	
	// Clear the log file on the 1st day of the month
    if (date('j') == 1 && file_exists($log_file)) {
        file_put_contents($log_file, ''); // reset file
    }


    $date = date("Y-m-d H:i:s");
    error_log("[$date] $message\n", 3, $log_file);
}
function get_product_id_case_insensitive($sku) {
    global $wpdb;

    // Normalize input SKU: lowercase (keep dashes)
    $normalized_sku = strtolower($sku);

    // Query for product ID, normalize database SKU: replace spaces with dashes and lowercase
    $product_id = $wpdb->get_var(
        $wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm
            ON (p.ID = pm.post_id AND pm.meta_key = '_sku')
            WHERE LOWER(REPLACE(pm.meta_value, ' ', '-')) = %s
            AND p.post_type = 'product'
            AND p.post_status IN ('draft','publish')
            LIMIT 1
        ", $normalized_sku)
    );

    // Log result
    custom_log_sync_image("🔍 Lookup SKU: {$sku} → Normalized: {$normalized_sku} → Product ID: " . ($product_id ?: 'NOT FOUND'));

    return $product_id;
}





function optimized_process_new_product_images() {
    custom_log_sync_image("=== START IMAGE SYNC ===");
	
	$raw_option     = WooAPI::instance()->option( 'sync_items_priority_config' );
    $raw_option     = str_replace( array( "\n", "\t", "\r" ), '', $raw_option );
    $config         = json_decode( stripslashes( $raw_option ) );
	$img_days_back            = ( ! empty( (int) $config->img_days_back ) ? $config->img_days_back : 1 );
    $yesterday = strtotime('-'.$img_days_back.' day');

    $attachments = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'date_query'     => [
            ['after' => date('Y-m-d H:i:s', $yesterday)]
        ],
    ]);

    custom_log_sync_image("Found " . count($attachments) . " new attachments since yesterday.");

    if (empty($attachments)) {
        custom_log_sync_image("No attachments found. Exiting.");
        return;
    }

    $variation_image_map = [];
    $product_featured_map = [];
    $sku_to_product_id = [];

    foreach ($attachments as $attachment) {
        $file = basename(get_attached_file($attachment->ID));
        $name = pathinfo($file, PATHINFO_FILENAME);

        // Normalize suffixes like -scaled, -1536x1536, etc.
        $normalized_name = preg_replace('/(-scaled|\-\d+x\d+|\-\d+)$/i', '', $name);
		custom_log_sync_image('normakized name'.$normalized_name);
        if (!str_contains($normalized_name, '_')) {
            $parent_sku = strtolower($normalized_name);
			custom_log_sync_image('$parent_sku'.$parent_sku);
            $product_featured_map[$parent_sku] = $attachment->ID;
            custom_log_sync_image("📷 Found main image for SKU={$parent_sku}, attachment={$attachment->ID}");
        } else {
            $parts = explode('_', $normalized_name);
            if (count($parts) !== 3) continue;

            [$sku_prefix, $color_code, $index] = $parts;
            $variation_key = strtolower("{$sku_prefix}_{$color_code}");
            $variation_image_map[$variation_key][] = $attachment->ID;
            custom_log_sync_image("🎨 Found variation image for key={$variation_key}, attachment={$attachment->ID}");
        }
    }
	
	    // === SET PRODUCT FEATURED IMAGES ===
    foreach ($product_featured_map as $sku => $img_id) {
        if (!isset($sku_to_product_id[$sku])) {
            $sku_to_product_id[$sku] = get_product_id_case_insensitive($sku);
        }
        $product_id = $sku_to_product_id[$sku] ?? 0;

        if ($product_id) {
            set_post_thumbnail($product_id, $img_id);
            custom_log_sync_image("✅ Set featured image for product ID={$product_id}, SKU={$sku}, img={$img_id}");
        } else {
            custom_log_sync_image("❌ No product found for featured SKU={$sku}");
        }
    }

    // === ATTACH IMAGES TO VARIATION GALLERY ===
    foreach ($variation_image_map as $key => $img_ids) {
        [$sku_prefix, $color_code] = explode('_', $key);

        if (!isset($sku_to_product_id[$sku_prefix])) {
            $sku_to_product_id[$sku_prefix] = get_product_id_case_insensitive($sku_prefix);
        }

        $parent_id = $sku_to_product_id[$sku_prefix] ?? 0;
        if (!$parent_id) {
            custom_log_sync_image("❌ No parent product found for variation key={$key}");
            continue;
        }

        custom_log_sync_image("🔄 Processing variations for parent ID={$parent_id}, key={$key}");

        $variations = wc_get_products([
            'type'   => 'variation',
            'parent' => $parent_id,
            'limit'  => -1,
            'status' => 'publish',
        ]);

        foreach ($variations as $variation) {
			$variation_id = $variation->get_id();
			$variation_color = get_field('color_code', $variation_id);

			if (strtolower($variation_color) === strtolower($color_code)) {
				$existing_gallery = get_field('gallery_image', $variation_id) ?: [];

				// Merge all images
				$all_images = array_unique(array_merge($existing_gallery, $img_ids));

				custom_log_sync_image("📝 Variation ID={$variation_id}, color={$variation_color} — Images before sort: " . implode(',', $all_images));

				// Sort images by numeric suffix (_1, _2, etc.)
				usort($all_images, function($a, $b) use ($variation_id) {
					$fileA = basename(get_attached_file($a));
					$fileB = basename(get_attached_file($b));

					preg_match('/_(\d+)(?:-[\w\d]+)?(?:\.[^.]+)?$/', $fileA, $matchA);
					preg_match('/_(\d+)(?:-[\w\d]+)?(?:\.[^.]+)?$/', $fileB, $matchB);

					$indexA = isset($matchA[1]) ? (int)$matchA[1] : 9999;
					$indexB = isset($matchB[1]) ? (int)$matchB[1] : 9999;

					custom_log_sync_image("🔍 Comparing Variation ID={$variation_id} — $fileA={$indexA} vs $fileB={$indexB}");

					return $indexA <=> $indexB;
				});
				custom_log_sync_image("✅ Variation ID={$variation_id} — Images after sort: " . implode(',', $all_images));

				// Save sorted gallery
				update_field('gallery_image', $all_images, $variation_id);

				break;
			}
		}

		wp_update_post([
            'ID'          => $parent_id,
            'post_status' => 'publish',
        ]);
        custom_log_sync_image("🚀 Published product ID={$product_id}, SKU={$sku}");
    }

    custom_log_sync_image("=== FINISHED IMAGE SYNC ===");
}


add_action('init', function() {
    if (!wp_next_scheduled('custom_daily_variation_image_sync')) {
        wp_schedule_event(time(), 'daily', 'custom_daily_variation_image_sync');
    }
});


add_filter('simply_syncInventoryPriority_data', 'simply_syncInventoryPriority_data_func');

function simply_syncInventoryPriority_data_func($data)

{
    $expand = '$expand=PARTBALANCE_SUBFORM($filter=ROYY_KROTZ eq \'Y\')';
    $data['expand'] = $expand;
    $data['select'] = 'PARTNAME';

    return $data;

}


add_action('syncStorePriority_cron_hook', 'syncStorePriority');

if (!wp_next_scheduled('syncStorePriority_cron_hook')) {

   $res = wp_schedule_event(time(), 'daily', 'syncStorePriority_cron_hook');

}


// function syncStorePriority(){
// 	//after adding all store need to remove filter by spec3 and add filter by update date
// 	//wait for roy to add the field
//     $url_addition = 'CUSTOMERS?$select=SPEC3,CUSTDES,ADDRESS,STATE,STATEA,ZIP,COUNTRYNAME,PHONE,GPSX,GPSY &$filter=SPEC3 eq \'YES\'' ;
//     $daysback = 1;                                  
//     $stamp = mktime(0 - $daysback * 24, 0, 0);
//     $bod = urlencode(date(DATE_ATOM, $stamp));
//     //$url_addition = 'CUSTOMERS?$select=CUSTNAME,SPEC3,CUSTDES,ADDRESS,STATE,STATEA,ZIP,COUNTRYNAME,PHONE,GPSX,GPSY &$filter=ROYY_UDATE ge ' . $bod . ' ' ;
	
//     $response =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
//     if ($response['status']) {
//         $data = json_decode($response['body_raw'], true)['value'];
//         $rows = [];
// 		if(empty($data)){
// 			return;
// 		}
//         foreach ($data as $store) {
            
// 			$show_on_website = ($store["SPEC3"] === "NO") ? 0 : 1;
			
//             $rows[] = [
//                 'show_on_website' => $show_on_website,
//                 'store_name'      => $store["CUSTDES"] ?? '',
//                 'phone'           => $store["PHONE"] ?? '',
//                 'store_address'   => $store["ADDRESS"] ?? '',
//                 'city'            => $store["STATEA"] ?? '', 
//                 'state'           => $store["STATE"] ?? '',
//                 'country'         => $store["COUNTRYNAME"] ?? '',
//                 'zip_code'        => $store["ZIP"] ?? '',
//                 'longtitude'      => $store["GPSX"] ?? '',
//                 'latitude'        => $store["GPSY"] ?? '',
//             ];
//         }
		
//         update_field('store_list', $rows, 483);

//     }
//     else {
//         WooAPI::instance()->sendEmailError(
//         $this->option('email_error_sync_inventory_priority'),
//         'Error Sync Stores From priority',
//         $response['body']
//         );

//     }
// }

function syncStorePriority() {
    // Priority API URL — adjust fields as needed
    //after adding all store need to remove filter by spec3 and add filter by update date
	 $daysback = 1;                                  
	 $stamp = mktime(0 - $daysback * 24, 0, 0);
 	$bod = urlencode(date(DATE_ATOM, $stamp));
    $url_addition = 'CUSTOMERS?$select=CUSTNAME,SPEC3,CUSTDES,ADDRESS,STATE,STATEA,ZIP,COUNTRYNAME,PHONE,GPSX,GPSY &$filter=ROYY_UDATE ge ' . $bod . ' ' ;
    //$url_addition = 'CUSTOMERS?$select=CUSTNAME,SPEC3,CUSTDES,ADDRESS,STATE,STATEA,ZIP,COUNTRYNAME,PHONE,GPSX,GPSY &$filter=SPEC3 eq \'YES\'';
    
    $response = WooAPI::instance()->makeRequest('GET', $url_addition, [], true);

    if (!$response['status']) {
        WooAPI::instance()->sendEmailError(
            $this->option('email_error_sync_inventory_priority'),
            'Error Sync Stores From Priority',
            $response['body']
        );
        return;
    }

    $data = json_decode($response['body_raw'], true)['value'] ?? [];
    if (empty($data)) {
        return; // nothing to update
    }

    // Load current stores from ACF
    $current_rows = get_field('store_list', 483) ?: [];

    // Re-index current rows by CUSTNAME (unique ID)
    $indexed = [];
    foreach ($current_rows as $row) {
        if (!empty($row['CUSTNAME'])) {
            $indexed[$row['CUSTNAME']] = $row;
        }
    }

    // Merge new/updated stores
    foreach ($data as $store) {
        $show_on_website = ($store["SPEC3"] === "NO") ? 0 : 1;
        $key = $store["CUSTNAME"]; // Unique ID

        $new_row = [
            'CUSTNAME'        => $store['CUSTNAME'],   // store unique ID
            'show_on_website' => $show_on_website,
            'store_name'      => $store["CUSTDES"] ?? '',
            'phone'           => $store["PHONE"] ?? '',
            'store_address'   => $store["ADDRESS"] ?? '',
            'city'            => $store["STATEA"] ?? '', 
            'state'           => $store["STATE"] ?? '',
            'country'         => $store["COUNTRYNAME"] ?? '',
            'zip_code'        => $store["ZIP"] ?? '',
            'longtitude'      => $store["GPSX"] ?? '',
            'latitude'        => $store["GPSY"] ?? '',
        ];

        // Update existing or add new
        $indexed[$key] = $new_row;
    }

    // Save back to ACF
    $rows = array_values($indexed);
    update_field('store_list', $rows, 483);
}


add_filter('simply_modify_customer_number','simply_modify_customer_number');
function simply_modify_customer_number($data){  

    $order_id = $data['order']->id;
    $order = wc_get_order( $order_id );
    $country = $order->get_billing_country(); 

    $eu_countries = WC()->countries->get_european_union_countries();

    if ( in_array( $country, $eu_countries, true ) ) {
        $cust_number = "20001"; // taxcode D01
    }
    else{
        $cust_number = "20000"; //taxcode D03
    }
    $data['CUSTNAME'] = $cust_number;
    return $data;
}
use Stripe\StripeClient;

add_filter('simply_request_data', 'simply_func_receipt');
function simply_func_receipt($data){
    $order_id = $data['orderId'];
    $order = new \WC_Order($order_id);
	
	$payment_method = $order->get_payment_method();
	echo 'Payment method slug: ' . $payment_method . '<br>'; 
	//$billing_country = $order->get_billing_country();
	$country_name = WC()->countries->countries[ $order->get_billing_country() ];
	$data['EINVOICESCONT_SUBFORM'][0]['COUNTRYNAME'] = $country_name;
	if ( in_array( $country, $eu_countries, true ) ) {
		$data['EINVOICESCONT_SUBFORM'][0]['TAXCODE'] = "D01";
	}
	else{
		$data['EINVOICESCONT_SUBFORM'][0]['TAXCODE'] = "D03";
	}
	
	if($payment_method == "stripe"){
		$data['CASHNAME'] = '007';
		$data['EPAYMENT2_SUBFORM'][0]['PAYMENTCODE'] = "20";
	}
	if ($payment_method == 'paypal'){
		$data['CASHNAME'] = '005';
		$data['EPAYMENT2_SUBFORM'][0]['PAYMENTCODE'] = "9";
	}
	
	//stripe = 007 paymentcode = 20
	//paypal = 005 paymentcode = 9
	$data['AGENTCODE'] = '013'; 
	$street_number = get_post_meta( $order_id, '_billing_street_number', true );
	$billing_houseno = get_post_meta($order_id, '_billing_houseno', true );
	
	$data['EINVOICESCONT_SUBFORM'][0]['ADRS'] = $order->get_billing_address_1().' '.$street_number;
	if(!empty($billing_houseno)){
		$data['EINVOICESCONT_SUBFORM'][0]['ADRS3'] = 'House number: '.$billing_houseno;
	}
	$shipping_street_number = get_post_meta( $order->get_id(), '_shipping_street_number', true );
	$shipping_houseno = get_post_meta( $order->get_id(), '_shipping_houseno', true );
	
	if(!empty($shipping_street_number)){
		$data['SHIPTO2_SUBFORM']['ADDRESS'] = $order->get_shipping_address_1().' '.$shipping_street_number;
	}
	if(!empty($shipping_houseno)){
		$data['SHIPTO2_SUBFORM']['ADDRESS3'] = 'House number: '.$shipping_houseno;
	}
	
	
	$data['EINVOICEITEMS_SUBFORM'][] = [
		'PARTNAME' => 'KBSU0355', // change to other item
		'TQUANT' => 1,

	];
	
	$items = $data['EINVOICEITEMS_SUBFORM'] ?? [];

	// Initialize total quantity
	$total_quantity = 0;

	// Loop through each item
	foreach ($items as $item) {
		$sku      = $item['PARTNAME'];
		$quantity = (int) $item['TQUANT'];

		// Check if SKU exists in WooCommerce
		$product_id = wc_get_product_id_by_sku($sku);

		if ($product_id) {
			// Add to total quantity only if product exists
			$total_quantity += $quantity;
		}
	}
	$data['EINVOICEITEMS_SUBFORM'][] = [
		'PARTNAME' => 'KB1139', // change to other item
		'TQUANT' => $total_quantity,

	];
	
	if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
		require_once __DIR__ . '/../vendor/autoload.php';
	} else {
		echo "Composer autoload not found";
		return; // Stop execution to avoid fatal errors
	}
	
	if($payment_method == "stripe"){
		
		 $stripe_settings = get_option('woocommerce_stripe_settings');
		$secret_key = $stripe_settings['test_secret_key'] ?? '';
		$stripe = new StripeClient($secret_key);
		$pm_id = $order->get_meta('_stripe_source_id');

		$payment_intent_id = $order->get_meta('_stripe_intent_id');

		if (!$payment_intent_id) {
			echo "No PaymentIntent ID found for order $order_id";
			return;
		}

		if (!$pm_id) {
			echo "No PaymentMethod ID found for order $order_id";
			return;
		}
		try {
			// Retrieve the PaymentMethod from Stripe
			$pm = $stripe->paymentMethods->retrieve($pm_id);

			$intent = $stripe->paymentIntents->retrieve($payment_intent_id);


	//         echo "<pre>";
	//         print_r($intent);
	//         echo "</pre>";

	//         echo "PaymentIntent ID: " . $intent->id . "<br>";
	//         echo "Status: " . $intent->status . "<br>";
	//         echo "Amount: " . $intent->amount . "<br>";
	//         echo "Currency: " . $intent->currency . "<br>";
	//         echo "Confirmation method: " . $intent->confirmation_method . "<br>";
	//         echo "Client secret: " . $intent->client_secret . "<br>";

			// Output card details
			if($payment_method == "stripe"){
				$month = str_pad($pm->card->exp_month, 2, '0', STR_PAD_LEFT); // Ensure 2 digits
				$year  = substr($pm->card->exp_year, -2); // Last 2 digits of year
				unset( $data['EPAYMENT2_SUBFORM'][0]['CONFNUM']);
				$data['EPAYMENT2_SUBFORM'][0]['VALIDMONTH'] = $month . '/' . $year;
				//$data['EPAYMENT2_SUBFORM'][0]['PAYMENTCODE'] = '20';
				$data['EPAYMENT2_SUBFORM'][0]['PAYACCOUNT'] = $pm->card->last4;
				$data['EPAYMENT2_SUBFORM'][0]['PAYDATE'] = date('Y-m-d');
				$data['EPAYMENT2_SUBFORM'][0]['CCUID'] = $intent->id;
				$data['EPAYMENT2_SUBFORM'][0]['QPRICE'] = floatval($order->get_total());
			}






		} catch (\Exception $e) {
			echo "Stripe error: " . $e->getMessage();
		}
	}
    

	
	return $data;
}

//add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
	$data['EINVOICEITEMS_SUBFORM'][] = [
		'PARTNAME' => 'KBSU0355', // change to other item
		'TQUANT' => 1,

	];
	
	$items = $data['EINVOICEITEMS_SUBFORM'] ?? [];

	// Initialize total quantity
	$total_quantity = 0;

	// Loop through each item
	foreach ($items as $item) {
		$sku      = $item['PARTNAME'];
		$quantity = (int) $item['TQUANT'];

		// Check if SKU exists in WooCommerce
		$product_id = wc_get_product_id_by_sku($sku);

		if ($product_id) {
			// Add to total quantity only if product exists
			$total_quantity += $quantity;
		}
	}
	$data['EINVOICEITEMS_SUBFORM'][] = [
		'PARTNAME' => 'KB1139', // change to other item
		'TQUANT' => $total_quantity,

	];
	
	return $data;
}



// Initialize Stripe client
//$stripe = new StripeClient('sk_live_...EX1C'); // your live/test key
// $stripe = new StripeClient('sk_test_nnnn'); 
// // WooCommerce order ID
// $stripe = new StripeClient($secret_key);
// $order_id = 604;
// $order = wc_get_order($order_id);

// // Get the Stripe PaymentMethod ID stored in order meta
// $pm_id = $order->get_meta('_stripe_source_id');

// $payment_intent_id = $order->get_meta('_stripe_intent_id');

// if (!$payment_intent_id) {
//     echo "No PaymentIntent ID found for order $order_id";
//     return;
// }

// if (!$pm_id) {
//     echo "No PaymentMethod ID found for order $order_id";
//     return;
// }

// try {
//     // Retrieve the PaymentMethod from Stripe
//     $pm = $stripe->paymentMethods->retrieve($pm_id);

//     $intent = $stripe->paymentIntents->retrieve($payment_intent_id);

    

//     // echo "<pre>";
//     // print_r($intent);
//     // echo "</pre>";

//     echo "PaymentIntent ID: " . $intent->id . "<br>";
//     echo "Status: " . $intent->status . "<br>";
//     echo "Amount: " . $intent->amount . "<br>";
//     echo "Currency: " . $intent->currency . "<br>";
//     echo "Confirmation method: " . $intent->confirmation_method . "<br>";
//     echo "Client secret: " . $intent->client_secret . "<br>";

//     // Output card details
//     echo "<pre>";
//     echo "Card Brand: {$pm->card->brand}\n";
//     echo "Last 4: ****{$pm->card->last4}\n";
//     echo "Expiry: {$pm->card->exp_month}/{$pm->card->exp_year}\n";
//     echo "</pre>";

// } catch (\Exception $e) {
//     echo "Stripe error: " . $e->getMessage();
// }


// 1. Add new column header
// Detect if HPOS is enabled
$data_storage_order = get_option('woocommerce_custom_orders_table_enabled');
$wc_orders_columns_hook       = ($data_storage_order === 'yes') ? 'manage_woocommerce_page_wc-orders_columns' : 'manage_edit-shop_order_columns';
$wc_orders_custom_column_hook = ($data_storage_order === 'yes') ? 'manage_woocommerce_page_wc-orders_custom_column' : 'manage_shop_order_posts_custom_column';

// 1. Add new columns
add_filter($wc_orders_columns_hook, function ($columns) {
    $columns['priority_document_n_status'] = __('Return Status', 'kreuzbergkinder');
    $columns['priority_document_n_number'] = __('Return Number', 'kreuzbergkinder');
    return $columns;
}, 20);

// 2. Output column content
add_action($wc_orders_custom_column_hook, function ($column, $post_id) {
    $order = wc_get_order($post_id);

    if (! $order) return;

    switch ($column) {
        case 'priority_document_n_status':
            $document_n_status = $order->get_meta('priority_document_n_status', true);
            if (empty($document_n_status)) $document_n_status = '';
            if (strlen($document_n_status) > 25) {
                $document_n_status = '<div class="tooltip">Error<span class="tooltiptext">' . esc_html($document_n_status) . '</span></div>';
            }
            echo wp_kses_post($document_n_status);
            break;

        case 'priority_document_n_number':
            $document_n_number = $order->get_meta('priority_document_n_number', true);
            echo $document_n_number ? esc_html($document_n_number) : '';
            break;
    }
}, 20, 2);

add_action( 'simply_update_order_status', 'simply_update_order_status_func', 10, 1 );
function simply_update_order_status_func( $data ) {
    // Send an email when this action is triggered
    $order_id = $data['order_id'];
	$order = wc_get_order( $order_id );
    $status = $data['status']; 
    if ( $order && $order->has_status( 'on-hold' ) ) {
        if ($status == "Final" ) {
            $order->update_status( 'processing' );
			send_order_to_ups_api( $order_id );
        }
    }
}


function send_order_to_ups_api($order_id){
    $config = json_decode(stripslashes(WooAPI::instance()->option('setting-config')));
    $access_token = $config->carmaapi;
    $url = 'https://stage.esb.k8s.vce.de/oneflow/v1/ship';
    $order = wc_get_order( $order_id );
  
    if ( ! $order ) {
        wp_mail( get_option('admin_email'), 'Carma API Error', "Order #$order_id not found." );
        return;
    }
    $shipping_country = $order->get_shipping_country();
    $store_country    = WC()->countries->get_base_country();
    $total_value      = (float) $order->get_total();
    $currency         = $order->get_currency();
    $total_quantity   = $order->get_item_count();

    $streetnum = get_post_meta( $order->get_id(), '_shipping_street_number', true );

    $package_details = [];

    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        if ( ! $product ) continue;

        $quantity   = $item->get_quantity();
        $description = $product->get_name();
        $value      = $product->get_price(); 
        $currency   = $order->get_currency();

        $hs_code           = get_post_meta( $product->get_id(), '_hs_code', true );
        $country_of_origin = get_post_meta( $product->get_id(), '_country_of_origin', true );

        $package_details[] = [
            'quantity' => $quantity,
            'customsData' => [
                'description' => $description,
                'value' => (float) $value,
                'currency' => $currency,
                'countryOfOrigin' => $country_of_origin ?: 'DE'
            ]
        ];
    }

    $receiver = [
        "name1"    => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        "street"   => $order->get_shipping_address_1(),
        "houseNo"  => $streetnum,
        "postCode" => $order->get_shipping_postcode(),
        "city"     => $order->get_shipping_city(),
        "country"  => $order->get_shipping_country()
    ];

    // Package dimensions
    $package_dimensions = $total_quantity <= 1
        ? ["length" => 22, "width" => 16, "height" => 5]
        : ["length" => 25, "width" => 19, "height" => 15];

    $body = [[
        "sender" => [
            "name1"    => "kreuzbergkinder",
            "street"   => "BockhstraBe",
            "houseNo"  => "26",
            "postCode" => "10967",
            "city"     => "Berlin",
            "country"  => "DE"
        ],
        "receiver"     => $receiver,
        "tpls"         => ["UPS1"],
        "services"     => [],
        "incoterms"    => "DAP",
        "labelFormat"  => "PDFA6",
        "reference"    => $order_id,
        "orderNo"      => $order_id,
        "shipmentId"   => $order_id,
        "skipPrinting" => true,
        "paperSize"    => "A6",
        "packages"     => [[
            "length"        => $package_dimensions['length'],
            "width"         => $package_dimensions['width'],
            "height"        => $package_dimensions['height'],
            "weight"        => 2,
            "content"       => "Eyewear",
            "type"          => "PARCEL",
            "thirdPartyOrderID" => $order_id,
            "packageDetails"    => $package_details,
            "reference"     => $order_id
        ]]
    ]];

    $json_body = wp_json_encode($body);

    $args = [
        'method'    => 'POST',
        'headers'   => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ],
        'body'      => $json_body,
        'timeout'   => 60,
    ];

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_mail(
            get_option('admin_email'),
            "Carma API Request Error - Order #$order_id",
            "Error: $error_message\n\nRequest body:\n$json_body"
        );
        return;
    }

    $status_code   = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $data          = json_decode($response_body, true);

    // If invalid JSON or empty
    if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
        wp_mail(
            get_option('admin_email'),
            "Carma API Invalid Response - Order #$order_id",
            "Raw Response:\n$response_body"
        );
        return;
    }

    // If error returned by API
    if (!empty($data[0]['errorDesc'])) {
        wp_mail(
            get_option('admin_email'),
            "Carma API Error - Order #$order_id",
            "Error: " . $data[0]['errorDesc'] . "\n\nResponse:\n$response_body"
        );
        return;
    }

    // If tracking number exists
    if (isset($data[0]['details'][0]['trackingNo'])) {
        $tracking_number = sanitize_text_field($data[0]['details'][0]['trackingNo']);
		$tracking_link = array_column($data[0]['details'][0]['parameters'], 'value', 'key')['trackinglink'] ?? '';

        // Save in order meta
        $order->update_meta_data('_tracking_number', $tracking_number);
		$order->update_meta_data('_tracking_link', $tracking_link);
        $order->save();

        // Optional: send success notification to admin
//         wp_mail(
//             get_option('admin_email'),
//             "Carma API Success - Order #$order_id",
//             "Tracking Number: $tracking_number"
//         );

        echo "Tracking Number saved: " . $tracking_number;

    } else {
        wp_mail(
            get_option('admin_email'),
            "Carma API Missing Tracking - Order #$order_id",
            "Response:\n$response_body"
        );
    }

}








?>