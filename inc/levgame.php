<?php
add_filter('simply_request_data', 'simply_func');
function simply_func($data) {
    unset($data['CDES']);
    return $data;   
}

?>