<?php
add_filter('simply_request_data', 'simply_request_data_func');
function simply_request_data_func($data)
{
    $data['CASHNAME'] = '102';
    return $data;
}
