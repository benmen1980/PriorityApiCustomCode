<?php
use PriorityWoocommerceAPI\WooAPI;

function ajax_enqueue() {
    global $wp_query; 
	// Enqueue javascript on the frontend.
    wp_enqueue_script('admin-ajax-scripts', P18AW_ASSET_URL.'admin-ajax-scripts.js', array('jquery'));
    // The wp_localize_script allows us to output the ajax_url path for our script to use.
	wp_localize_script('admin-ajax-scripts', 'ajax_obj', array( 
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
    ));
}

add_action( 'admin_enqueue_scripts', 'ajax_enqueue' );

//sync cutomer priority to web
function syncCustomersPriority() {
    $daysback = 10; // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = '('. rawurlencode('CURDATE ge ' . $bod ) . ')';
    // Get all customer details from Priority
    $response = WooAPI::instance()->makeRequest('GET', 
    'UNIT_B2BWEBSITES?$filter=' . $url_addition . ' ', [], true );
    // check response
    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);      
        if ($response_data['value'][0] > 0) {
            create_new_project_post($response_data['value']);

        } else {
            exit(json_encode(['status' => 0, 'msg' => 'Error Sync customers Priority']));
            $subj = 'check sync customers';
            wp_mail( 'margalit.t@simplyct.co.il', $subj, implode(" ",$response) );
        }
    }

};

add_action('syncCustomersPriority_hook', 'syncCustomersPriority');

if (!wp_next_scheduled('syncCustomersPriority_hook')) {

    $res = wp_schedule_event(time(), 'daily', 'syncCustomersPriority_hook');

}

// Create a new project post in a specific minisite
function create_new_project_post($customer_array) {
    // print_r($customer_array);
    foreach($customer_array as $response_array) {
        // Get all sites in the multisite network
        $sites = get_sites();
        //זמני התאמה למקומי שלי אח"כ צריך לתקן להתאמה לאתר של יונידרס
        // unset($response_array['WEBSITE']);
        // $response_array['WEBSITE'] = 'http://localhost/unidress/test/';
        $url = $response_array['WEBSITE'];
        $url = explode('/', $url);
        $utl_site_lochally = 'wordpressmu-948345-3693715.cloudwaysapps.com/';
        // $new_url = $utl_site_lochally . end($url) .'/';
        $new_url = $utl_site_lochally . $url['3'] .'/';
        $response_array['WEBSITE'] = $new_url;

        // Loop through each site and print information
        foreach ($sites as $site) {
            $site_id = $site->blog_id;
            // $path = $site->path;
            // $url_site = "http://localhost".  $path;  

            $path = $site->domain;
            $url_site = $path . $site->path;  
            if ($response_array['WEBSITE'] == $url_site) {
                // echo 'yes. '. $url_site;
                switch_to_blog($site_id);

                // if product exsits, update
                $search_by_title = (string) $response_array['CUSTDES'];
                $args = array(
                    'post_type'   => 'customers',
                    'post_status' => array( 'publish', 'draft' ),
                    'title' => $search_by_title,                
                );

                // Set the post type and taxonomy terms
                $post_type = 'customers';
                $taxonomy = 'minisite';
                $minisite_term = $site->path; 
                 
                $product_id = 0;

                $my_query = new \WP_Query( $args );
                if ( $my_query->have_posts() ) {
                    while ( $my_query->have_posts() ) {
                        $my_query->the_post();
                        $product_id = get_the_ID();
                    }
                }
                // insert customer
                if ($product_id == 0) {
                    // Post data
                    $post_data = array(
                        'post_title'    => $response_array['CUSTDES'],
                        'post_content'  => '',
                        'post_status'   => 'publish',
                        'post_type'     => $post_type,
                    );
                    // Insert the post
                    $post_id = wp_insert_post($post_data);
                }
                else {
                    $post_id = $product_id;
                }
                //update customer
                // Assign the minisite term to the post
                wp_set_post_terms($post_id, $minisite_term, $taxonomy);
                
                update_post_meta($post_id, 'priority_customer_number', $response_array['CUSTNAME']);
                $field_1_value = update_field('customer_type', 'campaign', $post_id);

                // Get the value of field_1 for the current post
                $field_1_value = get_field('customer_type', $post_id);

                if($response_array['B2BCAMPBUDGCODE'] == "01" || $response_array['B2BCAMPBUDGCODE'] == "02") {
                    $field_2_value = 'תצוגה סטנדרטית';
                } elseif ($response_array['B2BCAMPBUDGCODE'] == "03") {
                    $field_2_value = 'תצוגת רשימה סגורה';
                }

                // Get the term ID based on the term slug
                $term = get_term_by('name', $field_2_value, 'ordering_style_campaign');

                // Update the value of the taxonomy field
                if ($term && !is_wp_error($term)) {
                    update_field('ordering_style', $term->term_id, $post_id);
                } else {
                    //open term 
                    
                    // Assuming you want to add a term to the category taxonomy
                    $taxonomy = 'ordering_style_campaign';
                    // Add the term
                    $new_term_id = wp_insert_term('תצוגה סטנדרטית', $taxonomy, array(
                        'parent' => 0,
                        'slug' => 'standard',
                    ));
                    $new_term_id = wp_insert_term('תצוגת רשימה סגורה', $taxonomy, array(
                        'parent' => 0,
                        'slug' => 'closed_list',
                    ));

                    // Get the term ID based on the term slug
                    $term = get_term_by('name', $field_2_value, 'ordering_style_campaign');

                    if ($term && !is_wp_error($term)) {
                        update_field('ordering_style', $term->term_id, $post_id);
                    } else {
                        // error_log('Term not found: ' . $field_2_value);
                    }

                }
                
                update_field('customer_price_list', 'Stores', $post_id);

                //update VAT fields on the WooCommerce website
                if ($response_array['CODE'] == 'שח') {
                    update_option('woocommerce_currency', 'ILS');
                } else {
                    update_option('woocommerce_currency', 'USD');
                }

                // Enable taxes and tax calculations
                update_option('woocommerce_calc_taxes', 'yes');
                
                // Set "Prices entered with tax" to "No"
                update_option('woocommerce_prices_include_tax', 'no');
                
                // Set display prices in the shop or cart and checkout
                if ($response_array['WITHTAX'] == 'Y') {
                    $customer_with_tax = "true";
                    update_field('price_list_include_vat', $customer_with_tax, $post_ids);

                    update_option('woocommerce_tax_display_shop', 'incl');
                    update_option('woocommerce_tax_display_cart', 'incl');
                } else {
                    update_option('woocommerce_tax_display_shop', 'excl');
                    update_option('woocommerce_tax_display_cart', 'excl');
                }   

                // Restore the original site context
                restore_current_blog();
            }
        }
    }

}


//sync cmapign priority to web
function syncCampignPriority() {
    $daysback = 2; // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = '('. rawurlencode('CURDATE ge ' . $bod ) . ')';
    // Get all cutomer extensions from Priority
    $response = WooAPI::instance()->makeRequest('GET', 
    'UNIT_B2BWEBSITES?$expand=UNIT_B2BCAMPAIGN_SUBFORM($filter ='  . $url_addition . '; $expand=UNIT_B2BCAMPSAL_SUBFORM($filter=' . $url_addition . '),UNIT_B2BCAMPROJ_SUBFORM,UNIT_B2BCOLLECTION_SUBFORM($filter=' . $url_addition . '; $expand=UNIT_B2BCOLLECSIZE_SUBFORM),UNIT_B2BCAMPKIT_SUBFORM($filter=' . $url_addition . '; $expand=UNIT_B2BCAMPASSIG_SUBFORM($filter=' . $url_addition . '),UNIT_B2BCAMPKITEMP_SUBFORM,UNIT_B2BCAMPKITPART_SUBFORM($filter=' . $url_addition . '))) ', [], true );
    // check response
    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);      
        if ($response_data['value'][0] > 0) {
            // print_r($response_data['value'][0]);
            foreach($response_data['value'] as $customer) {
                $priority_customer = $customer['CUSTNAME'];
                if (!empty($customer['UNIT_B2BCAMPAIGN_SUBFORM'])) {
                    create_campign_with_kits($customer['UNIT_B2BCAMPAIGN_SUBFORM'], $priority_customer);
                }
            }
        } 
        else {
            exit(json_encode(['status' => 0, 'msg' => 'Error Sync customers Priority']));
            $subj = 'check sync customers';
            wp_mail( 'margalit.t@simplyct.co.il', $subj, implode(" ",$response) );
        }
    }

};

add_action('syncCampignPriority_hook', 'syncCampignPriority');

if (!wp_next_scheduled('syncCampignPriority_hook')) {

    $res = wp_schedule_event(time(), 'hourly', 'syncCampignPriority_hook');

}

function create_campign_with_kits($campaigns, $priority_customer) {
    // Get all sites in the multisite network
    $sites = get_sites();
    foreach ($sites as $site) {
        $site_id = $site->blog_id;
        $path = $site->path;

        //search the customer's website by customer number and directs the additions in the code to him 
        $args = array(
            'post_type'   => 'customers',
            'post_status' => array( 'publish', 'draft' ),
            'posts_per_page' => 1,
            'meta_query'  => array(
                array(
                    'key'   =>'priority_customer_number',
                    'value' => $priority_customer
                )
            )                
        );
        switch_to_blog($site_id);
        $this_site = 0;
        $my_query = new \WP_Query( $args );
        if ( $my_query->have_posts() ) {
            while ( $my_query->have_posts() ) {
                $my_query->the_post();
                $this_site = $site_id;
                $customer_name = get_the_title();
                $customer_id = get_the_ID();
            }
            // wp_reset_postdata();
        }

        if ($this_site !== 0) {
            foreach($campaigns as $campaign) {
                // if extension exsits, update
                $search_by_title = (string) $campaign['CAMPAIGDES'];
                $args = array(
                    'post_type'   => 'campaign',
                    'post_status' => array( 'publish', 'draft' ),
                    'title' => $search_by_title,                
                );

                // Set the post type and taxonomy terms
                $post_type = 'campaign';
                $taxonomy = 'minisite';
                $minisite_term = $path; 

                $campaign_id = 0;
                $my_query = new \WP_Query( $args );
                if ( $my_query->have_posts() ) {
                    while ( $my_query->have_posts() ) {
                        $my_query->the_post();
                        $campaign_id = get_the_ID();
                    }
                }
                if ($campaign['B2BCAMPSTATDES'] == 'בהקמה' || $campaign['B2BCAMPSTATDES'] == 'פעיל') {
                    if ($campaign_id == 0) {
                        // insert extension
                        $campaign_data = array(
                            'post_title'    => $campaign['CAMPAIGDES'],
                            'post_content'  => ' ',
                            'post_status'   => 'publish',
                            'post_type'     => $post_type,
                        );
                        // Insert the post
                        $campaign_id = wp_insert_post($campaign_data);
                    }          
                    //update acf field campaign details
                    // Assign the minisite term to the post
                    wp_set_post_terms($campaign_id, $minisite_term, $taxonomy);
                    
                    update_field('campaign_customer', $customer_id, $campaign_id);
                    update_field('campaign_number', $campaign['CAMPAIGNNUM'], $campaign_id);

                    /*if(!empty($campaign['SHOWBAL']) && $campaign['SHOWBAL'] == "Y") {
                        $campaign_show_notes = "true";
                        update_field('enable_order_notes', $campaign_show_notes, $campaign_id);
                    } else {
                        update_field('enable_order_notes', false, $campaign_id);
                    }*/

                    if(!empty($campaign['B2BCAMPBUDGDES']) && $campaign['B2BCAMPBUDGDES'] == "נקודות") {
                        $campaign_budgdes = 1;
                        update_field('budget_by_points', $campaign_budgdes, $campaign_id);
                    } else {
                        update_field('budget_by_points', 0, $campaign_id);
                    }

                    $categories = wp_get_post_terms($campaign_id, 'product_cat', array('fields' => 'ids'));
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            wp_remove_object_terms($campaign_id, $category, 'product_cat');
                        }
                    } 

                    //***** update acf field customer pickup details ******/

                    $branches = [];
                    if (!empty($campaign['UNIT_B2BCAMPSAL_SUBFORM'])) {
                        foreach($campaign['UNIT_B2BCAMPSAL_SUBFORM'] as $extension){

                            // Get the current value of the 'shops' field
                            $search_by_title = (string) $extension['CUSTDES'];

                            $args = array(
                                'post_type'   => 'branch',
                                'post_status' => array( 'publish', 'draft' ),
                                'title' => $search_by_title,                
                                'meta_query'  => array(
                                    array(
                                        'key'   =>'branch_customer',
                                        'value' => $customer_id
                                    )
                                )                 
                            );

                            $branch_id = 0;
                            $my_query = new \WP_Query( $args );
                            if ( $my_query->have_posts() ) {
                                while ( $my_query->have_posts() ) {
                                    $my_query->the_post();
                                    $branch_id = get_the_ID();
                                }
                            }   

                            // insert extension
                            if ($branch_id == 0) {
                                // Set the post type and taxonomy terms
                                $post_type = 'branch';
                                $taxonomy = 'minisite';
                                $minisite_term = $path; 
                                
                                // Post data
                                $branch_data = array(
                                    'post_title'    => $extension['CUSTDES'],
                                    'post_content'  => '',
                                    'post_status'   => 'publish',
                                    'post_type'     => $post_type,
                                );
                                // Insert the post
                                $branch_id = wp_insert_post($branch_data);
                            }
                            
                            //update acf field in branch
                            if ($branch_id !== 0) {
                                // Assign the minisite term to the post
                                wp_set_post_terms($branch_id, $minisite_term, $taxonomy);
                                
                                update_post_meta($branch_id, 'branch_priority_number', $extension['CUSTNAME']);
                                update_post_meta($branch_id, 'branch_priority_cstmer_number', $priority_customer);
                                update_field('branch_customer', $customer_id, $branch_id);
                                update_field('branch_address',$extension['ADDRESS'], $branch_id);
                                update_field('branch_city',$extension['STATE'], $branch_id);
                                update_field('contact_name', $extension['NAME'], $branch_id);
                                if($extension['PHONENUM'] !== null) {
                                    $phone = $extension['PHONENUM'];
                                } else {
                                    $phone = $extension['CELLPHONE'];
                                }
                                update_field('contact_phone_number', $phone, $branch_id);

                                //*** update_user_meta($user_id, 'user_branch', $branch_id);  
                            }

                            if($campaign['FROMSTORE'] !== 2) {
                                if ($branch_id !== 0) {
                                    $branches[] = $branch_id; // Set the checkbox with value - checked
                                } 
                                    
                            }                    
                        }
                    }
                    if($campaign['FROMSTORE'] == 3) {

                        // Fetch posts from the main site
                        restore_current_blog();   
                        $main_site_id = 1;
                        switch_to_blog($main_site_id);

                            $args_shop = array(
                                'post_type'   => 'shop',
                                'post_status' => 'publish',
                                'posts_per_page' => -1,                
                            );      
                            $my_query_shop = new \WP_Query( $args_shop );
                            if ( $my_query_shop->have_posts() ) {
                                while ( $my_query_shop->have_posts() ) {
                                    $my_query_shop->the_post();
                                    $shop_id = get_the_ID();
                                    $branches[] = $shop_id;
                                }
                            }

                        restore_current_blog();    
                        switch_to_blog($site_id);
                    }                        
                    
                    // Update the specific checkbox value if it exists
                    if($campaign['FROMSTORE'] == 3 || $campaign['FROMSTORE'] == 2) {
                        update_post_meta($campaign_id, 'shops', $branches);
                        update_post_meta($campaign_id, 'shipping_allow', '');
                    }

                    if($campaign['FROMSTORE'] == 1) {
                        update_post_meta($campaign_id, 'shipping_allow', 'on');
                        update_post_meta($campaign_id, 'shops', '');
                    }                   

                    //***** open kit and update the details ******/

                    //update the kit
                    if (!empty($campaign['UNIT_B2BCAMPKIT_SUBFORM'])) {
                        $arr_kits = [];
                        $one_order = [];
                        $amount_budget = [];
                        $product_to_campaign = [];
                        $product_option = [];
                        $group_code = [];
                        $requird_code = [];
                        $product_to_kit = [];
                        foreach($campaign['UNIT_B2BCAMPKIT_SUBFORM'] as $kit) {
                            $kit_name = $kit['CAMPKITDES'];
                            $args = array(
                                'post_type'   => 'kits',
                                'post_status' => 'publish',
                                'title' => $kit_name,                
                            );

                            // Set the post type and taxonomy terms
                            $post_type = 'kits';
                            $taxonomy = 'minisite';
                            $minisite_term = $path; 
                            $kit_id = 0;

                            $my_query = new \WP_Query( $args );
                            if ( $my_query->have_posts() ) {
                                while ( $my_query->have_posts() ) {
                                    $my_query->the_post();
                                    $kit_id = get_the_ID();
                                }
                            }

                            //insert_kit
                            if ($kit_id == 0) {
                                // Post data
                                $kit_data = array(
                                    'post_title'    => $kit['CAMPKITDES'],
                                    'post_content'  => '',
                                    'post_status'   => 'publish',
                                    'post_type'     => $post_type,
                                );
                                // Insert the post
                                $kit_id = wp_insert_post($kit_data);
                            }  

                            //update acf field in kit
                            if ($kit_id !== 0) {
                                // Assign the minisite term to the post
                                wp_set_post_terms($kit_id, $minisite_term, $taxonomy);
                                
                                // update_post_meta($kit_id, 'kit_number', $extension['CUSTNAME']);
                                update_field('kit_number', $kit['CAMPKITNUM'], $kit_id);
                                update_field('kit_customer', $customer_id, $kit_id);

                                //*** update_user_meta($user_id, 'user_kit', $kit_id);
                                
                            }

                            //updte meta field in user
                            /*foreach($kit['UNIT_B2BCAMPKITEMP_SUBFORM'] as $user) {

                                $search_by_email = (string) $user['EMAIL'];
                                // Arguments for the user query
                                $args = array(
                                    'search'         => '*' . esc_attr( $search_by_email ) . '*',
                                    'search_columns' => array( 'user_email' ),
                                );

                                $user_id = 0;
                                // Perform the user query
                                $user_query = new WP_User_Query( $args );

                                // Check if user(s) found
                                if ( ! empty( $user_query->results ) ) {
                                    // Assuming you want to get the ID of the first user found
                                    $user_ex = $user_query->results[0];
                                    $user_id = $user_ex->ID;
                                }
                                
                                if ($user_id !== 0) {
                                    $search_by_custdes = (string) $user['SCUSTDES'];
                                    $args = array(
                                        'post_type'   => 'branch',
                                        'post_status' => array( 'publish', 'draft' ),
                                        'title' => $search_by_custdes,                
                                        'meta_query'  => array(
                                            array(
                                                'key'   =>'branch_customer',
                                                'value' => $customer_id
                                            )
                                        )                 
                                    );
                
                                    $branch_user_id = 0;
                                    $my_query = new \WP_Query( $args );
                                    if ( $my_query->have_posts() ) {
                                        while ( $my_query->have_posts() ) {
                                            $my_query->the_post();
                                            $branch_user_id = get_the_ID();
                                        }
                                    }
                                    if($branch_user_id !== 0) {
                                        update_user_meta($user_id, 'user_customer', $customer_id);
                                        update_user_meta($user_id, 'user_branch', $branch_user_id);  
                                    }
                                    if($kit_id !== 0) {
                                    update_user_meta($user_id, 'user_kit', $kit_id);
                                    }
                                }
                            }*/

                            //open kit and update in the campign
                            if ($kit_id !== 0) {
                                $arr_kits[] = $kit_id;
                                update_post_meta($campaign_id, 'kits', $arr_kits);
                                // $aa = get_post_meta($campaign_id, 'kits');

                                $one_orders = $kit['ONEORDERS'];
                                if($one_orders === 'Y') {
                                    $one_order[$kit_id] = "on";
                                    update_post_meta($campaign_id, 'one_order_toggle', $one_order);
                                } 
                                
                                $order_notes = $kit['REMARKS'];
                                if($order_notes === 'Y') {
                                    $enable_notes[$kit_id] = "on";
                                    update_post_meta($campaign_id, 'enable_order_notes', $enable_notes);
                                }
                                
                                if(!empty($campaign['B2BCAMPBUDGDES']) && $campaign['B2BCAMPBUDGDES'] == "נקודות") {
                                    $amount_budget[$kit_id] = $kit['BUDGET'];
                                    update_post_meta($campaign_id, 'budget', $amount_budget); 
                                } else {
                                    $amount_budget[$kit_id] = $kit['BUDGET'];
                                    update_post_meta($campaign_id, 'budget', $amount_budget);
                                }

                                $groups_kit = $kit['UNIT_B2BCAMPASSIG_SUBFORM'];
                                if ($groups_kit) {
                                    foreach($groups_kit as $group) {
                                        if($group['HAKQUANT'] !== '0') {
                                            $random_ID = rand(0, 100000000000000000);
                                            $group_code[$kit_id][$random_ID] = [
                                                'name' => $group['B2BASSIGGROUPDES'],
                                                'amount' => $group['HAKQUANT'],
                                            ]; 
                                        }
                                        if($group['MUSTQUANT'] !== '0') {
                                            $random_ID = rand(0, 100000000000000000); 
                                            $requird_code[$kit_id][$random_ID] = [
                                                'name' => $group['B2BAMUSTPGDES'],
                                                'amount' => $group['MUSTQUANT'],
                                            ];
                                        }

                                        update_post_meta($campaign_id, 'groups', $group_code);
                                        update_post_meta($campaign_id, 'required_products', $requird_code);  
                                    }
                                }

                                if (!empty($campaign['UNIT_B2BCOLLECTION_SUBFORM'])) {
                                    // $categories = [];
                                    foreach($campaign['UNIT_B2BCOLLECTION_SUBFORM'] as $product_collection) {
                                        //add category
                                        $category_name = (string) $product_collection['B2BPCDES'];
                                        if(!empty($category_name )) {

                                            // Check if the term already exists
                                            $category = get_term_by('name', $category_name, 'product_cat');

                                            if (!$category) {
                                                // Term doesn't exist, so insert it
                                                $inserted_category = wp_insert_term(
                                                    $category_name,
                                                    'product_cat',
                                                    array(
                                                        // You can add additional parameters here if needed
                                                    )
                                                );
                                                if (!is_wp_error($inserted_category)) {
                                                    $category_id = $inserted_category['term_id'];
                                                } else {
                                                    // Handle error
                                                    $error_message = $inserted_category->get_error_message();
                                                }
                                            } else {
                                                $category_id = $category->term_id;
                                            }
                                        }
                                        if(!empty($category_id)) {
                                            // $categories[] = $category_id;
                                            wp_set_object_terms($campaign_id, $category_id, 'product_cat', true);                                   
                                            // update_post_meta($campaign_id, 'tax_input[product_cat]', $categories);
                                        }
                                                                
                                        $partname_coollect = (string) $product_collection['PARTNAME'];
                                        $_sku = explode('/', $partname_coollect);
                                        $search_by_value = $_sku[0] . '/' . $_sku[1];
                                        $args            = array(
                                            'post_type'   => array( 'product', 'product_variation' ),
                                            'post_status' => array( 'publish', 'draft' ),
                                            'meta_query'  => array(
                                                array(
                                                    'key'   => '_sku',
                                                    'value' => $search_by_value
                                                )
                                            )
                                        );
                                        $product_id_c      = 0;
                                        $my_query        = new \WP_Query( $args );
                                        if ( $my_query->have_posts() ) {
                                            while ( $my_query->have_posts() ) {
                                                $my_query->the_post();
                                                $product_id_c = get_the_ID();
                                            }
                                        }

                                        if($product_id_c == 0) {
                                            $args            = array(
                                                'post_type'   => array( 'product', 'product_variation' ),
                                                'post_status' => array( 'publish', 'draft' ),
                                                'meta_query'  => array(
                                                    array(
                                                        'key'   => '_sku',
                                                        'value' => $partname_coollect
                                                    )
                                                )
                                            );
                                            $my_query        = new \WP_Query( $args );
                                            if ( $my_query->have_posts() ) {
                                                while ( $my_query->have_posts() ) {
                                                    $my_query->the_post();
                                                    $product_id_c = get_the_ID();
                                                }
                                            }
                                        }

                                        if ( $product_id_c != 0 ) {
                                            if(!empty($category_id)) {
                                                $product_wp = wc_get_product($product_id_c);
                                                // Assign the product to the category
                                                wp_set_object_terms($product_id_c, $category_id, 'product_cat', true);                                   
                                                $product_wp->save();
                                            }
                                        }

                                    }
                                }

                                if (!empty($kit['UNIT_B2BCAMPKITPART_SUBFORM'])) {
                                    $data_product = [];
                                    foreach($kit['UNIT_B2BCAMPKITPART_SUBFORM'] as $product_kit) {

                                        $partname = $product_kit['PARTNAME'];
                                        $_sku= explode('/', $partname);
                                        $search_by_partname = $_sku[0] . '/' . $_sku[1];
                                        
                                        $args            = array(
                                            'post_type'   => array( 'product', 'product_variation' ),
                                            'post_status' => array( 'publish', 'draft' ),
                                            'meta_query'  => array(
                                                array(
                                                    'key'   => '_sku',
                                                    'value' => $search_by_partname
                                                )
                                            )
                                        );
                                        $product_kit_id = 0;
                                        $my_query        = new \WP_Query( $args );
                                        if ( $my_query->have_posts() ) {
                                            while ( $my_query->have_posts() ) {
                                                $my_query->the_post();
                                                $product_kit_id = get_the_ID();
                                            }
                                        }

                                        if ( $product_kit_id == 0 ) {
                                            $args            = array(
                                                'post_type'   => array( 'product', 'product_variation' ),
                                                'post_status' => array( 'publish', 'draft' ),
                                                'meta_query'  => array(
                                                    array(
                                                        'key'   => '_sku',
                                                        'value' => $partname
                                                    )
                                                )
                                            );
                                            $my_query        = new \WP_Query( $args );
                                            if ( $my_query->have_posts() ) {
                                                while ( $my_query->have_posts() ) {
                                                    $my_query->the_post();
                                                    $product_kit_id = get_the_ID();
                                                }
                                            }
                                        }

                                        if ( $product_kit_id != 0 ) {

                                            $kit_product = wc_get_product($product_kit_id);

                                            $product_to_campaign[$kit_id][] = $product_kit_id;
                                            
                                            // $product_to_campaign = array($kit_id => $product_id);
                                            $groups = get_post_meta($campaign_id, 'groups', true);
                                            foreach($groups as $kit_id_group => $assignment_products) {
                                                if($kit_id_group === $kit_id) {
                                                    foreach($assignment_products as $key_assignment => $assignment_product) {
                                                        // $assignment = $assignment_products;
                                                        if($assignment_product['name'] === $product_kit['B2BASSIGGROUPDES']) {
                                                            $assignment_group = $key_assignment;
                                                        }
                                                    }
                                                }
                                                
                                            }

                                            $required_products = get_post_meta($campaign_id, 'required_products', true);
                                            foreach($required_products as $kit_id_required => $required_items) {
                                                if($kit_id_required === $kit_id) {
                                                    foreach($required_items as $key_items => $required_item) {
                                                        // $assignment = $assignment_products;
                                                        if($required_item['name'] === $product_kit['B2BAMUSTPGDES']) {
                                                            $required_group = $key_items;
                                                        }
                                                    }
                                                }
                                                
                                            }

                                            $data_product[$product_kit_id] = [
                                                'camp_varible_img' => '',
                                                'warehouse' => $product_kit['WAREHOUSES'],
                                                'uni_simple_field' => '',
                                                'groups' => (!empty ($assignment_group)) ? $assignment_group : '',
                                                'required_products' => (!empty ($required_group)) ? $required_group : '',
                                                'price' => $product_kit['PRICE'],
                                                'points' => $product_kit['POINTS'],
                                                'order' => $product_kit['SORTINSITE'],

                                            ];

                                            // Restore the original site context
                                            restore_current_blog();

                                            $response_collection = WooAPI::instance()->makeRequest('GET', 
                                            'UNIT_B2BWEBSITES?$filter=CUSTNAME eq \''. $priority_customer .'\'&$expand=UNIT_B2BCAMPAIGN_SUBFORM($expand=UNIT_B2BCOLLECTION_SUBFORM($filter = PARTNAME eq \''. $partname .'\'; $expand=UNIT_B2BCOLLECSIZE_SUBFORM)) ', [],
                                            WooAPI::instance()->option( ' ', false ) );
                                            // check response
                                            if ($response_collection['status']) {
                                                $response_collection_data = json_decode($response_collection['body_raw'], true);      
                                                if ($response_collection_data['value'][0] > 0) {
                                                    foreach($response_collection_data['value'] as $customer_collect) {
                                                        foreach($customer_collect['UNIT_B2BCAMPAIGN_SUBFORM'] as $campign_collect) {
                                                            foreach($campign_collect['UNIT_B2BCOLLECTION_SUBFORM'] as $collection_collect) {
                                                                // Check if the product exists and if it's a variable product
                                                                if ($kit_product && $kit_product->is_type('variable')) {
                                                                    // Get the variation attribute data
                                                                    $variation_attributes = $kit_product->get_attributes();
                                                                    // $attribute_name = array_keys($variation_attributes);   
                                                                    // $attribute_name = $attribute_name[0];  
                                                                    $attribute_name = 'pa_size';  
                                                                    
                                                                    if(!empty($collection_collect['UNIT_B2BCOLLECSIZE_SUBFORM'])) {
                                                                        $array_size = [];
                                                                        foreach($collection_collect['UNIT_B2BCOLLECSIZE_SUBFORM'] as $collection_size) {
                                                                            $size = $collection_size['SIZE'];
                                                                            $array_size[] = $size;
                                                                        }
                                                                        
                                                                    }
                                                                } 
                                                                $data_product[$product_kit_id][$attribute_name] = $array_size;  
                                                            }
                                                        }

                                                    }

                                                }
                                            }
                                            switch_to_blog($site_id);

                                        }                          
                                        // error_log("data_product: " . var_export($data_product, true));
                                        $product_option[$kit_id] = $data_product;
                                    }
                                }
        
                            }
                            $jsonArray = json_encode($product_to_campaign[$kit_id]);
                            $product_to_kit[$kit_id] = $jsonArray;

                        } 
                        update_post_meta($campaign_id, 'add_product_to_campaign', $product_to_kit);
                        update_post_meta($campaign_id, 'product_option', $product_option);
                    }

                    // continue;
                }

                if ( $campaign_id !== 0 && $campaign['B2BCAMPSTATDES'] == 'פעיל' ) {

                    // update customer, kit and branch to the user
                    if (!empty($campaign['UNIT_B2BCAMPKIT_SUBFORM'])) {
                        foreach($campaign['UNIT_B2BCAMPKIT_SUBFORM'] as $kit){
                            //get the id of kit
                            $search_by_title_kit = (string) $kit['CAMPKITDES'];
                            $args = array(
                                'post_type'   => 'kits',
                                'post_status' => array( 'publish', 'draft' ),
                                'title' => $search_by_title_kit,                
                            );
                            $my_query = new \WP_Query( $args );
                            if ( $my_query->have_posts() ) {
                                while ( $my_query->have_posts() ) {
                                    $my_query->the_post();
                                    $kit_id = get_the_ID();
                                }
                            }

                            foreach($kit['UNIT_B2BCAMPKITEMP_SUBFORM'] as $user_kit){
                                //get the id of user in site
                                $search_user_by_email = (string) $user_kit['EMAIL'];
                                // Arguments for the user query
                                $args = array(
                                    'search'         => '*' . esc_attr( $search_user_by_email ) . '*',
                                    'search_columns' => array( 'user_email' ),
                                );
        
                                $user_kit_id = 0;
                                // Perform the user query
                                $user_query = new WP_User_Query( $args );
                                $users = $user_query->get_results();
                                        
                                if (!empty($users)) {
                                    foreach ($users as $user) {
                                        $user_kit_id = $user->ID;
                                        
                                        //get the id of user branch
                                        $search_by_title_extension = (string) $user_kit['SCUSTDES'];
                                        $args = array(
                                            'post_type'   => 'branch',
                                            'post_status' => 'publish',
                                            'title' => $search_by_title_extension,                
                                        );
                                        $my_query = new \WP_Query( $args );
                                        if ( $my_query->have_posts() ) {
                                            while ( $my_query->have_posts() ) {
                                                $my_query->the_post();
                                                $extension_id = get_the_ID();
                                            }
                                        }
                                    }
                                    update_user_meta($user_kit_id, 'user_customer', $customer_id);
                                    update_user_meta($user_kit_id, 'user_branch', $extension_id);  
                                    update_user_meta($user_kit_id, 'user_kit', $kit_id);
                                }
                            }
                        }
                    }
                       
                    //update the campaign to active
                    update_post_meta($customer_id, 'active_campaign', $campaign_id);
                    // continue;
                }

                if ( $campaign_id !== 0 && $campaign['B2BCAMPSTATDES'] == 'סגור' ) {

                    // update customer, kit and branch to the user
                    if (!empty($campaign['UNIT_B2BCAMPKIT_SUBFORM'])) {
                        foreach($campaign['UNIT_B2BCAMPKIT_SUBFORM'] as $kit){
                            foreach($kit['UNIT_B2BCAMPKITEMP_SUBFORM'] as $user_kit){
                                //get the id of user in site
                                $search_user_by_email = (string) $user_kit['EMAIL'];
                                // Arguments for the user query
                                $args = array(
                                    'search'         => '*' . esc_attr( $search_user_by_email ) . '*',
                                    'search_columns' => array( 'user_email' ),
                                );
        
                                $user_kit_id = 0;
                                // Perform the user query
                                $user_query = new WP_User_Query( $args );
                                $users = $user_query->get_results();
                                        
                                if (!empty($users)) {
                                    foreach ($users as $user) {
                                        $user_kit_id = $user->ID;
                                        
                                        delete_user_meta($user_kit_id, 'user_kit');
                                        delete_user_meta($user_kit_id, 'user_branch');
                                        delete_user_meta($user_kit_id, 'user_customer');
                                    }
                                }
                            }
                        }
                    }
                       
                    //update the campaign to active
                    update_post_meta($customer_id, 'active_campaign', '0');
                    continue;
                }

            } 

        }

        // Restore the original site context
        restore_current_blog();
    }    
}


function syncUserPriority() {
    $daysback = 2; // change days back to get inventory of prev days
    $stamp = mktime(1 - ($daysback * 24), 0, 0);
    $bod = date(DATE_ATOM, $stamp);
    $url_addition = '('. rawurlencode('CURDATE ge ' . $bod ) . ')';
    
    // Get all users from Priority
    $response = WooAPI::instance()->makeRequest('GET', 
    'UNIT_B2BWEBSITES?$filter=' . $url_addition . '&$expand=UNIT_B2BCAMPAIGN_SUBFORM($filter=' . $url_addition . '; $expand=UNIT_B2BCAMPKIT_SUBFORM($filter=' . $url_addition . '; $expand=UNIT_B2BCAMPKITEMP_SUBFORM($filter=' . $url_addition . '))) ', [], true  );
    // check response
    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);      
        if ($response_data['value'][0] > 0) {
            foreach($response_data['value'] as $customer_array) {
                $priority_customer = $customer_array['CUSTNAME'];
                foreach($customer_array['UNIT_B2BCAMPAIGN_SUBFORM'] as $campaign_cutomer)
                {
                    create_user_unidress($campaign_cutomer['UNIT_B2BCAMPKIT_SUBFORM'], $priority_customer);
                }
            }

        } else {
            exit(json_encode(['status' => 0, 'msg' => 'Error Sync customers Priority']));
            $subj = 'check sync customers';
            wp_mail( 'margalit.t@simplyct.co.il', $subj, implode(" ",$response) );
        }
    }

}

add_action('syncUserPriority_hook', 'syncUserPriority');

if (!wp_next_scheduled('syncUserPriority_hook')) {

    $res = wp_schedule_event(time(), 'daily', 'syncUserPriority_hook');

}

function create_user_unidress($kits_cutomer, $priority_customer) {

    $sites = get_sites();
    foreach ($sites as $site) {
        $site_id = $site->blog_id;
        $path = $site->path;

        //search the customer's website by customer number and directs the additions in the code to him 
        $args = array(
            'post_type'   => 'customers',
            'post_status' => array( 'publish', 'draft' ),
            'posts_per_page' => 1,
            'meta_query'  => array(
                array(
                    'key'   =>'priority_customer_number',
                    'value' => $priority_customer
                )
            )                
        );
        switch_to_blog($site_id);
        $this_site = 0;
        $my_query = new \WP_Query( $args );
        if ( $my_query->have_posts() ) {
            while ( $my_query->have_posts() ) {
                $my_query->the_post();
                $this_site = $site_id;
                $customer_name = get_the_title();
                $customer_id = get_the_ID();
            }
            // wp_reset_postdata();
        }

        if ($this_site !== 0) {
            foreach($kits_cutomer as $kit) {
                foreach($kit['UNIT_B2BCAMPKITEMP_SUBFORM'] as $user) {
                    if($user['INACTIVE'] !== "Y") {
                        $search_by_email = (string) $user['EMAIL'];
                        // Arguments for the user query
                        $args = array(
                            'search'         => '*' . esc_attr( $search_by_email ) . '*',
                            'search_columns' => array( 'user_email' ),
                        );

                        $user_id = 0;
                        // Perform the user query
                        $user_query = new WP_User_Query( $args );

                        // Check if user(s) found
                        if ( ! empty( $user_query->results ) ) {
                            // Assuming you want to get the ID of the first user found
                            $user_ex = $user_query->results[0];
                            $user_id = $user_ex->ID;
                        }
                    
                        // insert extension
                        if ($user_id == 0) {
                            //user details
                            $user_data = array(
                                'user_email' => $user['EMAIL'],
                                'user_login' => $user['B2BUSER'],
                                'user_pass' => $user['B2BPASS'],
                                'first_name' => $user['FIRSTNAME'], 
                                'last_name' => $user['LASTNAME'], 
                                'user_nickname' => $user['SCUSTNAME'],
                                'display_name' => $user['CUSTDES'],
                                'role' => 'customer'
                            );
                            
                            // Create the user
                            $user_id = wp_insert_user( $user_data );

                            if(is_wp_error( $user_id )) {
                                // Handle the error if user creation failed
                                $error_message = $user_id->get_error_message();
                                echo 'User creation failed: ' . $error_message;
                                continue;
                            }
                        }

                        /*$username = $user['CUSTDES'];
                        $email = $user['EMAIL'];
                        $password = $user['EMAIL']

                        // Create the user
                        $user_id = wpmu_create_user( $username, $password, $email );*/

                        // Check if user creation was successful
                        if ( $user_id !== 0 ) {
                            // Add the user to the specified minisite
                            add_user_to_blog( $this_site, $user_id, 'customer' ); // Change 'customer' to the appropriate role
                        }
                        else {
                            // Handle the error if user creation failed
                            $error_message = $user_id->get_error_message();
                            echo 'User creation failed: ' . $error_message;
                        }

                        if ($user_id !== 0) {

                            //update post meta field in user
                            update_user_meta($user_id, 'priority_customer_number', $user['MYNUM']);
                            update_user_meta($user_id, 'billing_company', $user['SCUSTDES']);
                            update_user_meta($user_id, 'billing_phone', $user['PHONE']);                          
                            update_user_meta($user_id, 'unidress_budget', $user['BUDGET']);
                        }
                    } 
                    if($user['INACTIVE'] == "Y") {
                        delete_user_meta($user_id, 'user_kit');
                        delete_user_meta($user_id, 'user_branch');
                        delete_user_meta($user_id, 'user_customer');
                        if ( ! is_wp_error( $user_id ) ) {
                            remove_user_from_blog($user_id, $this_site);
                        }
                    }
                    

                }


            }
        }
    }

}

/**
 * sync items simple & variation from priority
 */

function syncItemsPriority() {
    $priority_version = (float) WooAPI::instance()->option( 'priority-version' );
    // config
    $raw_option     = WooAPI::instance()->option( 'sync_items_priority_config' );
    $raw_option     = str_replace( array( "\n", "\t", "\r" ), '', $raw_option );
    $config         = json_decode( stripslashes( $raw_option ) );
    $is_update_products  = true;
    // $daysback            = ( ! empty( (int) $config->days_back ) ? $config->days_back : 1 );
    $daysback            = "10";
    $url_addition_config = ( ! empty( $config->additional_url ) ? $config->additional_url : '' );
    $stamp          = mktime( 0 - $daysback * 24, 0, 0 );
    $bod            = date( DATE_ATOM, $stamp );
    $date_filter    = 'CURDATE ge ' . urlencode( $bod );
    $data['select'] = 'WEBSITE,CAMPAIGNNUM,CAMPAIGDES,PARTNAME,PARTDES,PRICE,OSPART,B2BASSIGGROUPCODE,B2BASSIGGROUPDES,B2BPCCODE,B2BPCDES,INACTIVE,NOTINBAL,B2BCAMPAIGN,PART';
    $data['expand'] = '$expand= ';
    $item_variation = [];

    $response = WooAPI::instance()->makeRequest( 'GET',
        'UNIT_B2BCOLLECTIONF?$select=' . $data['select'] . '&$filter=' . $date_filter  . $url_addition_config .
        '', [], true );
    

    // check response status
    if ($response['status']) {
        $response_data = json_decode($response['body_raw'], true);
        try {
            foreach ( $response_data['value'] as $item ) {
                //check if this is a variation product, if not return to syncItemsPriority
                $simple_product = $item['OSPART'];
                if ($simple_product !== 'Y') {
                    $site_item =  $item['WEBSITE']; 

                    // Check if the key exists in the parent array, if not, initialize it as an empty array
                    if (!isset($item_variation[$site_item])) {
                        $item_variation[$site_item] =  array();
                    }
                    // Append the new item to the nested array
                    $item_variation[$site_item][] = $item;

                    continue;
                }
                // $item = apply_filters('simply_syncItemsPriorityAdapt_unidress', $item);

                // Get all site in the multisite network
                $sites = get_sites();

                // Loop through each site
                foreach ( $sites as $site ) {
                    $site_id = $site->blog_id;
                    // if site_id equal to 1 which is the main site, exit
                    if ($site_id == "1") {
                        continue;
                    }

                    if ($site_id !== "1")  {

                        $path = $site->domain;
                        $url_site = $path . $site->path;

                        $url_item = $item['WEBSITE'];
                        $url = explode('/', $url_item);
                        $utl_site_lochally = 'wordpressmu-948345-3693715.cloudwaysapps.com/';
                        $new_url = $utl_site_lochally . $url['3'] .'/';

                        if ($new_url !== $url_site) {
                            continue;
                        }
                        if ($new_url == $url_site) {
                            // Switch to the current site
                            switch_to_blog( $site_id );

                            $data = [
                                'post_author' => 1,
                                //'post_content' =>  $content,
                                'post_status' => 'publish',
                                'post_title'  => $item['PARTDES'],
                                'post_parent' => '',
                                'post_type'   => 'product',
                            ];
                            // if product exsits, update
                            $search_by_value = (string) $item['PARTNAME'];
                            $args            = array(
                                'post_type'   => array( 'product', 'product_variation' ),
                                'post_status' => array( 'publish', 'draft' ),
                                'meta_query'  => array(
                                    array(
                                        'key'   => '_sku',
                                        'value' => $search_by_value
                                    )
                                )
                            );
                            $product_id      = 0;
                            $my_query        = new \WP_Query( $args );
                            if ( $my_query->have_posts() ) {
                                while ( $my_query->have_posts() ) {
                                    $my_query->the_post();
                                    $product_id = get_the_ID();
                                }
                            }
                            // if product variation skip
                            if ( $product_id != 0 ) {
                                $_product = wc_get_product( $product_id );
                                if ( ! $_product->is_type( 'simple' ) ) {
                                    continue;
                                }
                            }
                            // update product
                            if ( $product_id != 0 ) {
                                $data['ID'] = $product_id;
                                $_product->set_status(WooAPI::instance()->option('item_status'));
                                $_product->save();
                                // Update post
                                $id = $product_id;
                                global $wpdb;
                                // @codingStandardsIgnoreStart
                                $wpdb->query(
                                    $wpdb->prepare(
                                        "
                                UPDATE $wpdb->posts
                                SET post_title = '%s'
                                WHERE ID = '%s'
                                ",
                                        $item['PARTDES'],
                                        $id
                                    )
                                );
                                update_post_meta( $id, '_sku', $search_by_value );
                                update_post_meta( $id, '_stock_status', 'instock' );
                                update_post_meta( $id, '_regular_price', $item['PRICE'] );
                                update_post_meta( $id, '_price', $item['PRICE'] );
                            
                            } else {
                                // Insert product
                                $id = wp_insert_post( $data );
                                if ( $id ) {
                                    update_post_meta( $id, '_sku', $search_by_value );
                                    update_post_meta( $id, '_stock_status', 'instock' );
                                    update_post_meta( $id, '_regular_price', $item['PRICE'] );
                                    update_post_meta( $id, '_price', $item['PRICE'] );

                                }
                            }

                            // And finally (optionally if needed)
                            wc_delete_product_transients( $id ); // Clear/refresh the variation cache
                                
                            // sync image
                            /*
                            $is_load_image = json_decode( $config->is_load_image );
                            if ( false == $is_load_image ) {
                                continue;
                            }
                            $sku          = $item[ $search_field ];
                            $is_has_image = get_the_post_thumbnail_url( $id );
                            if ( WooAPI::instance()->option( 'update_image' ) == true || ! get_the_post_thumbnail_url( $id ) ) {
                                $file_     = WooAPI::instance()->load_image( $item['EXTFILENAME'] ?? '', $image_base_url, $priority_version, $sku, $search_field );
                                $attach_id = $file_[0];
                                $file      = $file_[1];
                                if ( empty( $file ) ) {
                                    continue;
                                }
                                include $file;
                                require_once( ABSPATH . '/wp-admin/includes/image.php' );
                                $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
                                wp_update_attachment_metadata( $attach_id, $attach_data );
                                set_post_thumbnail( $id, $attach_id );
                            }*/
                            // Restore the current site
                            restore_current_blog();
                        }
                    }
                }

            }
            //if you want customized syncItemsPriority, activate the function
            if(function_exists('syncItemsPriorityVariationUnidress')) {
                syncItemsPriorityVariationUnidress($item_variation);
            }
            // do_action('syncItemsPriorityAdapt');
        } catch (Exception $e) {
            // Exception handling code
            echo "Exception caught: " . $e->getMessage();
         }
        // add timestamp
        // WooAPI::instance()->updateOption('items_priority_update', time());
    } else {
        $subj = 'check sync item';
        wp_mail( 'margalit.t@simplyct.co.il', $subj, implode(" ",$response) );
    }

    return $response;
}

add_action('syncItemsPriority_hook', 'syncItemsPriority');

if (!wp_next_scheduled('syncItemsPriority_hook')) {

    $res = wp_schedule_event(time(), 'daily', 'syncItemsPriority_hook');

}

function syncItemsPriorityVariationUnidress($item_variation) 
{ 
    // error_log('sites: ' . count($item_variation));
    $priority_version = (float)WooAPI::instance()->option('priority-version');
    // config
    $raw_option = WooAPI::instance()->option('sync_items_priority_config');
    $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
    $config = json_decode(stripslashes($raw_option));
    $is_update_products  = ( ! empty( $config->is_update_products ) ? $config->is_update_products : false );

    $sites_id = get_sites(array('fields' => 'ids'));
    unset($sites_id[0]);

    foreach($sites_id as $s_id) {
        switch_to_blog( $s_id );

        
        // if($s_id !== 1) {

            $attribute = 'size';
            if ($attribute) {

                // Add the attribute if it doesn't exist
                $attribute_id = wc_attribute_taxonomy_id_by_name($attribute);
                // error_log("Processed site ID: " . $attribute_id . ' ' . $s_id);

                if (!$attribute_id) {
                    $attribute_array = wc_create_attribute(array(
                        'name' => $attribute,
                        'slug' => 'pa_' . sanitize_title($attribute ),
                        'type' => 'text', // Change this if your attribute type is different
                        'order_by' => '',
                    ));
                }
            }
            restore_current_blog();
            // }
    }
    $sites_var = get_sites();
    unset($sites_var[0]);

    // Loop through each site
    foreach ( $sites_var as $site_var ) {
        $site_id = $site_var->blog_id;

        $path = $site_var->domain;
        $url_site = $path . $site_var->path; 
        // error_log("Processing site ID: " . $site_id);

        $site_wp = '';                                      

        foreach ($item_variation as $site_name => $site_item) {

            $url_var = explode('/', $site_name);
            $utl_site_test = 'wordpressmu-948345-3693715.cloudwaysapps.com/';
            $site_product = $utl_site_test . $url_var['3'] .'/';

            if ($site_product == $url_site) {
                $site_wp = $site_id;
                switch_to_blog( $site_wp );
            

        // // Loop through each site
        // foreach ( $sites_var as $site_var ) {
        //     $site_id = $site_var->blog_id;
            // Log the current site ID
            

            // // if site_id is 1, skip to the next iteration
            // if ($site_id == 1) {
            //     continue;
            // } 

            

            // if ($site_product !== $url_site) {
            //     continue;
            // }


                $parents = [];
                $childrens = [];
                if ($site_wp !== 1) {
                    foreach ($site_item as $item ) {   

                        $variation_field =  $item['PARTNAME']; //84539Z/F03/100EE
                        $variation_field_title = $item['PARTDES'];

                        $_sku= explode('/', $variation_field);
                        $parent_sku = $_sku[0] . '/' . $_sku[1];

                        $show_in_web = (empty($item['INACTIVE'])) ? 'Y' : '';
                        
                        if ($variation_field !== '-') {
                            $search_by_value = (string)$parent_sku;           
                            $attribute = [];
                            $attributes = [];

                            //add color/size attribute
                            $color_size = end($_sku);
                            $size_attr = substr($color_size, 3);
                            $attribute['size'] = $size_attr;
                            $item['attributes']= $attribute;
                            // $item = apply_filters('simply_ItemsAtrrVariation', $item);
                            $attributes = $item['attributes'];

                            //check if WooCommerce Tax Settings are set
                            $set_tax = get_option('woocommerce_calc_taxes');
                            if ($attributes) {
                                $price =  $item['PRICE'];

                                if (isset($item['PARTTEXT_SUBFORM'])) {
                                    foreach ($item['PARTTEXT_SUBFORM'] as $text) {
                                        $clean_text = preg_replace('/<style>.*?<\/style>/s', '', $text);
                                        $parents[$search_by_value]['content'] .= $clean_text;
                                    }
                                }
                                $parents[$search_by_value] = [
                                    'sku' => $search_by_value ,
                                    'title' => $variation_field_title,
                                    'stock' => 'instock',
                                    'variation' => [],
                                    'regular_price' => (!empty ($price)) ? $price : '',
                                    'post_content' => '',
                                    'show_in_web' => 'Y',
                                    'web' => $site_product
                                ];

                                /*if (!empty($show_in_web)) {
                                    $parents[$search_by_value][$show_in_web] = $item[$show_in_web];
                                }*/
                                $childrens[$search_by_value][$variation_field] = [
                                    'sku' => $variation_field,
                                    'regular_price' => (!empty ($price)) ? $price : '',
                                    'stock' => 'instock',
                                    'parent_title' => '',
                                    'title' => $item['PARTDES'],
                                    'stock_status' => 'instock',
                                    'image' => '',
                                    'categories' => [ ],
                                    'attributes' => $attributes,
                                    'show_in_web' => $show_in_web
                                ];
                                /*if ($show_front != null) {
                                    $childrens[$search_by_value][$variation_field]['show_front'] = $item[$show_front];
                                }*/
                            }
                        }
                    }
                           
                    foreach ($parents as $partname => $value) {
                        if (count($childrens[$partname])) {
                            $parents[$partname]['categories'] = end($childrens[$partname])['categories'];
                            // $parents[$partname]['tags'] = end($childrens[$partname])['tags'];
                            $parents[$partname]['variation'] = $childrens[$partname];
                            $parents[$partname]['title'] = $parents[$partname]['title'];
                            foreach ($childrens[$partname] as $children) {
                                foreach ($children['attributes'] as $attribute => $attribute_value) {
                                    if ($attributes) {
                                        // Add the attribute if it doesn't exist
                                        $attribute_id = wc_attribute_taxonomy_id_by_name($attribute);
                                        if (!$attribute_id) {
                                            $attribute_array = wc_create_attribute(array(
                                                'name' => $attribute,
                                                'slug' => 'pa_' . sanitize_title($attribute ),
                                                'type' => 'text', // Change this if your attribute type is different
                                                'order_by' => '',
                                            ));
                                        }

                                        // Ensure the attribute is registered
                                        $attribute_taxonomy_name = wc_attribute_taxonomy_name(sanitize_title($attribute));
                                        register_taxonomy($attribute_taxonomy_name, apply_filters('woocommerce_taxonomy_objects_' . $attribute_taxonomy_name, array('product')));

                                        if (!empty($parents[$partname]['attributes'][$attribute])) {
                                            if (!in_array($attribute_value, $parents[$partname]['attributes'][$attribute]))
                                                $parents[$partname]['attributes'][$attribute][] = $attribute_value;
                                        } else {
                                            $parents[$partname]['attributes'][$attribute][] = $attribute_value;
                                        }

                                        // Add terms for the attribute
                                        foreach ($parents[$partname]['attributes'][$attribute] as $term_value) {
                                            // Check if the term exists before inserting
                                            if (!term_exists($term_value, $attribute_taxonomy_name)) {
                                                wp_insert_term($term_value, $attribute_taxonomy_name, array('slug' => sanitize_title($term_value)));
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            unset($parents[$partname]);
                        }
                    }
                    restore_current_blog();

                    if ($parents) {
                        // Switch to the current site
                        switch_to_blog( $site_id );
                        foreach ($parents as $sku_parent => $parent) {                

                            $id = create_product_variable(array(
                                'author' => '', // optional
                                'title' => $parent['title'],
                                'content' => $parent['post_content'],                           
                                'excerpt' => '',
                                'regular_price' => (!empty($parent['regular_price'])) ? $parent['regular_price'] : '', // product regular price
                                'sale_price' => '', // product sale price (optional)
                                'stock' => (!empty($parent['stock'])) ? $parent['stock'] : '', // Set a minimal stock quantity
                                'image_id' => (!empty($attach_id_parent) && $attach_id_parent != 0) ? $attach_id_parent : '', // optional
                                'image_file' => (!empty($file_name_parent)) ? $file_name_parent : '', // optional
                                'gallery_ids' => array(), // optional
                                'sku' => $sku_parent, // optional
                                'tax_class' => '', // optional
                                'weight' => '', // optional
                                // For NEW attributes/values use NAMES (not slugs)
                                'tags' => '',
                                'attributes' => $parent['attributes'],
                                'categories' => $parent['categories'],
                                'status' => 'publish',
                                'show_in_web' => !empty( $parent['show_in_web']) ?  $parent['show_in_web'] : '',
                                'is_update_products' => $is_update_products,
                                // 'shipping' => $parent_data['shipping'] != '' ? $parent_data['shipping'] : ''
                            ));


                            $parents[$sku_parent]['product_id'] = $id;
                            foreach ($parent['variation'] as $sku_children => $children) {
                                // The variation data
                                //sync image
                                /*if (true == $is_load_image) {
                                    $file = WooAPI::instance()->load_image('', $image_base_url, $priority_version, $sku_children, $search_field);
                                    $attach_id = $file[0];
                                    $file_name = $file[1];
                                }*/
                                $variation_data = array(
                                    'attributes' => $children['attributes'],
                                    'sku' => $sku_children,
                                    'regular_price' => !empty($children['regular_price']) ? ($children['regular_price']) : '',
                                    'product_code' => !empty($children['product_code']) ? $children['product_code'] : '',
                                    'sale_price' => '',
                                    'content' => '',
                                    'stock' => $children['stock'],
                                    'stock_status' => $children['stock_status'],
                                    'image_id' => (!empty($attach_id) && $attach_id != 0) ? $attach_id : '', // optional
                                    'image_file' => (!empty($file_name)) ? $file_name : '', // optional
                                    'show_front' => '',
                                    'show_in_web' => $children['show_in_web'],
                                    'is_update_products' => $is_update_products,
                                );
                                // The function to be run
                                $cc = create_product_variation($id, $variation_data);
                                // update ACFs
                            }
                            unset($parents[$sku_parent]['variation']);
                            $var_product = wc_get_product($id);
                            $var_product->set_stock_status('instock'); 
                            $var_product->save();
                        }
                        restore_current_blog();
                    } 
                }
                break;
            }           
        }       
    }
}


/*add_filter('simply_ItemsAtrrVariation', 'select_size_by_sku');
function select_size_by_sku($item) {
    $sku = $item['PARTNAME'];
    $sku_attr = explode('/', $sku);
    $color_attr = end($sku_attr);
    $attribute['size'] = $color_attr;
    $item['attributes']= $attribute;
    return $item;
}*/

// Add new columns to the Kits post type
add_filter('manage_kits_posts_columns', 'set_custom_edit_kits_columns');
function set_custom_edit_kits_columns($columns) {
    $columns['kit_number'] = __('מספר הקיט');
    return $columns;
}

// Add custom column content for Kits post type
add_action('manage_kits_posts_custom_column', 'custom_kits_column', 10, 2);
function custom_kits_column($column, $post_id) {
    switch ($column) {
        case 'kit_number':
            $number = get_post_meta($post_id, 'kit_number', true);
            echo $number;
            break;

    }
}
// Add new columns to the campaign post type
add_filter('manage_campaign_posts_columns', 'set_custom_edit_campaigns_columns');
function set_custom_edit_campaigns_columns($columns) {
    $columns['campaign_number'] = __('מספר הקמפיין');
    return $columns;
}

// Add custom column content for Kits post type
add_action('manage_campaign_posts_custom_column', 'custom_campaigns_column', 10, 2);
function custom_campaigns_column($column, $post_id) {
    switch ($column) {
        case 'campaign_number':
            $number = get_post_meta($post_id, 'campaign_number', true);
            echo $number;
            break;

    }
}

/*** 
    add Priority order status to orders page
*/  

// Checking if woocommerce custom order table is enabled
$data_storage_order = get_option('woocommerce_custom_orders_table_enabled');
$wc_orders_columns_hook = ($data_storage_order == 'yes') ? 'manage_woocommerce_page_wc-orders_columns' : 'manage_edit-shop_order_columns';
$wc_orders_custom_column_hook = ($data_storage_order == 'yes') ? 'manage_woocommerce_page_wc-orders_custom_column' : 'manage_shop_order_posts_custom_column';

// ADDING A CUSTOM COLUMN TITLE TO ADMIN ORDER LIST
add_filter($wc_orders_columns_hook,
    function ($columns) {
        // Set "Actions" column after the new colum
		$action_column = $columns['order_actions'] ?? ''; // Set the title in a variable
		unset( $columns['order_actions'] ); // remove  "Actions" column
        unset($columns['order_post']); // remove  "Actions" column

		//add the new column "Status"
		$columns['order_priority_status'] = '<span>' . __( 'Priority Status', 'p18w' ) . '</span>'; // title

		//add the new column "Priority Order"
		$columns['order_priority_number'] = '<span>' . __( 'Priority Number', 'p18w' ) . '</span>'; // title

		//add the new column "Branch Name"
		$columns['branch_title'] = '<span>' . __( 'Branch Title', 'p18w' ) . '</span>'; // title

		//add the new column "Priority Customer Number"
		$columns['customer_number'] = '<span>' . __( 'Priority Customer Number', 'p18w' ) . '</span>'; // title

		//add the new column "post to Priority"
		$columns['order_post_unidress'] = '<span>' . __( 'Post to Priority', 'p18w' ) . '</span>'; // title

		if ( $_GET["debug1"] ) {
			echo "FILTER";
			die();
		}

        // Set back "Actions" column
		$columns['order_actions'] = $action_column;

		return $columns;
    }, 
999);

// ADDING THE DATA FOR EACH ORDERS BY "Platform" COLUMN
add_action($wc_orders_custom_column_hook,
    function ($column, $post_id) {
        
        if (is_array($post_id)) {
            $post_id = $post_id['id'];
        } elseif (is_object($post_id)) {
            $post_id = $post_id->get_id();
        } 

        // HERE get the data from your custom field (set the correct meta key below)
        $order         = wc_get_order( $post_id );
        $user          = $order->get_user();
        $customer_id   = get_user_meta( $user->ID, 'user_customer', true );
        $customer_meta = get_post_meta( $customer_id );
        update_post_meta( $post_id, 'priority_customer_number', get_post_meta( $customer_id, 'priority_customer_number', true ) );
        $customer_number = get_post_meta( $post_id, 'priority_customer_number', true );
        $branch_id       = get_user_meta( $user->ID, 'user_branch', true );
        $branch_title    = $branch_id > 0 ? get_the_title( $branch_id ) : 'N/A';
        update_post_meta( $post_id, 'branch_title', $branch_title );
        $branch_title = get_post_meta( $post_id, 'branch_title', true );

        // $status       = get_post_meta( $post_id, 'priority_status', true );
        // $order_number = get_post_meta( $post_id, 'priority_ordnumber', true );
        $status = $order->get_meta( 'priority_order_status', true );
        $order_number = $order->get_meta( 'priority_order_number', true );
        if ( empty( $status ) ) {
            $status = '';
        }
        if ( strlen( $status ) > 15 ) {
            $status = '<div class="tooltip">Error<span class="tooltiptext">' . $status . '</span></div>';
        }
        if ( empty( $order_number ) ) {
            $order_number = '';
        }

        switch ( $column ) {
            case 'order_priority_status':
                echo $status;
                break;
            case 'branch_title' :
                echo '<span>' . $branch_title . '</span>'; // display the data
                break;
            case 'customer_number' :
                echo '<span>' . $customer_number . '</span>'; // display the data
                break;
            case 'order_priority_number':
                echo '<span>' . $order_number . '</span>'; // display the data
                break;

            case 'order_post_unidress':
                $url = 'admin.php?page=priority-woocommerce-api&tab=post_order&ordunidress=' . $post_id;
                echo '<span><a href=' . $url . '>Re Post</a></span>'; // display the data
                break;
        }
    }, 
999, 2);

/*add_filter('manage_edit-shop_order_columns',
    function ($columns) {
        // Set "Actions" column after the new colum
        $action_column = $columns['order_actions']; // Set the title in a variable
        unset($columns['order_actions']); // remove  "Actions" column
        unset($columns['order_post']); // remove  "Actions" column

        // add the Priority order number
        $columns['priority_order_number'] = '<span>' . __('Priority Order', 'p18w') . '</span>'; // title
        $columns['priority_order_status'] = '<span>' . __('Priority Order Status', 'p18w') . '</span>'; // title
        //add the new column "post to Priority"
        $columns['order_post_unidress'] = '<span>' . __('Post to Priority', 'p18w') . '</span>'; // title
        // $columns['send_to_priority'] = __( 'Send to Priority', 'textdomain' );

        // Set back "Actions" column
        $columns['order_actions'] = $action_column;

        return $columns;
    }, 999);*/




/*add_action('manage_shop_order_posts_custom_column',
    function ($column, $post_id) {

        if (is_object($post_id)) {
            $post_id = $post_id>get_id();  
        }  
        // HERE get the data from your custom field (set the correct meta key below)
        $order_status = get_post_meta($post_id, 'priority_order_status', true);
        $order_number = get_post_meta($post_id, 'priority_order_number', true);
        if (empty($order_status)) $order_status = '';
        if (strlen($order_status) > 25) $order_status = '<div class="tooltip">Error<span class="tooltiptext">' . $order_status . '</span></div>';
        if (empty($order_number)) $order_number = '';

        switch ($column) {
            // order
            case 'priority_order_status' :
                echo $order_status;
                break;
            case 'priority_order_number' :
                echo '<span>' . $order_number . '</span>'; // display the data
                break;
            // post order to API, using GET and
            case 'order_post_unidress' :
                $url = 'admin.php?page=priority-woocommerce-api&tab=post_order&ord=' . $post_id;
                echo '<span><a href=' . $url . '>' . __('Re Post', 'p18w') . '</a></span>'; // display the data
                break;
            // case 'send_to_priority' :
            //     echo '<label class="send-to-priority"></label>';
            //     echo '<input type="checkbox" class="send-to-priority-checkbox" value="' . esc_attr( $post_id ) . '">';
            //     break;
        }
    }, 
999, 2);*/

//sync custom order to priority
function syncOrderCustomUnidress($id, $debug = false)
{
    $order = new \WC_Order($id);
    //  add data for unidress

    $user = $order->get_user();
    $user_id = $order->get_user_id();
    $order_user = get_userdata($user_id); //$user_id is passed as a parameter

    $customer_id =  get_user_meta($user_id, 'user_customer')[0];
    // $department_id =  get_user_meta($user_id, 'user_department')[0];

    // Temporary for unidress workers (AUAY-240).
    if (! class_exists('Unidress_Order')) require_once ABSPATH . '/wp-content/plugins/unidress/includes/class-unidress-order.php';
    $Order = new \Unidress_Order($id);
    $branch_id =  $Order->getBranchId();

    $customer_name = get_the_title($customer_id);
    $priority_customer_number =  get_post_meta($customer_id)['priority_customer_number'][0];
    // post Priority customer number per branch 
    $branch_priority_customer_number =  get_post_meta($branch_id)['branch_priority_cstmer_number'][0] ?? '';
    if (!empty($branch_priority_customer_number)) {
        $priority_customer_number = $branch_priority_customer_number;
    }
    /***/
    $customer_type = get_post_meta($customer_id)['customer_type'][0];
    // $priority_dep_number = get_post_meta($department_id)['department_number'][0];
    $priority_branch_number = get_post_meta($branch_id)['branch_priority_number'][0];

    $user_department = get_user_meta($user_id, 'user_department')[0];
    $active_campain = get_Post_meta($customer_id, 'active_campaign')[0];

    /* get the due date */
    $campaign_duedate = get_Post_meta($active_campain, 'order_due_date')[0];
    $order_date =  new \DateTime($order->order_date);
    $no_of_days_to_due_date = (int)get_post_meta($active_campain, 'no_of_days_to_due_date')[0];
    $order_duedate = $order_date;
    $order_duedate->modify('+' . $no_of_days_to_due_date . ' days');
    $dayOfWeek = date('w', strtotime($order_duedate->format('Y-m-d')));
    if ($dayOfWeek == 5) {
        $order_duedate->modify('+2 days');
    }
    if ($dayOfWeek == 6) {
        $order_duedate->modify('+1 days');
    }
    if ($no_of_days_to_due_date != 0) {
        $campaign_duedate = $order_duedate->format('Y-m-d');
    }
    /**/
    // $user = get_user_by('id', $user_id);
    $username = $user->user_login;
    $user_kit = get_user_meta($user_id, 'user_kit',true);
    $campain_kits = get_post_meta($active_campain, 'product_option')[0];
    $is_budget_by_points = get_post_meta($active_campain, 'budget_by_points')[0];

    $order_shop_id = get_post_meta($id, 'unidress_shipping')[0];
    $shop_address =  get_post_meta($order_shop_id, 'address')[0] ?? '';
    $contact_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $contact_phone_number = $order->get_billing_phone();

    if (!isset(get_post_meta($order_shop_id, 'address')[0])) {
        $shop_address =  get_post_meta($branch_id, 'branch_address')[0];
        $shop_city =  get_post_meta($branch_id, 'branch_city')[0];
        $contact_name =  get_post_meta($branch_id, 'contact_name')[0];
        $contact_phone_number = get_post_meta($branch_id, 'contact_phone_number')[0];
    }

    if ($order->get_customer_id()) {
        $meta = get_user_meta($order->get_customer_id());
        $cust_number = ($meta['priority_customer_number']) ? $meta['priority_customer_number'][0] : WooAPI::instance()->option('walkin_number');
    } else {
        $cust_number = WooAPI::instance()->option('walkin_number');
    }

    $data = [
        'CUSTNAME' => $priority_customer_number,
        //'CDES'     => ($meta['priority_customer_number']) ? '' : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'CURDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
        'REFERENCE'  => $order->get_order_number(),
        'DCODE' => 'web', // $priority_dep_number,  this is the site in Priority
        'DETAILS' => $user_department,
        'UNI_SCUSTNAME' => $priority_branch_number,
        'UNI_ORDTYPE'           => 'B',
        'UNI_DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
        'UFLR_ORDERRCVCODE' => '8'
    ];

    // order comments
    $order_comment_array = explode("\n", $order->get_customer_note());
    /* foreach($order_comment_array as $comment){
        $data['ORDERSTEXT_SUBFORM'][] = [
             'TEXT' =>preg_replace('/(\v|\s)+/', ' ',$comment),
            ];
    }*/

    // shipping
    $shipping_data = [
        'NAME'        => $contact_name,
        'CUSTDES'     => $customer_name,  //$order_user->user_firstname . ' ' . $order_user->user_lastname,
        'PHONENUM'    => $contact_phone_number,
        'ADDRESS'     => $shop_address,
        'STATE'       => !empty($shop_city) ? $shop_city : '',
        // 'COUNTRYNAME' => $order->get_shipping_country(),
        'COUNTRYNAME' => '',
        'ZIP'         => $order->get_shipping_postcode(),
    ];

    // add second address if entered
    if (!empty($order->get_shipping_address_2())) {
        $shipping_data['ADDRESS2'] = $order->get_shipping_address_2();
    }

    $data['SHIPTO2_SUBFORM'] = $shipping_data;

    // get shipping id
    $shipping_method    = $order->get_shipping_methods();
    $shipping_method    = array_shift($shipping_method);
    // $shipping_method_id = str_replace(':', '_', $shipping_method['method_id']);

    // get parameters
    $params = [];
    /*
    foreach(\CuttingArt\CTA::getParameters() as $parameter) {
        $params[$parameter->name] = $parameter->priority_id;
    }*/

    // get ordered items
    foreach ($order->get_items() as $item) {

        $product = $item->get_product();

        $parameters = [];

        // get tax
        // Initializing variables
        $tax_items_labels   = array(); // The tax labels by $rate Ids
        $tax_label = 0.0; // The total VAT by order line
        $taxes = $item->get_taxes();
        // Loop through taxes array to get the right label
        foreach ($taxes['subtotal'] as $rate_id => $tax) {
            $tax_label = +$tax; // <== Here the line item tax label
        }

        // get meta
        foreach ($item->get_meta_data() as $meta) {

            if (isset($params[$meta->key])) {
                $parameters[$params[$meta->key]] = $meta->value;
            }
        }

        if ($product) {

            /*start T151*/
            $new_data = [];

            $item_meta = wc_get_order_item_meta($item->get_id(), '_tmcartepo_data');

            if ($item_meta && is_array($item_meta)) {
                foreach ($item_meta as $tm_item) {
                    $new_data[] = [
                        'SPEC' => addslashes($tm_item['name']),
                        'VALUE' => htmlspecialchars(addslashes($tm_item['value']))
                    ];
                }
            }
            /* set the kit price in case by points */
            if ($is_budget_by_points) {

                $price = (float)$product->get_regular_price();;
                $order_item = $item['product_id'];
                foreach ($campain_kits as $key => $value) {
                    if ($key == $user_kit) {
                        foreach ($value as $product_id => $product_option) {
                            if ($order_item === $product_id && !is_null($product_option['price'])) {
                                $price =  (float)$product_option['price'];
                            }
                        }
                    } else { }
                }
            } else {
                $price = (float)$item->get_subtotal() / $item->get_quantity();
            }


            /* get the warehouse of the item in collection */
            $item_warehouse = '40';
            $parent_id = $item['product_id'];
           // $user_kit = get_user_meta($user_id, 'user_kit', true);
            $item_data = get_post_meta($active_campain, 'product_option')['0'][$user_kit][$parent_id];
            if (!empty($item_data['warehouse'])) {
                $item_warehouse = $item_data['warehouse'];
            }


            $data['ORDERITEMS_SUBFORM'][] = [
                'PARTNAME'         => $product->get_sku(),
                'TQUANT'           => (int)$item->get_quantity(),
                'PRICE'            => $price,
                "REMARK1"          => isset($parameters['REMARK1']) ? $parameters['REMARK1'] : '',
                'UFLR_GROUP'           => (int)$item_warehouse,
                'UNI_ORDTYPE'           => 'B',
                'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
                'DOERLOGIN'           => 'israela',
                // 'UNI_EMPNAME' => $username . '/' . $priority_branch_number,
                'UNI_WARHSNAME' => $item_warehouse


            ];
        }
        // unidress extra simple option 
        $simple_option = $item['Simple Option'];
        if (!empty($simple_option)) {
            $data['ORDERITEMS_SUBFORM'][] = [
                'PARTNAME'  => '33791',
                'TQUANT'    => 1,
                'PRICE'  => 0.0,
                "DOERLOGIN" => "israela",
                "UFLR_GROUP" => 97,
                "UNI_ORDTYPE" => 'B',
                'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),

            ];
            $data['ORDERITEMS_SUBFORM'][] = [
                'PARTNAME'  => '000',
                'PDES' => $simple_option,
                'TQUANT'    => 1,
                'PRICE'  => 0.0,
                "DOERLOGIN" => "israela",
                "UFLR_GROUP" => 96,
                "UNI_ORDTYPE" => 'B',
                'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),

            ];
        }
    }

    //  unidress extra not inventory item as remark ARIZA SHEMIT
    if ($customer_type == 'campaign') {
        $data['ORDERITEMS_SUBFORM'][] = [
            'PARTNAME'  => '59603',
            'TQUANT'    => 1,
            'PRICE'  => 0.0,
            "DOERLOGIN" => "marina",
            "UFLR_GROUP" => 99,
            "UNI_ORDTYPE" => 'B',
            'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),

        ];
    }
    // Get shipping fee
    $shipping_total = 0;
    foreach ($order->get_items('fee') as $item_id => $item_fee) {
        // The fee name
        $fee_name = $item_fee->get_name();
        // The fee total amount
        if ('Shipping Price' == $fee_name) {
            $shipping_total = $item_fee->get_total();
        }
    }
    // shiping rate
    if ($shipping_total <> 0) {
        $data['ORDERITEMS_SUBFORM'][] = [
            'PARTNAME' => '000',
            'TQUANT'   => 1,
            'PRICE' => $shipping_total
        ];
    }

    // HERE goes the condition to avoid the repetition
    $post_done = get_post_meta($order->get_id(), '_post_done', true);

    // AUAY-223. Ignore shipping allow: && get_post_meta($active_campain, 'shipping_allow')[0] != 'on'
    if (empty($post_done) ) {
        // make request
        /*if ($debug) {
            echo "<b>Sending request:</b><br/>";
            echo "<pre><div style='direction: ltr'>";
            var_dump($data);
            echo "</div></pre>";
        }*/
        $response = WooAPI::instance()->makeRequest(
            'POST',
            'ORDERS',
            ['body' => json_encode($data)],
            WooAPI::instance()->option('log_auto_post_orders_priority', true)
        );

        if ($response['code'] <= 201 && $response['code'] >= 200 ) {
            $body_array = json_decode($response["body"], true);
            $ord_status = $body_array["ORDSTATUSDES"];
            $ordname_field = $config->ordname_field ?? 'ORDNAME';
            $ord_number = $body_array[$ordname_field];
            $order->update_meta_data('priority_order_status', $ord_status);
            $order->update_meta_data('priority_order_number', $ord_number);
            $order->save();
        }
        else {
            $mes_arr = json_decode($response['body']);
            $message = $response['message'] . '' . json_encode($response);
            $message = $response['message'] . '<br>' . $response['body'] . '<br>';
            if(isset($mes_arr->FORM->InterfaceErrors->text)){
                $message = $mes_arr->FORM->InterfaceErrors->text;
            }
            $order->update_meta_data('priority_order_status', $message);
            $order->save();
        }
        // add timestamp
        return $response;        
    } else {
        echo "Post is in DONE status<br/>";
    }
    if (!$response['status']) {
        /**
         * t149
         */
        WooAPI::instance()->sendEmailError(
            WooAPI::instance()->option('email_error_sync_orders_web'),
            'Error Sync Orders',
            $response['body']
        );
    }

    // add timestamp
    return $response;
}
add_action('wp_ajax_process_selected_orders', 'process_selected_orders');
add_action('wp_ajax_nopriv_process_selected_orders', 'process_selected_orders');
function process_selected_orders() {
    if (isset($_POST['orders']) && !empty($_POST['orders'])) {
        $selected_orders = $_POST['orders'];
        $responses = [];

        foreach($selected_orders as $order) {
            $response = syncOrderCustomUnidress($order, false);
            $responses[] = $response; // Collect each response
        }       
        wp_send_json_success(['selected_orders' => $selected_orders, 'responses' => $responses]);
        // return $responses;
    } else {
        wp_send_json_error('No orders selected.');
    }
}


//in open a new website and defining the template "storefront" built a menu according to the template that is on the main website
add_action('wpmu_new_blog', 'create_menu_for_new_blog', 10, 2);
function create_menu_for_new_blog($blog_id, $user_id) {
    // Store menus for setup when "storefront" is activated
    switch_to_blog($blog_id);
    add_option('pending_menu_sync', true);
    restore_current_blog();
}

add_action('after_switch_theme', 'sync_menu_on_storefront_activation');
function sync_menu_on_storefront_activation() {
    // Check if the current theme is "storefront"
    $current_theme = wp_get_theme();
    if ($current_theme->get('TextDomain') !== 'storefront') {
        return;
    }

    // Check if menu sync is pending
    $blog_id = get_current_blog_id();

    // Switch to the new site's blog context
    switch_to_blog($blog_id);

    // Check if menu sync is pending
    $pending_sync = get_option('pending_menu_sync', false);

    if ($pending_sync !== "1") {
        restore_current_blog();
        return;
    }

    // Remove the pending flag
    delete_option('pending_menu_sync');
    restore_current_blog();

    // Remove the pending flag
    delete_option('pending_menu_sync');

    // Main site ID
    $main_site_id = 1;

    // Menu locations to copy
    $menu_locations_to_copy = [
        'primary' => 'primary',
        'handheld' => 'handheld',
    ];

    // To store menu data
    $menus_to_create = [];

    // Switch to the main site to fetch menu items
    switch_to_blog($main_site_id);

        $menu_locations = get_nav_menu_locations();

        // Fetch menus from the main site
        foreach ($menu_locations_to_copy as $location_key => $location_name) {
            if (isset($menu_locations[$location_name])) {
                $menu_id = $menu_locations[$location_name];
                $menu_items = wp_get_nav_menu_items($menu_id);
                $menus_to_create[$location_key] = $menu_items;
            }
        }
    restore_current_blog();

    switch_to_blog($blog_id);

    // Create and assign menus on the new site
    foreach ($menus_to_create as $location_key => $menu_items) {
        $new_menu_id = wp_create_nav_menu(ucfirst($location_key) . ' Menu');

        foreach ($menu_items as $item) {
            wp_update_nav_menu_item($new_menu_id, 0, array(
                'menu-item-title' => $item->title,
                'menu-item-url' => $item->url,
                'menu-item-status' => 'publish',
                'menu-item-type' => $item->type,
                'menu-item-object' => $item->object,
                'menu-item-parent-id' => $item->menu_item_parent,
            ));
        }

        $locations = get_theme_mod('nav_menu_locations');
        $locations[$location_key] = $new_menu_id;
        set_theme_mod('nav_menu_locations', $locations);
    }
    restore_current_blog();
}

add_action( 'after_setup_theme', 'register_theme_menus' );
// Ensure menus are registered for the "storefront" theme
function register_theme_menus() {
    register_nav_menus( array(
        'primary'   => __( 'Primary Menu', 'storefront' ),
        'secondary' => __( 'Secondary Menu', 'storefront' ),
        'handheld'  => __( 'Handheld Menu', 'storefront' ),
    ) );
}

//add Login comments fields
if (get_current_blog_id() === 1) { 
    if( function_exists('acf_add_options_page') ) {
        acf_add_options_page(array(
            'page_title'    => __('Unidress Settings', 'unidress'),
            'menu_title'    => __('Unidress Settings', 'unidress'),
            'menu_slug'     => 'unidress-settings',
            'capability'    => 'edit_posts',
            'redirect'      => false
        ));
    }

    //Login comments fields
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_login_details',
            'title' => __('Login Details', 'unidress'),
            'fields' => array(
                array(
                    'key' => 'field_5c866ae41e4ed',
                    'label' => __('Header instructions for connecting the site', 'unidress'),
                    'name' => 'login_details',
                    'type' => 'text',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '1000',
                        'class' => 'unidress-input-width',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'min' => '',
                    'max' => '',
                    'step' => '',
                ),
                array(
                    'key' => 'field_5c866af11e4fd',
                    'label' => __('Notes for login page (if any)', 'unidress'),
                    'name' => 'connection_notes',
                    'type' => 'textarea',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '1000',
                        'class' => 'unidress-input-width',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'min' => '',
                    'max' => '',
                    'step' => '',
                ), 
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'unidress-settings',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
        ));
    }
}

add_action('login_form', function () {
    // Fetch posts from the main site
	$main_site_id = 1; // The main site's ID
	switch_to_blog($main_site_id);

    $login_details = get_field('login_details', 'option');
    $connection_notes = get_field('connection_notes', 'option');

    // Restore to the current site
	restore_current_blog();

    if ($login_details || $connection_notes) {
        echo '<div style="margin-bottom: 20px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd;">';
        if ($login_details) {
            echo '<p>' . esc_html($login_details) . '</p>';
        }
        if ($connection_notes) {
            echo '<p>' . esc_html($connection_notes) . '</p>';
        }
        echo '</div>';
    }
    
});
?>