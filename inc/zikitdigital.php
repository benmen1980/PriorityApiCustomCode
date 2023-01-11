<?php

add_filter('simply_syncInventoryPriority_data','simply_syncInventoryPriority_data');
function simply_syncInventoryPriority_data($data){
	$data['expand'] =  '$expand=LOGCOUNTERS_SUBFORM($expand=PARTAVAIL_SUBFORM($filter=TITLE eq \'הזמנות רכש\')),PARTBALANCE_SUBFORM';
	return $data;
}
add_filter('simply_sync_priority_customers_to_wp','simply_sync_priority_customers_to_wp');
function simply_sync_priority_customers_to_wp($user){
//    $wp_user_object = new WP_User(\\\$user['user_id']);
//    $wp_user_object -> set_role('');

	if($user['STATDES'] == 'מוגבל')
    {
	    $wp_user_object = new WP_User($user['user_id']);
        $wp_user_object -> set_role('');
        //update_user_meta($user['user_id'], 'role', '');
    }

}
