<?php
add_filter('simply_request_data','manipulate_data');
function manipulate_data($data){
    unset($data['CDES']);
    return $data;
}

