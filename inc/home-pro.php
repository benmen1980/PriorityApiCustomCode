<?php
add_filter('simply_syncCustomer','simply_syncCustomer');
function simply_syncCustomer($data){
unset($data['EDOCUMENTS']);
return $data;
}