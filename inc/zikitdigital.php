<?php

add_filter('simply_syncInventoryPriority_data','simply_syncInventoryPriority_data');
function simply_syncInventoryPriority_data($data){
	$data['expand'] =  '$expand=LOGCOUNTERS_SUBFORM($expand=PARTAVAIL_SUBFORM($filter=TITLE eq \'הזמנות רכש\')),PARTBALANCE_SUBFORM';
	return $data;
}
