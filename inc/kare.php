<?php
use PriorityWoocommerceAPI\WooAPI;

// sync inventory from priority
// update that the product is always in stock
// function simply_code_after_sync_inventory($product_id, $item){
// 	if (!$product_id == 0) {
		
// 		// set stock status
// 		$product = wc_get_product($product_id);
// 		$stock_status = 'instock';
// 		$backorder_status = 'no';

// 		$kare_stock = intval(get_post_meta($product_id, 'kare_general_stock', true));
// 		$stock_available = intval(get_post_meta($product_id, '_stock', true));

// 		//Set default menu order
// 		$menu_order = 1; // Default: In-stock products
        
// 		if (($kare_stock <= 0) && ($stock_available <= 0)) {
// 			$stock_status = 'outofstock';
// 			$backorder_status = 'no';
// 			$menu_order = 3;
// 		} elseif ($kare_stock > 0 && $stock_available <= 0) {
// 			$stock_status = 'instock';
// 			$backorder_status = 'yes';
// 			$menu_order = 2;
// 			if(has_post_thumbnail($product_id) && ($product->get_status() !== 'publish')){
// 				$product->set_status('publish');
// 				$product->save();
// 			}
// 		}

//         if ($stock_available > 0 && has_post_thumbnail($product_id) && $product->get_status() !== 'publish') {
// 			$product->set_status('publish');
// 		}


// 		update_post_meta($product_id, '_stock_status', $stock_status);
// 		update_post_meta($product_id, '_backorders', $backorder_status);
// 		$product->set_menu_order($menu_order);
// 		//$shipping_class_slug = $product->get_shipping_class();
// 		//echo 'pdt_id: '.$product_id.'<br>';
// 		//echo "Shipping Class Slug before save: " . $shipping_class_slug.'<br>';
// 		$product->save();
		
// 	}

// }
// 
function simply_code_after_sync_inventory($product_id, $item) {
	if (!$product_id == 0) {

		// set stock status
		$product = wc_get_product($product_id);
		$stock_status = 'instock';
		$backorder_status = 'no';

		$kare_stock = intval(get_post_meta($product_id, 'kare_general_stock', true));
		$stock_available = intval(get_post_meta($product_id, '_stock', true));

		// Determine values for main product
		$menu_order = 1;
		if ($kare_stock <= 0 && $stock_available <= 0) {
			$stock_status = 'outofstock';
			$backorder_status = 'no';
			$menu_order = 3;
		} elseif ($kare_stock > 0 && $stock_available <= 0) {
			$stock_status = 'instock';
			$backorder_status = 'yes';
			$menu_order = 2;
			if (has_post_thumbnail($product_id) && $product->get_status() !== 'publish') {
				$product->set_status('publish');
			}
		} elseif ($stock_available > 0 && has_post_thumbnail($product_id)) {
			if ($product->get_status() !== 'publish') {
				$product->set_status('publish');
			}
		}

		update_post_meta($product_id, '_stock_status', $stock_status);
		update_post_meta($product_id, '_backorders', $backorder_status);
		$product->set_menu_order($menu_order);
		$product->save();

		// WPML: Update Hebrew product if different
		$hebrew_product_id = apply_filters('wpml_object_id', $product_id, 'product', true, 'he');
		if ($hebrew_product_id && $hebrew_product_id != $product_id) {
			$hebrew_product = wc_get_product($hebrew_product_id);
			if ($hebrew_product) {
				$hebrew_stock_available = intval(get_post_meta($hebrew_product_id, '_stock', true));
				if ($hebrew_stock_available != $stock_available) {
					update_post_meta($hebrew_product_id, '_stock', $stock_available);
				}
				update_post_meta($hebrew_product_id, '_stock_status', $stock_status);
				update_post_meta($hebrew_product_id, '_backorders', $backorder_status);
				update_post_meta($hebrew_product_id, '_manage_stock', 'yes');
				$hebrew_product->set_menu_order($menu_order);

				// Optionally publish if same conditions are met
				if (
					$stock_status === 'instock' &&
					has_post_thumbnail($hebrew_product_id) &&
					$hebrew_product->get_status() !== 'publish'
				) {
					$hebrew_product->set_status('publish');
				}

				$hebrew_product->save();
			}
		}
	}
}

// function simply_code_after_sync_inventory_by_sku($product_id, $item){
//     // set stock status
//     if (!$product_id == 0) {
// 		$product = wc_get_product($product_id);
// 		$stock_status = 'instock';
// 		$backorder_status = 'no';

// 		//Set default menu order
// 		$menu_order = 1; // Default: In-stock products

// 		$kare_stock = intval(get_post_meta($product_id, 'kare_general_stock', true));
// 		$stock_available = intval(get_post_meta($product_id, '_stock', true));
// 		$kare_date_available = get_post_meta($product_id, 'available_ex_muc', true);
//         //echo 'stock choul'.$kare_stock;
//         //echo 'stcok israel'.$stock_available;
// 		if (($kare_stock <= 0) && ($stock_available <= 0)) {
// 			$stock_status = 'outofstock';
// 			$backorder_status = 'no';
// 			$menu_order = 3;
// 		} elseif ($kare_stock > 0 && $stock_available <= 0) {
// 			$stock_status = 'instock';
// 			$backorder_status = 'yes';
// 			$menu_order = 2;
// 			if(has_post_thumbnail($product_id) && ($product->get_status() !== 'publish')){
// 				$product->set_status('publish');
// 				$product->save();
// 			}
// 		}

// 		update_post_meta($product_id, '_stock_status', $stock_status);
// 		update_post_meta($product_id, '_backorders', $backorder_status);

// 		$product->set_menu_order($menu_order);
//         //echo 'menu order for sku 57360'.$product->get_menu_order();
// 		$product->save();
//         //echo 'menu order for sku 57360'.$product->get_menu_order();
// 	}
// }


function simply_code_after_sync_inventory_by_sku($product_id, $item){
    if (!$product_id == 0) {
        $product = wc_get_product($product_id);
        $stock_status = 'instock';
        $backorder_status = 'no';
        $menu_order = 1;

        $kare_stock = intval(get_post_meta($product_id, 'kare_general_stock', true));
        $stock_available = intval(get_post_meta($product_id, '_stock', true));
        $kare_date_available = get_post_meta($product_id, 'available_ex_muc', true);

        if (($kare_stock <= 0) && ($stock_available <= 0)) {
            $stock_status = 'outofstock';
            $backorder_status = 'no';
            $menu_order = 3;
        } elseif ($kare_stock > 0 && $stock_available <= 0) {
            $stock_status = 'instock';
            $backorder_status = 'yes';
            $menu_order = 2;
            if (has_post_thumbnail($product_id) && ($product->get_status() !== 'publish')) {
                $product->set_status('publish');
            }
        }

        update_post_meta($product_id, '_stock_status', $stock_status);
        update_post_meta($product_id, '_backorders', $backorder_status);
        $product->set_menu_order($menu_order);
        $product->save();

        // Update Hebrew translation
        $hebrew_product_id = apply_filters('wpml_object_id', $product_id, 'product', true, 'he');
        if ($hebrew_product_id && $hebrew_product_id != $product_id) {
            $hebrew_product = wc_get_product($hebrew_product_id);
            if ($hebrew_product) {
                // Sync _stock if needed
                $hebrew_stock_available = intval(get_post_meta($hebrew_product_id, '_stock', true));
                if ($hebrew_stock_available != $stock_available) {
                    update_post_meta($hebrew_product_id, '_stock', $stock_available);
                }

                // Sync other fields
                update_post_meta($hebrew_product_id, '_stock_status', $stock_status);
                update_post_meta($hebrew_product_id, '_backorders', $backorder_status);
				update_post_meta($hebrew_product_id, '_manage_stock', 'yes');
                $hebrew_product->set_menu_order($menu_order);

                if (
                    $stock_status === 'instock' &&
                    has_post_thumbnail($hebrew_product_id) &&
                    $hebrew_product->get_status() !== 'publish'
                ) {
                    $hebrew_product->set_status('publish');
                }

                $hebrew_product->save();
            }
        }
    }
}

function simply_syncItemsPriorityAdapt($item){
    $priority_version = (float) WooAPI::instance()->option( 'priority-version' );
    // config
    $raw_option     = WooAPI::instance()->option( 'sync_items_priority_config' );
    $raw_option     = str_replace( array( "\n", "\t", "\r" ), '', $raw_option );
    $config         = json_decode( stripslashes( $raw_option ) );

    $daysback            = ( ! empty( (int) $config->days_back ) ? $config->days_back : 1 );
    $stamp          = mktime( 0 - $daysback * 24, 0, 0 );
    $bod            = date( DATE_ATOM, $stamp );
    $date_filter    = 'UDATE ge ' . urlencode( $bod );
    $url_addition_config = ( ! empty( $config->additional_url ) ? $config->additional_url : '' );
    $search_field        = ( ! empty( $config->search_by ) ? $config->search_by : 'PARTNAME' );
    $search_field_web    = ( ! empty( $config->search_field_web ) ? $config->search_field_web : '_sku' );
    $stock_status        = ( ! empty( $config->stock_status ) ? $config->stock_status : 'outofstock' );
    $is_categories       = ( ! empty( $config->categories ) ? $config->categories : null );
    $is_attrs            = ( ! empty( $config->attrs ) ? $config->attrs : false );
    $is_update_products  = ( ! empty( $config->is_update_products ) ? $config->is_update_products : false );
    $show_in_web         = ( ! empty( $config->show_in_web ) ? $config->show_in_web : 'SHOWINWEB' );
    $product_id = wc_get_product_id_by_sku( $item["PARTNAME"] );

    if ( $product_id ) {
        $post_status = get_post_status( $product_id );
        if ( in_array( $post_status, array( 'publish', 'draft' ), true ) ) {
            $_product = wc_get_product( $product_id );
        } else {
            $product_id = 0; // Ignore products not in publish/draft
        }
    }
    if ( isset( $show_in_web ) ) {
        if ( $product_id == 0 && $item[ "SHOWINWEB"]  != 'Y' ) {
            return;
        }
        if ( $product_id != 0 && $item[ "SHOWINWEB"]  != 'Y' ) {
            $_product->set_status( 'draft' );
            $_product->save();

            // ✅ Also set Hebrew translation to draft manually
            $hebrew_product_id = apply_filters('wpml_object_id', $product_id, 'product', true, 'he');
            if ( $hebrew_product_id && $hebrew_product_id != $product_id ) {
                $hebrew_product = wc_get_product( $hebrew_product_id );
                if ( $hebrew_product && $hebrew_product->get_status() !== 'draft' ) {
                    $hebrew_product->set_status( 'draft' );
                    $hebrew_product->save();
                }
            }
            return;
        }
		 else if($product_id != 0 && $item[ "SHOWINWEB"]  = 'Y'){
            if (has_post_thumbnail($product_id)) { 
                $_product->set_status( 'publish' );
                $_product->save();
                return;
            } 
			$hebrew_product_id = apply_filters('wpml_object_id', $product_id, 'product', true, 'he');
            if ( $hebrew_product_id && $hebrew_product_id != $product_id ) {
                $hebrew_product = wc_get_product( $hebrew_product_id );
                if ( $hebrew_product && $hebrew_product->get_status() !== 'publish' ) {
                    $hebrew_product->set_status( 'publish' );
                    $hebrew_product->save();
                }
            }
            return;
        }
    }
    if ( $product_id != 0 ) {
                        
        //$_product->set_status(WooAPI::instance()->option('item_status'));
        //$_product->save();
        $id = $product_id;
        global $wpdb;
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
        
    }
    else {
        // Insert product
        $data = [
            'post_author' => 1,
            //'post_content' =>  $content,
            'post_status' => WooAPI::instance()->option( 'item_status' ),
            'post_title'  => $item['PARTDES'],
            'post_parent' => '',
            'post_type'   => 'product',
        ];
        $id = wp_insert_post( $data );
        if ( $id ) {
            update_post_meta( $id, '_sku', "PARTNAME" );
            update_post_meta( $id, '_stock_status', $stock_status );
            if ( $stock_status == 'outofstock' ) {
                update_post_meta( $id, '_stock', 0 );
                wp_set_post_terms( $id, 'outofstock', 'product_visibility', true );
            }
            if ( ! empty( $item['INVFLAG'] ) ) {
                update_post_meta( $id, '_manage_stock', ( $item['INVFLAG'] == 'Y' ) ? 'yes' : 'no' );
            }
        }
    }
    $set_tax = get_option('woocommerce_calc_taxes');
    $pri_price = (wc_prices_include_tax() == true || $set_tax == 'no') ? $item['VATPRICE'] : $item['BASEPLPRICE'];
    $my_product = new \WC_Product( $id );
    $my_product->set_regular_price( $pri_price );
    if ( $product_price_sale != null && ! empty( $item[ $product_price_sale ] ) ) {
        $price_sale = $item[ $product_price_sale ];
        if ( $price_sale != 0 ) {
            $my_product->set_sale_price( $price_sale );
        }
    }
    if ( ! empty( $my_product->get_meta_data( 'family_code', true ) ) ) {
        $my_product->update_meta_data( 'family_code', $item['FAMILYNAME'] );
    } else {
        $my_product->add_meta_data( 'family_code', $item['FAMILYNAME'] );
    }
    $my_product->save();
    $taxon = 'product_cat';
    if ( ! empty( $config->parent_category ) || ! empty( $is_categories ) ) {
        $terms = get_the_terms( $id, $taxon );
        foreach ( $terms as $term ) {
            wp_remove_object_terms( $id, $term->term_id, $taxon );
        }
    }
    if ( ! empty( $config->parent_category ) ) {
        $parent_category = wp_set_object_terms( $id, $item[ $config->parent_category ], $taxon, true );
    }
    if ( ! empty( $is_categories ) ) {
        // update categories
        $categories = [];
        foreach ( explode( ',', $config->categories ) as $cat ) {
            if ( ! empty( $item[ $cat ] ) ) {
                array_push( $categories, $item[ $cat ] );
            }
        }
		if ( ! empty( $categories ) ) {
			$d           = 0;
			$terms       = $categories;
			$taxon       = 'product_cat'; // or your custom taxonomy
			$current_lang = apply_filters( 'wpml_current_language', NULL );
			$default_lang = apply_filters( 'wpml_default_language', NULL );

			// Switch to default language (English)
			if ( $current_lang !== $default_lang ) {
				do_action( 'wpml_switch_language', $default_lang );
			}

			// Make sure parent category is in English
			if ( ! empty( $config->parent_category ) && $parent_category[0] > 0 ) {
				$parent_cat_id_en = $parent_category[0];

				// Check if term exists under parent (in English)
				$term_exists = term_exists( $terms[0], $taxon, $parent_cat_id_en );

				// Get all children of parent
				$childs = get_term_children( $parent_cat_id_en, $taxon );

				if ( ! empty( $childs ) ) {
					foreach ( $childs as $child ) {
						$cat_c = get_term_by( 'id', $child, $taxon, 'ARRAY_A' );

						if ( $cat_c && html_entity_decode( $cat_c['name'] ) === $terms[0] ) {
							$english_term_id = $child;
							$d = 1;
							break;
						}
					}
				}

				// Insert new term under parent (in English)
				if ( empty( $term_exists ) && $d === 0 ) {
					$inserted_term = wp_insert_term( $terms[0], $taxon, array( 'parent' => $parent_cat_id_en ) );

					if ( ! is_wp_error( $inserted_term ) ) {
						$english_term_id = $inserted_term['term_id'];
						$d = 1;
					}
				}
			}

			// Switch back to original language (e.g., Hebrew)
			if ( $current_lang !== $default_lang ) {
				do_action( 'wpml_switch_language', $current_lang );
			}

			// Assign translated term to product
			if ( isset( $english_term_id ) ) {
				$translated_term_id = function_exists('icl_object_id') 
					? icl_object_id( $english_term_id, $taxon, true, $current_lang ) 
					: $english_term_id;

				if ( $translated_term_id && ! is_wp_error( $translated_term_id ) ) {
					wp_set_object_terms( $id, $translated_term_id, $taxon, true );
				}
			} else {
				// No parent category – just assign base category
				$term_name = $terms[0];
				$term = get_term_by( 'name', $term_name, $taxon );

				if ( $term ) {
					$translated_term_id = function_exists('icl_object_id') 
						? icl_object_id( $term->term_id, $taxon, true, $current_lang ) 
						: $term->term_id;

					if ( $translated_term_id ) {
						wp_set_object_terms( $id, $translated_term_id, $taxon, true );
					}
				}
			}
		}

    }
    $item['product_id'] = $id;
    do_action( 'simply_update_product_data', $item );
    return;
}

// sync items from priority
//define select field for sync item
add_filter('simply_syncItemsPriority_data', 'simply_syncItemsPriority_data_func');
function simply_syncItemsPriority_data_func($data)
{
    $data['select'] .= ',SUPNAME,SUPDES,ZYOU_LONGFAMILYDES,ZYOU_MFAMILYDES,ZYOU_FAMILYDES,ZYOU_SPECEDES15';
    return $data;
}

function update_product_color_attributes( $product_id, $colors ) {


    // Check if the attribute exists; if not, create it
    $attr_slug = 'color'; // Global attribute slug for color
    $attr_taxonomy = 'pa_' . $attr_slug; // WooCommerce expects 'pa_' prefix

    // Ensure the attribute exists in WooCommerce
    if ( class_exists('WooAPI') && method_exists(WooAPI::instance(), 'is_attribute_exists') ) {
        if ( ! WooAPI::instance()->is_attribute_exists($attr_slug) ) {
            wc_create_attribute(
                array(
                    'name'         => $attr_slug,
                    'slug'         => $attr_slug,
                    'type'         => 'select',
                    'order_by'     => 'menu_order',
                    'has_archives' => 0,
                )
            );
        }
    }

    // Sanitize color names (capitalize first letter, trim spaces)
    $clean_colors = array_map( function ( $color ) {
        return ucwords( trim( strtolower( $color ) ) );
    }, $colors );

    // Ensure terms exist for each color
    foreach ( $clean_colors as $color ) {
        if ( ! term_exists( $color, $attr_taxonomy ) ) {
            wp_insert_term( $color, $attr_taxonomy );
        }
    }

    // Assign colors to the product
    wp_set_object_terms( $product_id, $clean_colors, $attr_taxonomy, false );

    // Retrieve existing attributes
    $product_attributes = get_post_meta( $product_id, '_product_attributes', true );

    if ( ! is_array( $product_attributes ) ) {
        $product_attributes = array();
    }

    // Add or update the color attribute in product attributes
    $product_attributes[ $attr_taxonomy ] = array(
        'name'         => $attr_taxonomy,
        'value'        => '',
        'is_visible'   => 1, // Show on product page
        'is_variation' => 0, // Not used for variations
        'is_taxonomy'  => 1, // This is a taxonomy attribute
    );

    // Save updated product attributes
    update_post_meta( $product_id, '_product_attributes', $product_attributes );

    // Ensure WPML compatibility (get translated attributes)
    if ( function_exists('icl_object_id') ) {
        $current_lang = apply_filters('wpml_current_language', NULL);
        $translated_colors = [];

        foreach ($clean_colors as $color) {
            $translated_color_id = icl_object_id( term_exists($color, $attr_taxonomy)['term_id'], $attr_taxonomy, true, $current_lang );
            if ($translated_color_id) {
                $translated_color = get_term($translated_color_id, $attr_taxonomy);
                if ($translated_color) {
                    $translated_colors[] = $translated_color->name;
                }
            }
        }

        // Assign translated colors if available
        if (!empty($translated_colors)) {
            wp_set_object_terms($product_id, $translated_colors, $attr_taxonomy, false);
        }
    }
}
add_action('simply_update_product_data', function($item){

    $product_id = $item['product_id'];

    // check if the item flagged is 'O.C.' 
    // הוחלט לא להתיחס ל O.C  במוצרים כרגע רק לפי שדה "showinweb"
    /*if ( $product_id != 0 && $item['SPEC6'] == 'O.C' ) {
        $kare_stock = get_post_meta($product_id, 'kare_general_stock', true);
        $stock_available = get_post_meta($product_id, '_stock', true);

        if (  (empty($kare_stock) || $kare_stock <= 0) && intval($stock_available) <= 0  ) {
			$_product = wc_get_product( $product_id );
			$_product->delete( true );
			$_product->save();
            return;
        }
    }*/
    
    if($product_id !== 0) {
        $pdt_product = wc_get_product( $product_id );

        //update procuct details
        $item_num =  $item['SPEC1'];
        $width =  $item['SPEC9'];
        $depth =  $item['SPEC13'];
        $height =  $item['SPEC11'];
        $weight =  $item['SPEC8'];
        $color =  $item['ZYOU_SPECEDES15'];
        // $series =  $item['SPEC12'];

        $colors = array_map('trim', explode(',', $color));
        update_product_color_attributes($product_id, $colors);

        $product_details_data = [
            'pdt_information' => [
                'item_number' => $item_num,
                'width' => $width,
                'depth' => $depth,
                'height' => $height,
                'weight' => $weight,
                'color' => $color
            ],
        ];
        update_field('product_details', $product_details_data, $product_id);

        $main_module =  $item['SPEC4'];
        $qty_product_order =  $item['SPEC12'];
        update_post_meta($product_id, 'main_module', $main_module);
        update_post_meta($product_id, 'quantity_product_order', $qty_product_order);

        //update size ship
        $shipping_size =  strtolower($item['SPEC5']);
        // simply_set_ship_class($product_id, $shipping_size);
        $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));
        foreach ($shipping_classes as $shipping_class) {
            //if (strcasecmp($shipping_size, $shipping_class->slug) == 0) {
            if (strcasecmp(trim($shipping_size), trim($shipping_class->slug)) == 0){
                // assign class to product
                $product = wc_get_product($product_id); // Get an instance of the WC_Product Object
                $product->set_shipping_class_id($shipping_class->term_id); // Set the shipping class ID
                $product->save(); // Save the product data to database
                continue;
            }
		
			
        }
        
        //update main parent category
//         $taxon = 'product_cat';
//         $main_parent_category_name = $item['ZYOU_FAMILYDES']; //FURNITURE
//         $parent_category_name = $item['ZYOU_MFAMILYDES']; //Tables

//         $main_parent_category = term_exists( $main_parent_category_name, $taxon );
//         if ( ! $main_parent_category ) {
//             $main_parent_category = wp_insert_term( $main_parent_category_name, $taxon );
//         }

//         if ( ! is_wp_error( $main_parent_category ) && ! empty( $main_parent_category['term_id'] ) ) {
//             $parent_category = term_exists( $parent_category_name, $taxon );
//             // $child_category = term_exists( $parent_category_name, $taxon, $main_parent_category['term_id'] );
//             if ( ! empty( $parent_category ) && ! is_wp_error( $parent_category )) {
//                 // wp_insert_term( $parent_category, $taxon, array( 'parent' => $main_parent_category['term_id'] ) );
//                 wp_update_term( $parent_category['term_id'], $taxon, array( 'parent' => $main_parent_category['term_id'] ) );
//             }
//         }

		$taxon = 'product_cat';

		// Get current WPML language
		$current_lang = function_exists('apply_filters') ? apply_filters('wpml_current_language', NULL) : '';

		// Get translated names if needed (optional step, depends on your source)
		$main_parent_category_name = $item['ZYOU_FAMILYDES']; // e.g., 'Furniture'
		$parent_category_name      = $item['ZYOU_MFAMILYDES']; // e.g., 'Tables'

		// Check if main parent exists
		$main_parent_category = term_exists( $main_parent_category_name, $taxon );

		// If not, insert it
		if ( ! $main_parent_category ) {
			$main_parent_category = wp_insert_term( $main_parent_category_name, $taxon );
		}

		// Handle WPML translation for main parent
		if ( function_exists('icl_object_id') && ! is_wp_error( $main_parent_category ) && ! empty( $main_parent_category['term_id'] ) ) {
			$main_parent_category_id = icl_object_id( $main_parent_category['term_id'], $taxon, true, $current_lang );
		} else {
			$main_parent_category_id = $main_parent_category['term_id'];
		}

		// Now process the child category
		if ( ! is_wp_error( $main_parent_category ) && ! empty( $main_parent_category_id ) ) {

			// Check if child category exists
			$parent_category = term_exists( $parent_category_name, $taxon );

			if ( ! $parent_category ) {
				// Insert child under the translated parent
				$parent_category = wp_insert_term( $parent_category_name, $taxon, array( 'parent' => $main_parent_category_id ) );
			} else {
				// Ensure child is correctly assigned to parent
				$parent_category_id = function_exists('icl_object_id') 
					? icl_object_id( $parent_category['term_id'], $taxon, true, $current_lang )
					: $parent_category['term_id'];

				wp_update_term( $parent_category_id, $taxon, array( 'parent' => $main_parent_category_id ) );
			}
		}
        
        //update tag
        $tag =  $item['SPEC6'];
        if ($tag == 'A') {
            wp_set_object_terms($product_id, 'new', 'product_tag');
        } 
        elseif ($tag == 'B.S') {
            wp_set_object_terms($product_id, 'bestseller', 'product_tag');
        }
        else {
            wp_set_object_terms($product_id, null, 'product_tag'); 
        }
        $bs_tags = $item['SPEC10'];
        if ($bs_tags) {
            wp_set_object_terms($product_id, 'bestseller', 'product_tag', true);
        }


        //update inspiration caegory - preferred supplier
        //כרגע הוחלט שזה הזנה באתר ולא מגיע מפריוריטי
        /*$trend = $item['SUPDES'];
        if ( $trend && $trend != null ) {
            wp_set_object_terms($product_id, $trend, 'trend_cat', true);
        } else {
            wp_set_object_terms($product_id, null, 'trend_cat');
        }*/

        // check if there is a main image to product and publish
        if (has_post_thumbnail($product_id)) {
            $product = wc_get_product($product_id);
            if ($product->get_status() !== 'publish') {
                $product->set_status('publish');
                $product->save();
            }
        }
        $pdt_product->save();
        do_action( 'wpml_sync_all_custom_fields', $product_id );   
    }
});

//open customer in priority then in register
//add_action( 'template_redirect', 'get_user_details_after_registration1');
function get_user_details_after_registration1() {
    if ( is_user_logged_in() && !current_user_can( 'manage_options' )) {
        $id = get_current_user_id();
        if(empty(get_user_meta($id, 'user_reg', true))){
            update_user_meta($id, 'user_reg', true);
            if ($user = get_userdata($id)) {
                $meta = get_user_meta($id);
                // if already assigned value it is stronger
                $email = strtolower($user->data->user_email);
                $phone = $meta['billing_phone'][0];
                // if ($email) {
                //$url_addition = 'CUSTOMERS?$filter=(EMAIL eq \''.$email.'\' or PHONE eq \''.$phone .'\')';
                $url_addition = 'CUSTOMERS?$filter=EMAIL eq \''.$email.'\'';
                $response_email =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
                if($response_email['code'] == 200) {
                    $body_email =   json_decode($response_email['body'])->value;
                    if(empty($body_email)){
                        $response_phone =  WooAPI::instance()->makeRequest('GET', 'CUSTOMERS?$filter=PHONE eq \''.$phone .'\'' , [], true);
                        if($response_phone['code'] == 200) {
                            $body_phone =   json_decode($response_phone['body'])->value;
                            if(!empty($body_phone)){
                                $custname = $body_phone[0]->CUSTNAME;
                            }
                        }
                        else{
                            //$custname = null;
                            $subj = 'Error when searching for a customer by phone in sync to priority';
                            wp_mail( get_option('admin_email'), $subj, $response_phone['body'] );
                        }
                    }
                    else{
                        $custname = $body_email[0]->CUSTNAME;
                    }
                } else{
                    //$custname = null;
                    $subj = 'Error when searching for a customer by email in sync to priority';
                    wp_mail( get_option('admin_email'), $subj, $response_email['body'] );
                }
                // } 
                
    
                if (!empty($custname)) {
                    update_user_meta($id, 'priority_customer_number', $custname);
                }
                
                $birthday = get_user_meta( $id, 'birth_date', true );
                $timezone = new DateTimeZone('Asia/Jerusalem'); 
                $date = DateTime::createFromFormat('Y-m-d', $birthday, $timezone); 
                $date->setTime(0, 0, 0);
                $birthday_date = $date->format('Y-m-d\TH:i:sP');

                $gender = get_user_meta( $id, 'sex_selection', true );
                $user_arrived_choice = get_user_meta($id, 'user_arrived_choice', true);
                $club = get_user_meta($id, 'checkbox_club', true);
         
    
                $request = [
                    'CUSTNAMEPATNAME' => 'DW',
                    //'CUSTNAME' => $priority_customer_number,
                    'CUSTDES' => empty($meta['first_name'][0]) ? $meta['nickname'][0] : $meta['first_name'][0].' '.$meta['last_name'][0],
                    'EMAIL' => $user->data->user_email,
                    'ZYOU_BIRTHDATE10' => !empty($birthday_date) ? $birthday_date : '',
                    'PHONE' => isset($meta['billing_phone']) ? $meta['billing_phone'][0] : '',
                    'NSFLAG' => 'Y',
                    'SPEC4' => !empty( $user_arrived_choice ) ? $user_arrived_choice : '',
                    'CTYPECODE' => !empty($club) ? '02' : '03',
                    'SPEC3' => !empty( $gender ) ? $gender : '',
                    'ZYOU_MAILAPP' => !empty($club) ? 'Y' : '',
                    'SPEC20' => strval($id),
                    'SPEC19' => 'נרשם באתר'
                ];
                
                $method = !empty($custname) ? 'PATCH' : 'POST';
                // $method = 'POST';
                $url_eddition = 'CUSTOMERS';
                if ($method == 'PATCH') {
                    $url_eddition = 'CUSTOMERS(\'' . $custname . '\')';
                    unset($request['CUSTNAMEPATNAME']);
                }

                $json_request = json_encode($request);
                $response = WooAPI::instance()->makeRequest($method, $url_eddition, ['body' => $json_request], true);
                if ($method == 'POST' && $response['code'] == '201' || $method == 'PATCH' && $response['code'] == '200') {
                    $data = json_decode($response['body']);
                    $priority_customer_number = $data->CUSTNAME;
                    update_user_meta($id, 'priority_customer_number', $priority_customer_number);
                } // set priority customer id
                else {
                    WooAPI::instance()->sendEmailError(
                        [WooAPI::instance()->option('email_error_sync_customers_web')],
                        'Error Sync Customers',
                        $response['body']
                    );
                }
                
                // add timestamp
                //$this->updateOption('post_customers', time());
            }
        }
       
    }
}


add_action('template_redirect', 'schedule_user_registration_check');

function schedule_user_registration_check() {
    if (current_user_can('manage_options') || is_admin()) {
        return;
    }

    $id = get_current_user_id();
    if (!$id || get_user_meta($id, 'user_reg', true)) {
        return;
    }

    // Prevent running the check on every refresh (cache result for 10 min)
    if (get_transient("user_reg_check_$id")) {
        return;
    }
    set_transient("user_reg_check_$id", true, 10 * MINUTE_IN_SECONDS);

    get_user_details_after_registration($id);
}


function get_user_details_after_registration($id) {
    if (!$id || get_user_meta($id, 'user_reg', true)) {
        return;
    }

    update_user_meta($id, 'user_reg', true);

    $user = get_userdata($id);
    if (!$user) {
        return;
    }

    $meta = get_user_meta($id);
    $email = strtolower($user->user_email);
    $phone = isset($meta['billing_phone'][0]) ? $meta['billing_phone'][0] : '';

    // Check for customer by email
    $url_addition = "CUSTOMERS?\$filter=EMAIL eq '$email'";
    $response_email = WooAPI::instance()->makeRequest('GET', $url_addition, [], true);

    $custname = null;
    if ($response_email['code'] == 200) {
        $body_email = json_decode($response_email['body'])->value;
        if (empty($body_email) && $phone) {
            // Check for customer by phone
            $response_phone = WooAPI::instance()->makeRequest('GET', "CUSTOMERS?\$filter=PHONE eq '$phone'", [], true);
            if ($response_phone['code'] == 200) {
                $body_phone = json_decode($response_phone['body'])->value;
                if (!empty($body_phone)) {
                    $custname = $body_phone[0]->CUSTNAME;
                }
            } else {
                wp_mail(get_option('admin_email'), 'Error searching customer by phone', $response_phone['body']);
            }
        } else {
            $custname = $body_email[0]->CUSTNAME;
        }
    } else {
        wp_mail(get_option('admin_email'), 'Error searching customer by email', $response_email['body']);
    }

    // Update user meta with customer name if found
    if ($custname) {
        update_user_meta($id, 'priority_customer_number', $custname);
    }

    // Prepare additional user details
    $birthday = get_user_meta($id, 'birth_date', true);
    $gender = get_user_meta($id, 'sex_selection', true);
    $user_arrived_choice = get_user_meta($id, 'user_arrived_choice', true);
    $club = get_user_meta($id, 'checkbox_club', true);

    // Format birthday date if available
    $birthday_date = '';
    if (!empty($birthday)) {
        $timezone = new DateTimeZone('Asia/Jerusalem');
        $date = DateTime::createFromFormat('Y-m-d', $birthday, $timezone);
        if ($date) {
            $date->setTime(0, 0, 0);
            $birthday_date = $date->format('Y-m-d\TH:i:sP');
        }
    }

    $request = [
        'CUSTNAMEPATNAME' => 'DW',
        'CUSTDES' => isset($meta['first_name'][0]) ? $meta['first_name'][0] . ' ' . $meta['last_name'][0] : $meta['nickname'][0],
        'EMAIL' => $user->user_email,
        'ZYOU_BIRTHDATE10' => $birthday_date,
        'PHONE' => $phone,
        'NSFLAG' => 'Y',
        'SPEC4' => $user_arrived_choice ?: '',
        'CTYPECODE' => $club ? '02' : '03',
        'SPEC3' => $gender ?: '',
        'ZYOU_MAILAPP' => $club ? 'Y' : '',
        'SPEC20' => strval($id),
        'SPEC19' => 'נרשם באתר'
    ];

    $method = $custname ? 'PATCH' : 'POST';
    $url_edition = $custname ? "CUSTOMERS('$custname')" : 'CUSTOMERS';

    if ($method === 'PATCH') {
        unset($request['CUSTNAMEPATNAME']);
    }

    $json_request = json_encode($request);
    $response = WooAPI::instance()->makeRequest($method, $url_edition, ['body' => $json_request], true);

    if (($method === 'POST' && $response['code'] == 201) || ($method === 'PATCH' && $response['code'] == 200)) {
        $data = json_decode($response['body']);
        update_user_meta($id, 'priority_customer_number', $data->CUSTNAME);
    } else {
        WooAPI::instance()->sendEmailError(
            [WooAPI::instance()->option('email_error_sync_customers_web')],
            'Error Sync Customers',
            $response['body']
        );
    }
}

add_filter('simply_modify_customer_number','simply_modify_customer_number');
function simply_modify_customer_number($data){  
    $order = $data['order'];
    
    if ($order->get_user_id() != 0) {
        $cust_number = get_user_meta($order->get_user_id(), 'priority_customer_number', true);
        if(empty($cust_number)){
            $cust_number = WooAPI::instance()->option('walkin_number');
        }
    }
    else{
        $cust_number = WooAPI::instance()->option('walkin_number');
    }
    $data['CUSTNAME'] = $cust_number;
    return $data;
}

// update data and fields in syncorder to priority
add_filter('simply_request_data', 'simply_func');
function simply_func($data)
{
	$data['AGENTCODE'] = '01';    
	$data['TYPECODE'] = '006';
    $agent_note = $data['ORDERSTEXT_SUBFORM']['TEXT'];
//     if (strlen($agent_note) > 120) {
//         $agent_note = substr($agent_note, 0, 115) . '...'; 
//     }
//     fix  code above cause fattal error
    if (mb_strlen($agent_note, 'UTF-8') > 120) {
		$agent_note = mb_substr($agent_note, 0, 115, 'UTF-8');
	}
    $data['ESTR_NOTES'] = $agent_note;
	
	//Update payment code for the query
    $data['PAYMENTDEF_SUBFORM']['PAYMENTCODE'] = '15';
	
	$order_id = $data['orderId'];
    $order = new \WC_Order($order_id);

    //set coupon to vprice instead vatprice
	$coupon = $order->get_coupon_codes();
	if (!empty($coupon)) {
		// echo "<pre>";
		// print_r($coupon);
		// echo "</pre>";
        //$mappings = get_field('coupon_agent_mappings', 'option');
		$default_language = apply_filters('wpml_default_language', null);
		$current_language = apply_filters('wpml_current_language', null);

		if ( $current_language !== $default_language ) {
			do_action('wpml_switch_language', $default_language);
		}

		$mappings = get_field('coupon_agent_mappings', 'option');

		if ( $current_language !== $default_language ) {
			do_action('wpml_switch_language', $current_language);
		}
		//print_r( $mappings);
        if ($mappings) {
            foreach ($mappings as $mapping) {
                if (in_array($mapping['coupon_code'], $coupon)) {
                    $data['AGENTCODE'] = $mapping['agent_name']; // Return the first matched agent name
                }
            }
        }
    }
	$items = [];
    foreach($data['ORDERITEMS_SUBFORM'] as $item ){
        //coupon
        if($item['PARTNAME'] == '004' ){
            if(!empty($coupon) && $item['TQUANT'] == -1){
                $item['PDES'] = $coupon[0];
            }
        }
        $items[] = $item;
    }
    $data['ORDERITEMS_SUBFORM'] = $items;
	
// 	echo "<pre>";
// 	print_r($data);
// 	echo "</pre>";
// 	die();
	
    return $data;
}

// update data and fields in receipt - sync to priority
add_filter('simply_request_data_receipt', 'simply_receipt_func');
function simply_receipt_func($data)
{
	$data['AGENTCODE'] = '01';    
	$data['TYPECODE'] = '006';
	$data['CASHNAME'] = '005';

    //ger the number order and update in Receipt
    $order_id = $data['orderId'];
    $order = wc_get_order( $order_id );
    $ord_number = $order->get_meta( 'priority_order_number', true );
	$data['ORDNAME'] = $ord_number;
    unset($data['REFERENCE']);
	
	//Update payment code for the query
    $data['TPAYMENT2_SUBFORM'][0]['PAYMENTCODE'] = '15';
	
	$coupon = $order->get_coupon_codes();
	if (!empty($coupon)) {
		//echo "<pre>";
		//print_r($coupon);
		//echo "</pre>";
        //$mappings = get_field('coupon_agent_mappings', 'option');
		$default_language = apply_filters('wpml_default_language', null);
		$current_language = apply_filters('wpml_current_language', null);

		if ( $current_language !== $default_language ) {
			do_action('wpml_switch_language', $default_language);
		}

		$mappings = get_field('coupon_agent_mappings', 'option');

		if ( $current_language !== $default_language ) {
			do_action('wpml_switch_language', $current_language);
		}
		print_r( $mappings);
        if ($mappings) {
            foreach ($mappings as $mapping) {
                if (in_array($mapping['coupon_code'], $coupon)) {
                    $data['AGENTCODE'] = $mapping['agent_name']; // Return the first matched agent name
                }
            }
        }
    }

    return $data;
}

add_action('simply_sync_payment_complete', 'simply_sync_payment_complete_func');
function simply_sync_payment_complete_func($order_id){
    global $wp_current_filter;

    if ( !in_array( 'woocommerce_order_status_changed', $wp_current_filter, true ) ) {
        return false;
    }
    else{
        return true;
    }   
}

//get priority customer number by user email
// add_filter('simply_search_customer_in_priority','simply_search_customer_in_priority_func');
/*function simply_search_customer_in_priority_func($data){
    $order = $data['order'];
    $order_id = $order->id;
    $order = wc_get_order($order_id);
    if ($order) {
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
    }
    if ($email || $phone) {
        $url_addition = 'CUSTOMERS?$filter=(EMAIL eq \''.$email.'\' or PHONE eq \''.$phone .'\')';
        $response =  WooAPI::instance()->makeRequest('GET', $url_addition, [], true);
        if($response['code'] == 200) {
            $body =   json_decode($response['body']);
            if(!empty($body)){
                $value = $body->value[0];
                $custname = $value->CUSTNAME;
            }
        } else{
            $custname = null;
            $subj = 'Error when searching for a customer in sync to priority';
            wp_mail( get_option('admin_email'), $subj, $response['body'] );
        }
    } else{
        $custname = null;
    }
    $data['CUSTNAME'] = $custname;
    return $data;
}*/
	
//add_filter('simply_syncCustomer', 'simply_syncCustomer_func');
/*function simply_syncCustomer_func($request)
{
    unset($request['EDOCUMENTS']);
	$request['CTYPECODE'] = '03';

    //get the data from user and update in customer
    $user_id = $request["id"];
    $birth_date = get_user_meta($user_id, 'birth_date', true);
    $user_arrived_choice = get_user_meta($user_id, 'user_arrived_choice', true);
	$request['SPEC2'] = !empty( $birth_date ) ? $birth_date : '';
	$request['SPEC4'] = !empty( $user_arrived_choice ) ? $user_arrived_choice : '';

    return $request; 
}*/
?>