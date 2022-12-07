<?php
add_filter('simply_syncCustomer', 'simply_syncCustomer_func');
function simply_syncCustomer_func($json)
{

    unset($json['CUSTNAME']);
    $json['CTYPECODE'] = '10';
    $json['AGENTCODE'] = '503';
   // unset($json['STATEA']);
    return $json;

}
